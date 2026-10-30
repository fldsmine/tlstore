<?php

namespace App\Services;

use App\Enums\CloudCallStatus;
use App\Enums\TransactionType;
use App\Events\CloudBroadcastFired;
use App\Models\CloudBroadcast;
use App\Models\CloudCall;
use App\Models\CloudNumber;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CloudCallService
{
    public function availableNumbers(User $user): Collection
    {
        if (! $user->canCloudCall()) {
            return new Collection;
        }

        return CloudNumber::query()
            ->active()
            ->whereDoesntHave('users', fn ($q) => $q->whereKey($user->id))
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();
    }

    public function addNumber(User $user, CloudNumber $number): void
    {
        if (! $user->canCloudCall()) {
            throw ValidationException::withMessages(['cloud' => 'Upgrade to the Royal eSIM to add contacts and make Cloud calls.']);
        }

        if (! $number->is_active) {
            throw ValidationException::withMessages(['cloud' => 'That sponsor is no longer available.']);
        }

        $quota = $user->cloudContactLimit();
        if ($quota > 0 && $user->cloudNumbers()->count() >= $quota && ! $user->cloudNumbers()->whereKey($number->id)->exists()) {
            throw ValidationException::withMessages(['cloud' => 'You have used all your Cloud Storage. Buy a storage plan to add more.']);
        }

        $user->cloudNumbers()->syncWithoutDetaching([$number->id]);
    }

    public function removeNumber(User $user, CloudNumber $number): void
    {
        $user->cloudNumbers()->detach($number->id);
    }

    public function payload(CloudNumber $n): array
    {
        $n->loadMissing(['notes' => fn ($q) => $q->where('is_active', true)]);

        $messages = $n->notes->map(fn ($note): array => ['text' => $note->message, 'audio' => $note->audioUrl()])->values()->all();
        $words = (int) collect($messages)->sum(fn (array $m): int => str_word_count((string) $m['text']));

        return [
            'id' => $n->id,
            'name' => $n->name,
            'brand' => $n->brand,
            'caller' => $n->caller_name,
            'line' => $n->line_number,
            'avatar' => $n->avatar_url,
            'earning' => (float) $n->earning_per_call,
            'messages' => $messages,
            'duration' => max(4, (int) ceil($words / 2.5)),
            'popup' => $n->popup_message,
            'claimUrl' => $n->claim_url,
        ];
    }

    public function activeCall(User $user): ?CloudCall
    {
        return $user->cloudCalls()->where('status', CloudCallStatus::ACTIVE->value)->latest()->first();
    }

    /**
     * Cloud calling is open 8 AM–midnight (Africa/Lagos); the morning gap lets admins rotate the
     * day's active numbers before anyone can earn.
     */
    public function withinEarningWindow(): bool
    {
        return now()->hour >= 8;
    }

    public function startCall(User $user, CloudNumber $number): CloudCall
    {
        if (! (bool) config('settings.cloud.enabled', true)) {
            throw ValidationException::withMessages(['cloud' => 'Nextel Cloud is currently unavailable.']);
        }

        if (! $user->canCloudCall()) {
            throw ValidationException::withMessages(['cloud' => 'Activate or renew your package to continue using Nextel Cloud.']);
        }

        if (! $user->cloudNumbers()->whereKey($number->id)->exists()) {
            throw ValidationException::withMessages(['cloud' => 'Add this number to your list before calling.']);
        }

        // Calling is only open from 8 AM to midnight (Africa/Lagos) — the morning gap gives admins
        // time to rotate the day's active numbers. It's also once per contact per day, and stops
        // once the hidden daily/hourly cap is reached. Any of these blocks the call with the same
        // neutral, rotating "unavailable/busy" toast so nothing about the rules leaks to the user.
        if (! $this->withinEarningWindow()
            || $this->earnedFromNumberToday($user, $number->id)
            || $this->remainingDailyCap($user) <= 0
            || $this->remainingHourlyCap($user) <= 0) {
            throw ValidationException::withMessages(['cloud' => $this->unavailableMessage()]);
        }

        return DB::transaction(function () use ($user, $number): CloudCall {
            User::query()->whereKey($user->id)->lockForUpdate()->first();

            if ($this->activeCall($user)) {
                throw ValidationException::withMessages(['cloud' => 'A call is already active.']);
            }

            return CloudCall::create([
                'user_id' => $user->id,
                'cloud_number_id' => $number->id,
                'number_name' => $number->name,
                'direction' => 'outgoing',
                'status' => CloudCallStatus::ACTIVE,
                'started_at' => now(),
                'required_listen_seconds' => $this->requiredListenSeconds($number),
            ]);
        });
    }

    public function completeCall(User $user, CloudCall $call, int $durationSeconds): CloudCall
    {
        return DB::transaction(function () use ($user, $call, $durationSeconds): CloudCall {

            $locked = CloudCall::query()->whereKey($call->id)->where('user_id', $user->id)->lockForUpdate()->firstOrFail();

            if ($locked->status !== CloudCallStatus::ACTIVE) {
                return $locked;
            }

            $lockedUser = User::query()->whereKey($user->id)->lockForUpdate()->first();
            $number = $locked->cloud_number_id ? CloudNumber::query()->find($locked->cloud_number_id) : null;

            $listenedFully = $locked->started_at
                ? $locked->started_at->diffInSeconds(now()) >= (int) $locked->required_listen_seconds
                : false;

            $onCooldown = $number && $this->earnedFromNumberToday($lockedUser, $number->id, $locked->id);

            $rate = ($number && $listenedFully && ! $onCooldown && $lockedUser->canCloudCall() && (bool) config('settings.cloud.enabled', true)) ? (float) $number->earning_per_call : 0.0;

            $earned = round(min($rate, $this->remainingDailyCap($lockedUser), $this->remainingHourlyCap($lockedUser)), 6);
            $earned = max(0.0, $earned);

            if ($earned > 0) {
                $lockedUser->increment('cloud_call_balance', $earned);
                $lockedUser->increment('lifetime_earnings', $earned);

                Transaction::create([
                    'user_id' => $lockedUser->id,
                    'type' => TransactionType::CLOUD_EARNING,
                    'direction' => 'credit',
                    'amount' => $earned,
                    'wallet' => 'cloud_call',
                    'status' => 'completed',
                    'reference' => 'CLD-'.strtoupper(Str::random(10)),
                    'description' => 'Cloud call · '.$locked->number_name,
                    'meta' => [
                        'cloud_call_id' => $locked->id,
                        'number' => $locked->number_name,
                    ],
                ]);

                app(CloudReferralService::class)->creditUpline($lockedUser, $earned, ['cloud_call_id' => $locked->id]);
            }

            $locked->update([
                'status' => CloudCallStatus::COMPLETED,
                'earned' => $earned,
                'duration_seconds' => max(0, $durationSeconds),
                'ended_at' => now(),
            ]);

            return $locked;
        });
    }

    public function endCall(User $user, CloudCall $call): CloudCall
    {
        return DB::transaction(function () use ($user, $call): CloudCall {
            // Lock + re-check under the lock: a call the user started but did not claim is
            // marked "ended" — never overwrite one that completeCall already booked as completed.
            $locked = CloudCall::query()->whereKey($call->id)->where('user_id', $user->id)->lockForUpdate()->firstOrFail();

            if ($locked->status === CloudCallStatus::ACTIVE) {
                $locked->update([
                    'status' => CloudCallStatus::ENDED,
                    'ended_at' => now(),
                    'duration_seconds' => $locked->started_at ? max(0, $locked->started_at->diffInSeconds(now())) : 0,
                ]);
            }

            return $locked;
        });
    }

    public function earnedFromNumberToday(User $user, string $numberId, ?string $exceptCallId = null): bool
    {
        return $user->cloudCalls()
            ->where('cloud_number_id', $numberId)
            ->completed()
            ->where('earned', '>', 0)
            ->whereDate('ended_at', today())
            ->when($exceptCallId !== null, fn ($q) => $q->whereKeyNot($exceptCallId))
            ->exists();
    }

    public function earnedNumberIdsToday(User $user): array
    {
        return $user->cloudCalls()
            ->completed()
            ->where('earned', '>', 0)
            ->whereDate('ended_at', today())
            ->whereNotNull('cloud_number_id')
            ->distinct()
            ->pluck('cloud_number_id')
            ->all();
    }

    public function dailyEarned(User $user): float
    {
        return (float) $user->cloudCalls()
            ->completed()
            ->whereDate('ended_at', today())
            ->sum('earned');
    }

    public function remainingDailyCap(User $user): float
    {
        $cap = (float) ($user->package?->cloud_daily_earning_cap ?? 0);

        if ($cap <= 0) {
            return INF;
        }

        return max(0.0, $cap - $this->dailyEarned($user));
    }

    public function hourlyEarned(User $user): float
    {
        return (float) $user->cloudCalls()
            ->completed()
            ->where('ended_at', '>=', now()->startOfHour())
            ->sum('earned');
    }

    public function remainingHourlyCap(User $user): float
    {
        $cap = (float) ($user->package?->cloud_hourly_earning_cap ?? 0);

        if ($cap <= 0) {
            return INF;
        }

        return max(0.0, $cap - $this->hourlyEarned($user));
    }

    /**
     * A neutral, rotating "call couldn't go through" line — shown when the earning cap is reached (and
     * on a claim that earns nothing) so the cap itself is never exposed to the user.
     */
    public function unavailableMessage(): string
    {
        $lines = [
            'This number is not available right now. Please try again later.',
            'This number is busy at the moment. Please try again later.',
            'We could not connect to this number. Please try again later.',
            'The number is not reachable right now. Please try again later.',
            'This line is currently busy. Please try again later.',
        ];

        return $lines[array_rand($lines)];
    }

    public function estimateListenSeconds(CloudNumber $number): int
    {
        $words = (int) $number->notes()
            ->where('is_active', true)
            ->get()
            ->sum(fn ($note): int => str_word_count((string) $note->message));

        return max(4, (int) ceil($words / 2.5));
    }

    protected function requiredListenSeconds(CloudNumber $number): int
    {
        return max(3, (int) floor($this->estimateListenSeconds($number) * 0.6));
    }

    public function heartbeat(User $user): void
    {
        User::query()->whereKey($user->id)->update(['last_seen_at' => now()]);
    }

    public function createBroadcast(CloudNumber $number, ?string $adminId, int $minutes): CloudBroadcast
    {
        $broadcast = CloudBroadcast::create([
            'cloud_number_id' => $number->id,
            'number_name' => $number->name,
            'caller_name' => $number->caller_name,
            'created_by' => $adminId,
            'expires_at' => now()->addMinutes(max(1, $minutes)),
        ]);

        try {
            CloudBroadcastFired::dispatch($broadcast);
        } catch (\Throwable $e) {
            report($e);
        }

        return $broadcast;
    }

    public function pendingBroadcastFor(User $user): ?CloudBroadcast
    {
        if (! $user->canCloudCall() || ! (bool) config('settings.cloud.enabled', true)) {
            return null;
        }

        // Calls the user was shown but never picked up (now expired) are logged as missed.
        $this->sweepMissedFor($user);

        $responded = DB::table('cloud_broadcast_user')
            ->where('user_id', $user->id)
            ->whereIn('status', ['answered', 'declined', 'missed'])
            ->pluck('cloud_broadcast_id');

        $broadcast = CloudBroadcast::query()
            ->whereNotNull('cloud_number_id')
            ->where('expires_at', '>', now())
            ->whereNotIn('id', $responded)
            ->latest()
            ->first();

        if ($broadcast) {
            $this->respondBroadcast($user, $broadcast, 'delivered');
        }

        return $broadcast;
    }

    public function answerBroadcast(User $user, CloudBroadcast $broadcast): ?CloudCall
    {
        $number = $broadcast->cloud_number_id ? CloudNumber::query()->find($broadcast->cloud_number_id) : null;

        if ($broadcast->isExpired() || ! $number || ! $number->is_active) {
            $this->resolveIncoming($user, $broadcast, 'missed', CloudCallStatus::MISSED);

            return null;
        }

        return DB::transaction(function () use ($user, $broadcast, $number): CloudCall {
            User::query()->whereKey($user->id)->lockForUpdate()->first();

            if ($this->activeCall($user)) {
                throw ValidationException::withMessages(['cloud' => 'A call is already active.']);
            }

            $this->respondBroadcast($user, $broadcast, 'answered');

            return CloudCall::create([
                'user_id' => $user->id,
                'cloud_number_id' => $number->id,
                'number_name' => $number->name,
                'direction' => 'incoming',
                'status' => CloudCallStatus::ACTIVE,
                'started_at' => now(),
                'required_listen_seconds' => $this->requiredListenSeconds($number),
            ]);
        });
    }

    public function declineBroadcast(User $user, CloudBroadcast $broadcast): void
    {
        $this->resolveIncoming($user, $broadcast, 'declined', CloudCallStatus::DECLINED);
    }

    /**
     * Move the user's response from a live state to a terminal one and log the
     * incoming call exactly once. The status guard makes concurrent callers no-op,
     * so only a call the user was actually shown (delivered) is ever logged.
     */
    protected function resolveIncoming(User $user, CloudBroadcast $broadcast, string $pivotStatus, CloudCallStatus $logStatus): void
    {
        $now = now();

        $transitioned = DB::table('cloud_broadcast_user')
            ->where('cloud_broadcast_id', $broadcast->id)
            ->where('user_id', $user->id)
            ->whereNotIn('status', ['answered', 'declined', 'missed'])
            ->update(['status' => $pivotStatus, 'responded_at' => $now, 'updated_at' => $now]);

        if ($transitioned > 0) {
            $this->logIncoming($user, $broadcast, $logStatus);
        }
    }

    /**
     * Log incoming calls the user was shown but let expire without answering or declining.
     */
    protected function sweepMissedFor(User $user): void
    {
        $broadcastIds = DB::table('cloud_broadcast_user as bu')
            ->join('cloud_broadcasts as b', 'b.id', '=', 'bu.cloud_broadcast_id')
            ->where('bu.user_id', $user->id)
            ->where('bu.status', 'delivered')
            ->whereNotNull('b.cloud_number_id')
            ->where('b.expires_at', '<=', now())
            ->pluck('b.id');

        if ($broadcastIds->isEmpty()) {
            return;
        }

        CloudBroadcast::query()->whereIn('id', $broadcastIds)->get()
            ->each(fn (CloudBroadcast $broadcast) => $this->resolveIncoming($user, $broadcast, 'missed', CloudCallStatus::MISSED));
    }

    protected function logIncoming(User $user, CloudBroadcast $broadcast, CloudCallStatus $status): void
    {
        $now = now();
        $brand = $broadcast->cloud_number_id
            ? CloudNumber::query()->whereKey($broadcast->cloud_number_id)->value('brand')
            : null;

        CloudCall::create([
            'user_id' => $user->id,
            'cloud_number_id' => $broadcast->cloud_number_id,
            'number_name' => $broadcast->number_name,
            'brand' => $brand,
            'direction' => 'incoming',
            'status' => $status,
            'started_at' => $now,
            'ended_at' => $now,
        ]);
    }

    protected function respondBroadcast(User $user, CloudBroadcast $broadcast, string $status): void
    {
        $now = now();
        $row = [
            'cloud_broadcast_id' => $broadcast->id,
            'user_id' => $user->id,
            'status' => $status,
            'responded_at' => in_array($status, ['answered', 'declined', 'missed'], true) ? $now : null,
            'created_at' => $now,
            'updated_at' => $now,
        ];

        if ($status === 'delivered') {

            DB::table('cloud_broadcast_user')->insertOrIgnore($row);

            return;
        }

        DB::table('cloud_broadcast_user')->upsert($row, ['cloud_broadcast_id', 'user_id'], ['status', 'responded_at', 'updated_at']);
    }
}
