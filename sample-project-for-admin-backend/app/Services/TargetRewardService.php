<?php

namespace App\Services;

use App\Enums\RewardTargetStatus;
use App\Enums\TransactionType;
use App\Models\RewardTarget;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class TargetRewardService
{
    public function isEnabled(): bool
    {
        return (bool) config('settings.rewards.enabled', true);
    }

    public function rewardPercent(): float
    {
        return (float) config('settings.rewards.target_percentage', 4);
    }

    public function minTarget(): float
    {
        return (float) config('settings.rewards.min_target', 1);
    }

    public function maxTarget(): float
    {
        return (float) config('settings.rewards.max_target', 0);
    }

    public function todayTarget(User $user): ?RewardTarget
    {
        return $user->rewardTargets()->whereDate('date', today())->first();
    }

    public function setTarget(User $user, float $amount): RewardTarget
    {
        $target = $this->todayTarget($user);

        if ($target) {
            return $target;
        }

        return $user->rewardTargets()->create([
            'date' => today(),
            'target_amount' => round($amount, 6),
            'status' => RewardTargetStatus::ACTIVE,
            'reward_amount' => 0,
            'reward_percent' => 0,
        ]);
    }

    public function progress(User $user, RewardTarget $target): float
    {
        return (float) $user->transactions()
            ->where('type', TransactionType::REFERRAL_BONUS)
            ->where('direction', 'credit')

            ->whereIn('meta->level', ['direct', 'indirect'])
            ->whereDate('created_at', $target->date)
            ->where('created_at', '>=', $target->created_at)
            ->sum('amount');
    }

    public function evaluate(User $user): ?RewardTarget
    {
        $target = $this->todayTarget($user);

        if (! $target || $target->status !== RewardTargetStatus::ACTIVE) {
            return $target;
        }

        if ($this->progress($user, $target) < (float) $target->target_amount) {
            return $target;
        }

        $percent = $this->rewardPercent();

        $target->forceFill([
            'reward_amount' => round((float) $target->target_amount * $percent / 100, 6),
            'reward_percent' => $percent,
            'status' => RewardTargetStatus::ACHIEVED,
            'achieved_at' => now(),
        ])->save();

        return $target;
    }

    public function claim(RewardTarget $target): bool
    {
        return DB::transaction(function () use ($target): bool {
            $flipped = RewardTarget::query()
                ->whereKey($target->getKey())
                ->where('status', RewardTargetStatus::ACHIEVED)
                ->update([
                    'status' => RewardTargetStatus::CLAIMED,
                    'claimed_at' => now(),
                ]);

            if ($flipped === 0) {
                return false;
            }

            $user = $target->user()->first();
            $amount = (float) $target->reward_amount;

            $user->creditEarnings('reward_balance', $amount);

            Transaction::create([
                'user_id' => $user->id,
                'type' => TransactionType::REWARD,
                'direction' => 'credit',
                'amount' => $amount,
                'wallet' => 'reward',
                'status' => 'completed',
                'reference' => 'RWD-'.strtoupper(Str::random(10)),
                'description' => 'Daily target reward',
                'meta' => [
                    'date' => $target->date->toDateString(),
                    'target_amount' => (float) $target->target_amount,
                    'percent' => (float) $target->reward_percent,
                ],
            ]);

            return true;
        });
    }

    public function claimAll(User $user): float
    {
        $total = 0.0;

        $user->rewardTargets()->unclaimed()->get()->each(function (RewardTarget $target) use (&$total): void {
            if ($this->claim($target)) {
                $total += (float) $target->reward_amount;
            }
        });

        return $total;
    }

    public function settleMissed(): int
    {
        return RewardTarget::query()
            ->where('status', RewardTargetStatus::ACTIVE)
            ->whereDate('date', '<', today())
            ->update([
                'status' => RewardTargetStatus::MISSED,
                'updated_at' => now(),
            ]);
    }

    public function lifetimeEarned(User $user): float
    {
        return (float) $user->rewardTargets()
            ->where('status', RewardTargetStatus::CLAIMED)
            ->sum('reward_amount');
    }

    public function streak(User $user): int
    {
        $hitDays = $user->rewardTargets()
            ->whereIn('status', [RewardTargetStatus::ACHIEVED, RewardTargetStatus::CLAIMED])
            ->orderByDesc('date')
            ->pluck('date')
            ->map(fn ($d) => $d->toDateString())
            ->all();

        if ($hitDays === []) {
            return 0;
        }

        $cursor = today();
        if (! in_array($cursor->toDateString(), $hitDays, true)) {
            $cursor = $cursor->copy()->subDay();
        }

        $streak = 0;
        while (in_array($cursor->toDateString(), $hitDays, true)) {
            $streak++;
            $cursor = $cursor->copy()->subDay();
        }

        return $streak;
    }
}
