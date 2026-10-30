<?php

namespace App\Livewire\Dashboard\Cloud;

use App\Enums\ChargePurpose;
use App\Models\CloudCall;
use App\Models\CloudDistribution;
use App\Models\CloudNumber;
use App\Models\CloudStoragePlan;
use App\Models\User;
use App\Services\CloudActivationService;
use App\Services\CloudCallService;
use App\Services\CloudDistributionService;
use App\Services\CloudInboxService;
use App\Services\CloudLineService;
use App\Services\Payments\PaymentGatewayManager;
use App\Support\RegionPricing;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\On;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('components.layouts.app')]
#[Title('Nextel Cloud')]
class CloudHome extends Component
{
    use WithPagination;

    public string $redeemCode = '';

    public ?string $selectedPlanId = null;

    /** Which picker action is open: 'renew' | 'switch' | 'activate' | null. Server-held so it survives
     *  the re-renders a sibling's `cloud-updated` triggers (plain Alpine state would reset to null). */
    public ?string $pickerTab = null;

    public function mount(): void
    {
        // Prefill a code carried over from the buy-chat's "Redeem" button.
        $this->redeemCode = (string) session('carry_code', $this->redeemCode);

        // Deep link from the inbox's "Add more Cloud storage" option — open the storage
        // picker straight away ('switch' for pack holders, 'activate' for first-timers).
        if (request()->boolean('storage')) {
            $this->pickerTab = $this->user()->cloudStorageTopUps()->active()->exists() ? 'switch' : 'activate';
        }
    }

    #[On('cloud-updated')]
    public function refreshCloud(): void {}

    public function redeemToken(CloudActivationService $service): void
    {
        $this->validate([
            'selectedPlanId' => ['required', 'exists:cloud_storage_plans,id'],
            'redeemCode' => ['required', 'string', 'min:6', 'max:32'],
        ], [
            'selectedPlanId.required' => 'Choose the pack your token is for.',
            'redeemCode.required' => 'Enter your Cloud token.',
        ]);

        try {
            $plan = $service->redeem($this->user(), $this->redeemCode, 'redeemCode', $this->selectedPlanId);
        } catch (ValidationException $e) {
            $this->addError('redeemCode', $e->validator->errors()->first());

            return;
        }

        $this->reset('redeemCode', 'selectedPlanId', 'pickerTab');
        $this->resetErrorBag();

        $grants = '+'.$plan->extra_contacts.' '.Str::plural('contact', (int) $plan->extra_contacts);
        if ((int) $plan->extra_sms > 0) {
            $grants .= ' · +'.$plan->extra_sms.' SMS/day';
        }

        $this->dispatch('toast', message: $plan->name.' added — '.$grants.'!', type: 'success');
    }

    /** Open a picker action ('renew' | 'switch' | 'activate') or close it if already open. */
    public function setPicker(string $tab): void
    {
        $this->pickerTab = $this->pickerTab === $tab ? null : $tab;
        $this->reset('selectedPlanId');
        $this->resetErrorBag();
    }

    public function addNumber(string $id, CloudCallService $service): void
    {
        try {
            $service->addNumber($this->user(), CloudNumber::findOrFail($id));
            $this->dispatch('toast', message: 'Number added successfully', type: 'success');
        } catch (ValidationException $e) {
            $this->dispatch('toast', message: $e->validator->errors()->first(), type: 'warning');
        }
    }

    public function claimBonus(string $id, CloudDistributionService $service): void
    {
        $user = $this->user();
        $distribution = CloudDistribution::query()->find($id);

        if ($distribution === null) {
            return;
        }

        if ($distribution->is_shareholder && $user->package_id === null) {
            $this->dispatch('open-activation');
            $this->dispatch('toast', message: 'Activate a plan to claim shareholder earnings into Nextel Shares.', type: 'info');

            return;
        }

        $amount = $service->claim($user, $distribution);
        $wallet = $distribution->is_shareholder ? 'Nextel Shares' : 'N-Cloud Calls';

        $user->refresh();

        $this->dispatch('toast',
            message: $amount > 0 ? 'Claimed '.money($amount).' into your '.$wallet.'.' : 'Nothing left to claim.',
            type: $amount > 0 ? 'success' : 'info',
        );

        $this->dispatch('cloud-badge', count: cloud_badge_count($user));
    }

    protected function user(): User
    {
        return auth()->user();
    }

    /**
     * Direct (card) purchase is offered only while the configured storage gateway is usable; a
     * provider without a secret key hides the Pay buttons and leaves vendor tokens as the way in.
     */
    protected function directPayEnabled(): bool
    {
        return app(PaymentGatewayManager::class)->availableFor(ChargePurpose::STORAGE);
    }

