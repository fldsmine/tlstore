<?php

namespace App\Services;

use App\Enums\TransactionType;
use App\Events\CloudInboxMessageArrived;
use App\Models\CloudInboxMessage;
use App\Models\CloudMessage;
use App\Models\CloudNumber;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CloudInboxService
{
    public const SYSTEM_THREAD = '__nextel__';

    public function sendSponsored(CloudNumber $number, string $subject, string $body, float $earnRate, ?string $adminId = null): CloudMessage
    {
        $message = CloudMessage::create([
            'cloud_number_id' => $number->id,
            'sender_name' => $number->brand ?: $number->name,
            'sender_number' => $number->line_number,
            'avatar' => $number->avatar,
            'subject' => $subject,
            'body' => $body,
            'earn_rate' => max(0, $earnRate),
            'claim_url' => $number->claim_url,
            'created_by' => $adminId,
        ]);

        $count = $this->fanOut($message);
        $message->update(['recipients_count' => $count]);

        try {
            CloudInboxMessageArrived::dispatch($message);
        } catch (\Throwable $e) {
            report($e);
        }

        return $message;
    }

    protected function fanOut(CloudMessage $message): int
    {
        $now = now();
        $count = 0;

        // Deliver only to users whose plan actually includes the sponsored inbox — no point
        // creating rows a user can never open. (SMS is a broadcast to all such users; saving a
        // contact is only for calls.)
        User::query()
            ->whereNotNull('cloud_line_number')
            ->whereHas('package', fn ($q) => $q->where('enable_cloud', true)->where('cloud_sms', true))
            ->select('id')
            ->chunkById(500, function ($users) use ($message, $now, &$count): void {
                $rows = $users->map(fn (User $u): array => [
                    'id' => (string) Str::uuid7(),
                    'user_id' => $u->id,
                    'type' => CloudInboxMessage::TYPE_SPONSORED,
                    'cloud_message_id' => $message->id,
                    'cloud_number_id' => $message->cloud_number_id,
                    'sender_name' => $message->sender_name,
                    'sender_number' => $message->sender_number,
                    'avatar' => $message->avatar,
                    'subject' => $message->subject,
                    'body' => $message->body,
                    'earn_rate' => $message->earn_rate,
                    'claim_url' => $message->claim_url,
                    'created_at' => $now,
                    'updated_at' => $now,
                ])->all();

                CloudInboxMessage::insert($rows);
                $count += count($rows);
            });

        return $count;
    }

    public function notify(User $user, string $subject, string $body, array $meta = []): ?CloudInboxMessage
    {
        if (blank($user->cloud_line_number)) {
            return null;
        }

        return CloudInboxMessage::create([
            'user_id' => $user->id,
            'type' => CloudInboxMessage::TYPE_SYSTEM,
            'sender_name' => 'Nextel',
            'sender_number' => $user->cloud_line_number,
            'subject' => $subject,
            'body' => $body,
            'earn_rate' => 0,
            'read_at' => null,
            'meta' => $meta,
        ]);
    }

    public function markRead(User $user, CloudInboxMessage $message): void
    {
        if ($message->user_id === $user->id && $message->read_at === null) {
            $message->forceFill(['read_at' => now()])->save();
        }
    }

    /** Sponsored SMS the user has claimed today (the daily counter behind the SMS limit). */
    public function smsClaimedToday(User $user): int
    {
        return $user->cloudInbox()
            ->where('type', CloudInboxMessage::TYPE_SPONSORED)
            ->whereNotNull('claimed_at')
            ->whereDate('claimed_at', today())
            ->count();
    }

    /** True when the user has hit their daily sponsored-SMS claim limit (0 = no cap). */
    public function smsLimitReached(User $user): bool
    {
        $limit = $user->cloudSmsLimit();

        return $limit > 0 && $this->smsClaimedToday($user) >= $limit;
    }

    public function claim(User $user, CloudInboxMessage $message): float
    {
        return (float) DB::transaction(function () use ($user, $message): float {

            $locked = CloudInboxMessage::query()->whereKey($message->id)->where('user_id', $user->id)->lockForUpdate()->firstOrFail();

            if (! $locked->is_sponsored || (float) $locked->earn_rate <= 0 || $locked->claimed_at !== null) {
                return 0.0;
            }

            $lockedUser = User::query()->whereKey($user->id)->lockForUpdate()->first();

            if (! $lockedUser->canCloudSms() || ! (bool) config('settings.cloud.enabled', true)) {
                return 0.0;
            }

            // Daily SMS access limit — the user row lock serializes concurrent claims, so the
            // count-then-claim check below can't race. Storage packs raise the limit (extra_sms).
            if ($this->smsLimitReached($lockedUser)) {
                return 0.0;
            }

            $earned = (float) $locked->earn_rate;
            $lockedUser->increment('cloud_sms_balance', $earned);
            $lockedUser->increment('lifetime_earnings', $earned);

            Transaction::create([
                'user_id' => $lockedUser->id,
                'type' => TransactionType::CLOUD_EARNING,
                'direction' => 'credit',
                'amount' => $earned,
                'wallet' => 'cloud_sms',
                'status' => 'completed',
                'reference' => 'CLM-'.strtoupper(Str::random(10)),
                'description' => 'Cloud SMS · '.$locked->sender_name,
                'meta' => ['inbox_message_id' => $locked->id, 'sender' => $locked->sender_name],
            ]);

            $locked->forceFill(['claimed_at' => now(), 'read_at' => $locked->read_at ?? now()])->save();

            return $earned;
        });
    }

    protected function threadKeyExpression(): string
    {
        return sprintf(
            'CASE WHEN type = \'%s\' THEN \'%s\' ELSE %s END',
            CloudInboxMessage::TYPE_SYSTEM,
            self::SYSTEM_THREAD,
            $this->sponsoredThreadKey(),
        );
    }

    /**
     * A sponsored thread represents one sponsor NUMBER, so key it by that number's unique id —
     * threads must never merge just because several sponsors share a display line_number (e.g. a
     * placeholder "0901 000 0000"). Falls back to the number/name for any legacy row without an id.
     */
    protected function sponsoredThreadKey(): string
    {
        return "COALESCE(cloud_number_id, NULLIF(sender_number, ''), sender_name)";
    }

    public function conversations(User $user): Collection
    {
        $expression = $this->threadKeyExpression();

        $threads = CloudInboxMessage::query()
            ->where('user_id', $user->id)
            ->selectRaw($expression.' as thread_key')

            ->selectRaw('MAX(id) as last_id')
            ->selectRaw('COUNT(*) as total')
            ->selectRaw('SUM(CASE WHEN read_at IS NULL THEN 1 ELSE 0 END) as unread')
            ->selectRaw(sprintf(
                "SUM(CASE WHEN type = '%s' AND earn_rate > 0 AND claimed_at IS NULL THEN earn_rate ELSE 0 END) as unclaimed",
                CloudInboxMessage::TYPE_SPONSORED,
            ))

            ->groupBy('thread_key')
            ->get();

        if ($threads->isEmpty()) {
            return collect();
        }

        $latest = CloudInboxMessage::query()->findMany($threads->pluck('last_id')->all())->keyBy('id');

        return $threads
            ->map(function (CloudInboxMessage $thread) use ($latest): ?array {
                $message = $latest->get($thread->last_id);

                if ($message === null) {
                    return null;
                }

                $isSystem = $message->type === CloudInboxMessage::TYPE_SYSTEM;

                return [
                    'key' => (string) $thread->thread_key,
                    'name' => $isSystem ? 'Nextel' : (string) $message->sender_name,
                    'number' => $isSystem ? null : $message->sender_number,
                    'avatar' => $isSystem ? null : $message->avatar_url,
                    'is_system' => $isSystem,
                    'snippet' => Str::limit((string) ($message->subject ?: $message->body), 64),
                    'last_at' => $message->created_at,
                    'unread' => (int) $thread->unread,
                    'total' => (int) $thread->total,
                    'unclaimed' => (float) $thread->unclaimed,
                ];
            })
            ->filter()

            ->sortByDesc('last_at')
            ->sortByDesc(fn (array $c): int => $c['is_system'] ? 1 : 0)
            ->values();
    }

    public function threadQuery(User $user, string $key): Builder
    {
        $query = CloudInboxMessage::query()->where('user_id', $user->id);

        if ($key === self::SYSTEM_THREAD) {
            return $query->where('type', CloudInboxMessage::TYPE_SYSTEM);
        }

        return $query
            ->where('type', '!=', CloudInboxMessage::TYPE_SYSTEM)
            ->whereRaw($this->sponsoredThreadKey().' = ?', [$key]);
    }

    public function markThreadRead(User $user, string $key): int
    {
        $unread = $this->threadQuery($user, $key)->unread()->get();

        $unread->each(fn (CloudInboxMessage $message) => $this->markRead($user, $message));

        return $unread->count();
    }

    public function unreadCount(User $user): int
    {
        // No SMS access → no inbox → no unread badge anywhere (dashboard, shortcuts, sidebar).
        if (! $user->canCloudSms()) {
            return 0;
        }

        return $user->cloudInbox()->unread()->count();
    }

    public function unclaimedTotal(User $user): float
    {
        return (float) $user->cloudInbox()->unclaimed()->sum('earn_rate');
    }
}
