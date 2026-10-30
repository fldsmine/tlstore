<?php

namespace App\Services;

use App\Enums\TransactionType;
use App\Models\Ad;
use App\Models\AdShare;
use App\Models\Transaction;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Database\QueryException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class AdService
{
    public function isEnabled(): bool
    {
        return (bool) config('settings.ads.enabled', true);
    }

    public function activeAds(): Collection
    {
        return Ad::query()->active()->orderByDesc('created_at')->get();
    }

    public function hasEarnedCurrent(User $user, Ad $ad): bool
    {
        return AdShare::query()
            ->where('user_id', $user->id)
            ->where('ad_id', $ad->id)
            ->where('ad_version', $ad->version)
            ->exists();
    }

    public function earnedCurrentAdIds(User $user, Collection $ads): array
    {
        if ($ads->isEmpty()) {
            return [];
        }

        $versionById = $ads->mapWithKeys(fn (Ad $ad): array => [$ad->id => $ad->version]);

        return AdShare::query()
            ->where('user_id', $user->id)
            ->whereIn('ad_id', $ads->pluck('id'))
            ->get(['ad_id', 'ad_version'])
            ->filter(fn (AdShare $s): bool => ($versionById[$s->ad_id] ?? null) === $s->ad_version)
            ->pluck('ad_id')
            ->all();
    }

    public function share(User $user, Ad $ad): float
    {
        return DB::transaction(function () use ($user, $ad): float {
            try {
                AdShare::create([
                    'user_id' => $user->id,
                    'ad_id' => $ad->id,
                    'ad_version' => $ad->version,
                    'amount' => (float) $ad->reward,
                    'shared_at' => now(),
                ]);
            } catch (QueryException) {
                return 0.0;
            }

            $ad->increment('shares');

            $amount = (float) $ad->reward;

            if ($amount > 0) {
                $user->creditEarnings('ads_revenue_balance', $amount);

                Transaction::create([
                    'user_id' => $user->id,
                    'type' => TransactionType::AD_REVENUE,
                    'direction' => 'credit',
                    'amount' => $amount,
                    'wallet' => 'ads_revenue',
                    'status' => 'completed',
                    'reference' => 'ADV-'.strtoupper(Str::random(10)),
                    'description' => 'Ad share — '.$ad->name,
                    'meta' => ['ad_id' => $ad->id, 'ad_name' => $ad->name, 'version' => $ad->version],
                ]);
            }

            return $amount;
        });
    }

    public function earnedToday(User $user): float
    {
        return $this->earnedSince($user, now()->startOfDay());
    }

    public function earnedThisWeek(User $user): float
    {
        return $this->earnedSince($user, now()->startOfWeek());
    }

    public function earnedThisMonth(User $user): float
    {
        return $this->earnedSince($user, now()->startOfMonth());
    }

    private function earnedSince(User $user, CarbonInterface $from): float
    {
        return (float) $user->transactions()
            ->where('type', TransactionType::AD_REVENUE)
            ->where('direction', 'credit')
            ->where('created_at', '>=', $from)
            ->sum('amount');
    }

    public function growFigures(): int
    {
        $grown = 0;

        Ad::query()->active()->where('growth_percent', '>', 0)->each(function (Ad $ad) use (&$grown): void {
            $intervalHours = max(1, (int) $ad->growth_interval_hours);

            if ($ad->last_grown_at !== null && $ad->last_grown_at->gt(now()->subHours($intervalHours))) {
                return;
            }

            $factor = 1 + ((float) $ad->growth_percent / 100);

            $ad->forceFill([
                'impressions' => (int) round($ad->impressions * $factor),
                'views' => (int) round($ad->views * $factor),
                'shares' => (int) round($ad->shares * $factor),
                'reward' => round((float) $ad->reward * $factor, 6),
                'last_grown_at' => now(),
            ])->save();

            $grown++;
        });

        return $grown;
    }

    public function resetReward(Ad $ad): void
    {
        $ad->forceFill([
            'reward' => (float) $ad->base_reward,
            'last_grown_at' => null,
        ])->save();
    }
}
