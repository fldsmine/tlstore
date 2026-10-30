<?php

namespace App\Services;

use App\Enums\PayLaterStatus;
use App\Enums\TransactionType;
use App\Models\AdditionalMinutePlan;
use App\Models\AllocatedNumber;
use App\Models\CallClaim;
use App\Models\MinuteCredit;
use App\Models\Transaction;
use App\Models\User;
use App\Support\RegionPricing;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class PayLaterService
{
    public function isEnabled(): bool
    {
        return (bool) config('settings.paylater.enabled', false);
    }

    public function isEligible(User $user): bool
    {
        if (! $this->isEnabled()) {
            return false;
        }

        if (config('settings.paylater.audience') === 'everyone') {
            return true;
        }

        if ($user->minuteCredits()->exists()) {
            return true;
        }

        $limit = (int) config('settings.paylater.limit', 0);

        return MinuteCredit::query()->distinct()->count('user_id') < $limit;
    }

    public function hasOutstanding(User $user): bool
    {
        return $user->minuteCredits()->outstanding()->exists();
    }

    public function apply(User $user, AdditionalMinutePlan $plan): MinuteCredit
    {
        return DB::transaction(function () use ($user, $plan): MinuteCredit {
            $topUp = app(PackageActivationService::class)->addMinutes($user, $plan);

            return MinuteCredit::create([
                'user_id' => $user->id,
                'additional_minute_plan_id' => $plan->id,
                'minute_top_up_id' => $topUp->id,
                'plan_name' => $plan->name,

                'amount' => RegionPricing::customer($plan, RegionPricing::forUser($user)),
                'minutes' => (float) $plan->daily_call_minutes,
                'due_date' => now()->addDays((int) ($plan->paylater_duration_days ?: 14))->toDateString(),
                'status' => PayLaterStatus::ACTIVE,
            ]);
        });
    }

    public function repay(MinuteCredit $credit): void
    {
        $credit->update(['status' => PayLaterStatus::REPAID, 'repaid_at' => now()]);
    }

    public function extend(MinuteCredit $credit, int $days): void
    {
        DB::transaction(function () use ($credit, $days): void {
            $base = $credit->due_date ?? today();
            $credit->update(['due_date' => $base->copy()->addDays($days)]);

            $topUp = $credit->minuteTopUp;
            if ($topUp === null) {
                return;
            }

            $topUp->loadMissing('shareSubscription');
            if ($topUp->expires_at !== null) {
                $extendedExpiry = $topUp->expires_at->copy()->addDays($days);
                $updates = ['expires_at' => $extendedExpiry];

                if ((float) $topUp->share_minutes > 0 && $topUp->share_expires_at !== null) {
                    $shareExpiry = $topUp->shareSubscription?->expires_at;
                    $updates['share_expires_at'] = $shareExpiry !== null && $shareExpiry->lessThan($extendedExpiry)
                        ? $shareExpiry
                        : $extendedExpiry;
                }

                $topUp->update($updates);
            }

            $topUp->allocatedNumbers()
                ->whereNotNull('expires_at')
                ->get()
                ->each(fn (AllocatedNumber $number) => $number->update([
                    'expires_at' => $number->expires_at->copy()->addDays($days),
                ]));
        });
    }

    public function forfeitOverdue(): int
    {
        $overdue = MinuteCredit::query()->outstanding()
            ->whereDate('due_date', '<', today())
            ->with(['minuteTopUp', 'user'])
            ->get();

        foreach ($overdue as $credit) {
            $this->forfeit($credit);
        }

        return $overdue->count();
    }

    public function forfeit(MinuteCredit $credit): void
    {
        DB::transaction(function () use ($credit): void {
            $credit->loadMissing('minuteTopUp', 'user');

            $topUp = $credit->minuteTopUp;
            if ($topUp !== null) {
                $topUp->update(['expires_at' => now(), 'share_expires_at' => now()]);
                $topUp->allocatedNumbers()->update(['expires_at' => now()]);
            }

            $this->clawbackEarnings($credit);

            $credit->update(['status' => PayLaterStatus::DEFAULTED]);
        });
    }

    private function clawbackEarnings(MinuteCredit $credit): float
    {
        $user = $credit->user;
        $topUp = $credit->minuteTopUp;
        if ($user === null || $topUp === null) {
            return 0.0;
        }

        // A call can use minutes from both a top-up and the base/other pools. Reverse only the
        // fraction funded by this top-up; pre-ledger claims retain the historical whole-claim rule.
        $allocatedEarnings = (float) DB::table('share_minute_allocations as allocations')
            ->join('call_claims as claims', 'claims.id', '=', 'allocations.call_claim_id')
            ->where('claims.user_id', $user->id)
            ->where('allocations.minute_top_up_id', $topUp->id)
            ->selectRaw('COALESCE(SUM(CASE WHEN claims.duration_minutes > 0 THEN claims.amount * allocations.minutes / claims.duration_minutes ELSE 0 END), 0) AS earned')
            ->value('earned');

        $legacyEarnings = (float) CallClaim::query()
            ->where('user_id', $user->id)
            ->where('minute_top_up_id', $topUp->id)
            ->whereNotExists(fn ($query) => $query->selectRaw('1')
                ->from('share_minute_allocations as allocations')
                ->whereColumn('allocations.call_claim_id', 'call_claims.id'))
            ->sum('amount');

        $earned = round($allocatedEarnings + $legacyEarnings, 6);

        $reverse = round(min($earned, (float) $user->shareholder_balance), 6);
        if ($reverse <= 0) {
            return 0.0;
        }

        $user->decrement('shareholder_balance', $reverse);

        Transaction::create([
            'user_id' => $user->id,
            'type' => TransactionType::ADJUSTMENT,
            'direction' => 'debit',
            'amount' => $reverse,
            'wallet' => 'shareholder',
            'status' => 'completed',
            'reference' => 'ADJ-'.strtoupper(Str::random(10)),
            'description' => 'Additional-minute earnings reversed — Pay Later default ('.$credit->plan_name.')',
            'meta' => [
                'reason' => 'paylater_default',
                'minute_credit_id' => $credit->id,
                'minute_top_up_id' => $topUp->id,
                'earned_from_plan' => round($earned, 6),
            ],
        ]);

        return $reverse;
    }
}
