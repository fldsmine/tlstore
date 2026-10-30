<?php

namespace App\Livewire\Dashboard;

use App\Enums\RewardTargetStatus;
use App\Livewire\Concerns\InteractsWithTransactionDetails;
use App\Models\CallType;
use App\Models\Package;
use App\Services\ClaimService;
use App\Services\TargetRewardService;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('components.layouts.app')]
#[Title('Dashboard')]
class Dashboard extends Component
{
    use InteractsWithTransactionDetails;

    public function render(): View
    {
        $user = auth()->user()->loadMissing('package');

        $byType = $user->callClaims()
            ->selectRaw('call_type_id, sum(duration_minutes) as minutes, sum(amount) as amount, count(*) as calls')
            ->groupBy('call_type_id')
            ->get();

        $callTypes = CallType::query()->get()->keyBy('id');

        $totalDuration = (float) $byType->sum('minutes');
        $totalCalls = (int) $byType->sum('calls');
        $totalEarnings = (float) $byType->sum('amount');
        $activeNumbers = $user->allocatedNumbers()->active()->count();

        $segments = $byType
            ->map(fn ($row) => [
                'name' => $callTypes->get($row->call_type_id)?->key?->getName() ?? 'Other',
                'color' => $callTypes->get($row->call_type_id)?->color ?: '#8c8c8c',
                'duration' => (float) $row->minutes,
            ])
            ->sortByDesc('duration')
            ->values()
            ->map(fn ($s) => [...$s, 'pct' => $totalDuration > 0 ? round($s['duration'] / $totalDuration * 100, 1) : 0])
            ->all();

        $shareClaims = app(ClaimService::class);
        $shareSubscription = $shareClaims->subscription($user);
        $allocated = $shareClaims->allocatedMinutes($user, $shareSubscription);
        $remainingMinutes = $shareClaims->remainingMinutes($user, $shareSubscription);
        $availableMinutes = $shareClaims->availableMinutes($user, $shareSubscription);
        $usedMinutes = max(0, $allocated - $remainingMinutes);
        $dailyShareCap = $shareClaims->maxDailyEarning($user, $shareSubscription);
        $dailyShareCapRemaining = $shareClaims->remainingEarning($user, $shareSubscription);
        $shareRenewalAvailable = $user->package !== null
            && $shareSubscription === null
            && app(\App\Services\ShareSubscriptionService::class)->packageIsConfigured($user->package);
        $directPackagePay = app(\App\Services\Payments\PaymentGatewayManager::class)
            ->availableFor(\App\Enums\ChargePurpose::PACKAGE);

        $rewards = app(TargetRewardService::class);
        $rewards->evaluate($user);
        $todayTarget = $rewards->todayTarget($user);
        $targetAmount = $todayTarget ? (float) $todayTarget->target_amount : 0.0;
        $targetProgress = $todayTarget ? min($rewards->progress($user, $todayTarget), $targetAmount ?: INF) : 0.0;

        return view('livewire.dashboard.dashboard', [
            'user' => $user,
            'performance' => [
                'segments' => $segments,
                'totalDuration' => $totalDuration,
                'activeNumbers' => $activeNumbers,
                'totalCalls' => $totalCalls,
                'avgPerMin' => $totalDuration > 0 ? $totalEarnings / $totalDuration : 0,
            ],
            'minutes' => [
                'hasPackage' => (bool) $user->package,
                'hasSharePlan' => $shareSubscription !== null,
                'allocated' => $allocated,
                'used' => $usedMinutes,
                'remaining' => $remainingMinutes,
                'available' => $availableMinutes,
                'dailyCap' => $dailyShareCap,
                'dailyCapRemaining' => $dailyShareCapRemaining,
                'expiresAt' => $shareSubscription?->expires_at,
                'shareRenewalAvailable' => $shareRenewalAvailable,
                'usedPct' => $allocated > 0 ? (int) round($usedMinutes / $allocated * 100) : 0,
            ],
            'canUpgrade' => Package::query()
                ->when($user->package, fn ($q) => $q->where('tier_level', '>', $user->package->tier_level))
                ->exists(),
            'directPackagePay' => $directPackagePay,
            'reward' => [
                'enabled' => $rewards->isEnabled(),
                'lifetime' => $rewards->lifetimeEarned($user),
                'unclaimed' => (float) $user->rewardTargets()->unclaimed()->sum('reward_amount'),
                'hasTarget' => (bool) $todayTarget,
                'targetAmount' => $targetAmount,
                'progress' => $targetProgress,
                'pct' => $targetAmount > 0 ? (int) min(100, round($targetProgress / $targetAmount * 100)) : 0,
                'locked' => $todayTarget && in_array($todayTarget->status, [RewardTargetStatus::ACHIEVED, RewardTargetStatus::CLAIMED], true),
            ],
            'recent' => $user->transactions()->latest('created_at')->take(4)->get(),
            'selectedTx' => $this->selectedTransaction(),
        ]);
    }
}