    /**
     * Storage-plan options for the redeem picker, aware of the user's current pack. The plan you're
     * on becomes a Renew entry (renewal price, gated to the expiry window) or is dropped when it can't
     * be renewed; every other plan is a Change/Switch option. No current pack → all are activations.
     *
     * @return Collection<int, array<string, mixed>>
     */
    protected function planOptions(User $user, string $region, CloudActivationService $activation): Collection
    {
        $current = $activation->currentPack($user);
        $currentPlanId = $current?->cloud_storage_plan_id;
        $renewWindowOpen = $activation->withinRenewalWindow($current);

        // Plans tagged with a package tier are SPECIAL coupons — coupon-only, never directly
        // purchasable here (vendors mint them as storage-activation codes instead).
        return CloudStoragePlan::query()->active()->whereNull('tier_level')->ordered()->get()
            ->map(function (CloudStoragePlan $plan) use ($region, $currentPlanId, $current, $renewWindowOpen): ?array {
                $isCurrent = $currentPlanId !== null && $plan->id === $currentPlanId;
                $renewalPrice = RegionPricing::renewalCustomer($plan, $region);

                if ($isCurrent) {
                    // The plan you're on: renew it (when supported), never re-activate it.
                    if ($renewalPrice <= 0) {
                        return null;
                    }

                    return [
                        'id' => $plan->id,
                        'name' => $plan->name,
                        'extra_contacts' => (int) $plan->extra_contacts,
                        'extra_sms' => (int) $plan->extra_sms,
                        'duration_days' => (int) $plan->duration_days,
                        'price' => $renewalPrice,
                        'mode' => 'renew',
                        'available' => $renewWindowOpen,
                        'opens_at' => $current?->expires_at?->copy()->subDay()->format('j M'),
                    ];
                }

                return [
                    'id' => $plan->id,
                    'name' => $plan->name,
                    'extra_contacts' => (int) $plan->extra_contacts,
                    'extra_sms' => (int) $plan->extra_sms,
                    'duration_days' => (int) $plan->duration_days,
                    'price' => RegionPricing::customer($plan, $region),
                    'mode' => $currentPlanId !== null ? 'switch' : 'activate',
                    'available' => true,
                    'opens_at' => null,
                ];
            })
            ->filter()
            ->values();
    }

    public function render(CloudCallService $service, CloudActivationService $activation): View
    {
        $user = $this->user();

        if (! $user->canAccessCloud()) {
            return view('livewire.dashboard.cloud.cloud-home', [
                'canAccess' => false,
                'currentPlan' => $user->package?->name,
            ]);
        }

        $canCall = $user->canCloudCall();
        $region = RegionPricing::forUser($user);

        if (blank($user->cloud_line_number)) {
            app(CloudLineService::class)->ensureLine($user);
        }

        $cooledIds = $canCall ? $service->earnedNumberIdsToday($user) : [];
        $windowOpen = $service->withinEarningWindow();
        $myNumbers = $canCall
            ? $user->cloudNumbers()->with(['notes' => fn ($q) => $q->where('is_active', true)])->orderBy('name')->get()
                // A contact is unavailable when calling is closed (outside the 8 AM–midnight window),
                // its sponsor is inactive, OR the user already earned from it today (once per day).
                ->map(function (CloudNumber $n) use ($service, $cooledIds, $windowOpen): array {
                    $earnedToday = in_array($n->id, $cooledIds, true);

                    return $service->payload($n) + ['cooldown' => $earnedToday, 'disabled' => ! $windowOpen || ! $n->is_active || $earnedToday];
                })
            : collect();

        $recent = $user->cloudCalls()->completed()
            ->where('ended_at', '>=', now()->startOfDay()->subDays(6))
            ->get(['earned', 'ended_at']);

        $byDay = $recent
            ->groupBy(fn (CloudCall $c): string => $c->ended_at->toDateString())
            ->map(fn ($group): float => (float) $group->sum('earned'));

        $week = collect(range(6, 0))->map(function (int $back) use ($byDay): array {
            $day = now()->startOfDay()->subDays($back);
            $amount = round((float) ($byDay[$day->toDateString()] ?? 0), 6);

            return [
                'label' => $day->format('D'),
                'date' => $day->format('j M'),
                'amount' => $amount,
                'display' => money($amount),
            ];
        })->all();

        return view('livewire.dashboard.cloud.cloud-home', [
            'canAccess' => true,

            'canCall' => $canCall,
            'canSms' => $user->canCloudSms(),
            'packageName' => $user->package?->name,
            'currentPlan' => $user->package?->name,
            'inboxUnread' => app(CloudInboxService::class)->unreadCount($user),
            'smsClaimedToday' => app(CloudInboxService::class)->smsClaimedToday($user),
            'smsLimit' => $user->cloudSmsLimit(),
            'bonuses' => app(CloudDistributionService::class)->claimableFor($user),
            'balance' => (float) $user->cloud_call_balance,
            'todayEarned' => $service->dailyEarned($user),
            // Caps hidden from users for now (0 → the UI shows "no cap"); enforcement still uses the
            // package cap at claim time. Restore the package expressions below to re-enable the display.
            'dailyCap' => 0.0, // was: (float) ($user->package?->cloud_daily_earning_cap ?? 0)
            'hourlyEarned' => $service->hourlyEarned($user),
            'hourlyCap' => 0.0, // was: (float) ($user->package?->cloud_hourly_earning_cap ?? 0)
            'callsToday' => $recent->filter(fn (CloudCall $c): bool => $c->ended_at->isToday())->count(),
            'quota' => $user->cloudContactLimit(),

            'baseQuota' => $canCall ? (int) ($user->package?->cloud_contact_limit ?? 0) : 0,
            'quotaUsed' => $myNumbers->count(),
            'myNumbers' => $myNumbers,
            'available' => $canCall ? $service->availableNumbers($user) : collect(),

            // One plan at a time: the plan you're on is never re-offered as a fresh activation. It
            // becomes a Renew entry (renewal price, gated to the expiry window) when the plan supports
            // renewal, or is dropped entirely when it can't be renewed (e.g. lifetime). Every other
            // plan is a Change/Switch option. First-time users (no pack) see all plans as activations.
            'plans' => $this->planOptions($user, $region, $activation),
            // Only tier-less plans are directly purchasable; the Pay button also needs a live gateway.
            'directPay' => $this->directPayEnabled(),
            'topUps' => $user->cloudStorageTopUps()->active()->with('cloudStoragePlan')->latest()->get(),
            'history' => $user->cloudCalls()->latest()->paginate(8),
            'week' => $week,
            'weekTotal' => round((float) collect($week)->sum('amount'), 6),
        ]);
    }
}
