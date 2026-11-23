<?php

namespace App\Livewire\Dashboard\Minutes;

use App\Enums\ChargePurpose;
use App\Models\AdditionalMinutePlan;
use App\Services\Payments\PaymentGatewayManager;
use App\Services\ShareSubscriptionService;
use App\Services\TokenRedemptionService;
use App\Support\RegionPricing;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('components.layouts.app')]
#[Title('Shares')]
class Plans extends Component
{
    public ?string $planId = null;

    public string $token = '';

    public bool $done = false;

    public ?string $redeemedPlan = null;

    public function mount(): void
    {
        // Prefill a code carried over from the buy-chat's "Redeem" button.
        $this->token = (string) session('carry_code', $this->token);
    }

    public function redeem(TokenRedemptionService $redemption): void
    {
        if (! config('settings.minutes.enabled', false)) {
            $this->dispatch('toast', message: 'Share purchases are currently unavailable.', type: 'error');

            return;
        }

        $user = auth()->user()->loadMissing('package');

        $this->validate([
            'planId' => ['required', 'exists:additional_minute_plans,id'],
            'token' => ['required', 'string', 'min:6', 'max:32'],
        ], [
            'planId.required' => 'Choose the minutes plan your token is for.',
            'token.required' => 'Enter the minutes token you bought from an agent.',
        ]);

        $topUp = $redemption->redeemMinutes($user, (string) $this->planId, $this->token, 'planId', 'token');

        $this->redeemedPlan = $topUp->plan?->name;
        $this->done = true;
        $this->reset(['token', 'planId']);
        $this->dispatch('toast', message: ($topUp->plan?->name ?? 'Your plan').' is now active on your account.', type: 'success');
    }

    public function render(): View
    {
        $user = auth()->user()->loadMissing('package');
        $shareClaims = app(\App\Services\ClaimService::class);
        $shareProducts = app(ShareSubscriptionService::class);
        $shareSubscription = $shareClaims->subscription($user);

        $baseMinutes = (float) ($user->package?->daily_call_minutes ?? 0);

        $activeTopUps = $user->minuteTopUps()->activeOrShareActive()->with(['plan', 'shareSubscription'])->latest()->get();
        $bonusMinutes = (float) $user->minuteTopUps()->active()->sum('daily_call_minutes');
        $shareAllocated = $shareClaims->allocatedMinutes($user, $shareSubscription);
        $shareRemaining = $shareClaims->remainingMinutes($user, $shareSubscription);
        $shareAvailable = $shareClaims->availableMinutes($user, $shareSubscription);
        $shareRenewalAvailable = $user->package !== null
            && $shareSubscription === null
            && $shareProducts->packageIsConfigured($user->package);
        $directPackagePay = app(PaymentGatewayManager::class)->availableFor(ChargePurpose::PACKAGE);
        $shareOnly = $user->package_id === null;

        $allPlans = AdditionalMinutePlan::query()->active()->ordered()->get();
        $plans = $shareOnly
            ? $allPlans->filter(fn (AdditionalMinutePlan $plan): bool => (float) $plan->share_minutes > 0
                && (float) $plan->daily_call_minutes <= 0
                && (int) $plan->call_id_bonus <= 0)->values()
            : $allPlans;
        $directSharePlans = $plans->filter(fn (AdditionalMinutePlan $plan): bool => (float) $plan->share_minutes > 0)->values();
        $region = RegionPricing::forUser($user);

        return view('livewire.dashboard.minutes.plans', [
            'enabled' => (bool) config('settings.minutes.enabled', false),
            'disabledMessage' => (string) config('settings.minutes.disabled_message'),
            'packageName' => $user->package?->name,
            'plans' => $plans,

            'prices' => $plans->mapWithKeys(fn (AdditionalMinutePlan $plan): array => [
                $plan->id => RegionPricing::customer($plan, $region),
            ]),
            'baseMinutes' => $baseMinutes,
            'bonusMinutes' => $bonusMinutes,
            'totalMinutes' => round($baseMinutes + $bonusMinutes, 2),
            'shareAllocated' => $shareAllocated,
            'shareRemaining' => $shareRemaining,
            'shareAvailable' => $shareAvailable,
            'shareSubscription' => $shareSubscription,
            'shareRenewalAvailable' => $shareRenewalAvailable,
            'directPackagePay' => $directPackagePay,
            'directSharePlans' => $directSharePlans,
            'canBuyShareMinutes' => $shareSubscription !== null && $directPackagePay,
            'shareOnly' => $shareOnly,
            'activeTopUps' => $activeTopUps,
        ]);
    }
}
