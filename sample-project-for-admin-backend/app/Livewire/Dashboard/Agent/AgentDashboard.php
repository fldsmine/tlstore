<?php

namespace App\Livewire\Dashboard\Agent;

use App\Enums\CouponType;
use App\Models\AdditionalMinutePlan;
use App\Models\CloudStoragePlan;
use App\Models\CallType;
use App\Models\Coupon;
use App\Models\CouponBatch;
use App\Models\Package;
use App\Models\User;
use App\Services\LoanService;
use App\Services\ShareSubscriptionService;
use App\Support\RegionPricing;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('components.layouts.app')]
#[Title('Vendor Dashboard')]
class AgentDashboard extends Component
{
    use WithPagination;

    public string $planKey = '';

    public $quantity = 1;

    public bool $generating = false;

    public string $search = '';

    public string $typeFilter = '';

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedTypeFilter(): void
    {
        $this->resetPage();
    }

    public function mount(): void
    {
        if (! auth()->user()->is_agent) {
            $this->redirectRoute('dashboard', navigate: true);

            return;
        }

        $this->generating = request()->boolean('generate');
    }

    public function openGenerate(): void
    {
        $this->resetValidation();
        $this->generating = true;
    }

    public function closeGenerate(): void
    {
        $this->generating = false;
        $this->reset('planKey');
        $this->quantity = 1;
        $this->resetValidation();
    }

    protected function planOptions(): Collection
    {

        $region = Coupon::regionFor(auth()->user());
        $foreign = $region === Coupon::REGION_FOREIGN;

        $activePackages = Package::active()->ordered()->get();

        $packages = $activePackages->map(fn (Package $p): array => [
            'key' => 'package:'.$p->id,
            'group' => 'Activation Packages',
            'label' => $p->name,
            'agent_price' => RegionPricing::agent($p, $region),
            'price' => RegionPricing::customer($p, $region),
        ]);

        // Upgrade codes for any package that has an upgrade price set (a higher-tier target).
        $upgrades = $activePackages
            ->filter(fn (Package $p): bool => RegionPricing::upgradeCustomer($p, $region) > 0)
            ->map(fn (Package $p): array => [
                'key' => 'upgrade:'.$p->id,
                'group' => 'Package Upgrades',
                'label' => $p->name.' Upgrade',
                'agent_price' => RegionPricing::upgradeAgent($p, $region),
                'price' => RegionPricing::upgradeCustomer($p, $region),
            ]);

        $minutes = AdditionalMinutePlan::active()->ordered()->get()->map(fn (AdditionalMinutePlan $p): array => [
            'key' => 'minutes:'.$p->id,
            'group' => 'Extra Minutes',
            'label' => $p->name,
            'agent_price' => RegionPricing::agent($p, $region),
            'price' => RegionPricing::customer($p, $region),
        ]);

        $shareProducts = app(ShareSubscriptionService::class);
        $shares = CallType::query()->active()->ordered()->get()
            ->filter(fn (CallType $callType): bool => $shareProducts->callTypeIsCouponPurchasable($callType, $region))
            ->map(fn (CallType $callType): array => [
                'key' => 'shares:'.$callType->id,
                'group' => 'Share Access',
                'label' => $callType->name,
                'agent_price' => RegionPricing::agent($callType, $region),
                'price' => RegionPricing::customer($callType, $region),
            ]);

        $activeCloud = CloudStoragePlan::active()->ordered()->get();

        // Cloud storage plans split by tier_level: plans tagged with a package tier are SPECIAL
        // coupons (coupon-only — not directly purchasable); plans without one are regular
        // purchasable storage plans.
        $cloud = $activeCloud->filter(fn (CloudStoragePlan $p): bool => $p->tier_level === null)->map(fn (CloudStoragePlan $p): array => [
            'key' => 'cloud:'.$p->id,
            'group' => 'Cloud Storage Plans',
            'label' => $p->name,
            'agent_price' => RegionPricing::agent($p, $region),
            'price' => RegionPricing::customer($p, $region),
            'special' => false,
        ]);

        $special = $activeCloud->filter(fn (CloudStoragePlan $p): bool => $p->tier_level !== null)->map(fn (CloudStoragePlan $p): array => [
            'key' => 'cloud:'.$p->id,
            'group' => 'Special Coupons',
            'label' => $p->name,
            'agent_price' => RegionPricing::agent($p, $region),
            'price' => RegionPricing::customer($p, $region),
            'special' => true,
            'tier_level' => (int) $p->tier_level,
        ]);

        // Renewal codes for any regular cloud plan that has a renewal price set (renewable plans only).
        $cloudRenewals = $activeCloud
            ->filter(fn (CloudStoragePlan $p): bool => $p->tier_level === null && RegionPricing::renewalCustomer($p, $region) > 0)
            ->map(fn (CloudStoragePlan $p): array => [
                'key' => 'cloud_renewal:'.$p->id,
                'group' => 'Cloud Storage Renewals',
                'label' => $p->name.' Renewal',
                'agent_price' => RegionPricing::renewalAgent($p, $region),
                'price' => RegionPricing::renewalCustomer($p, $region),
            ]);

        // When PIN reset tokens are disabled, drop the option entirely — this also blocks generate(),
        // which only accepts a planKey found in these options.
        $pinReset = config('settings.token.pin_reset.enabled') ? collect([[
            'key' => 'pin_reset',
            'group' => 'Other',
            'label' => 'PIN Reset Token',
            'agent_price' => RegionPricing::pinResetAgent($region),
            'price' => RegionPricing::pinResetCustomer($region),
        ]]) : collect();

        // Website-renewal codes hidden for now — website renewal is online-payment only.
        $websiteRenewal = collect();
        // $websiteRenewal = config('settings.website.renewal_mode') === 'code' ? collect([[
        //     'key' => 'website_renewal',
        //     'group' => 'Other',
        //     'label' => 'Website Renewal Code',
        //     'agent_price' => RegionPricing::websiteRenewalAgent($region),
        //     'price' => RegionPricing::websiteRenewalCustomer($region),
        // ]]) : collect();

        $loanFees = collect();
        $loanService = app(LoanService::class);
        if ((bool) config('settings.loan.enabled', false) && $loanService->applicationFeeMode() === 'fixed') {
            $localFee = $region === Coupon::REGION_NIGERIA
                ? '₦'.number_format($loanService->applicationFeeNgn(), 2)
                : '$'.number_format($loanService->applicationFeeUsd(), 2);
            $loanFees = collect([[
                'key' => 'loan_fee',
                'group' => 'Loan Application Fees',
                'label' => 'Loan Application Fee Coupon — '.$localFee,
                'agent_price' => $loanService->vendorCouponPriceUsd($region),
                'price' => $loanService->customerCouponPriceUsd($region),
                'loan_fee' => true,
            ]]);
        }

        return $packages->concat($upgrades)->concat($minutes)->concat($shares)->concat($cloud)->concat($special)->concat($cloudRenewals)->concat($pinReset)->concat($websiteRenewal)->concat($loanFees)->values();
    }

    protected function selectedPlan(): ?array
    {
        return $this->planOptions()->firstWhere('key', $this->planKey);
    }

    public function generate(): void
    {
        $user = auth()->user();
        abort_unless($user->is_agent, 403);

        $this->validate([
            'planKey' => ['required', 'string'],
            'quantity' => ['required', 'integer', 'min:1', 'max:500'],
        ], [
            'planKey.required' => 'Select a plan.',
            'quantity.min' => 'Generate at least 1 code.',
            'quantity.max' => 'You can generate up to 500 codes at once.',
        ]);

        $plan = $this->selectedPlan();

        if (! $plan) {
            $this->addError('planKey', 'Select a valid plan.');

            return;
        }

        $quantity = (int) $this->quantity;
        $cost = round($plan['agent_price'] * $quantity, 2);

        if ((float) $user->agent_balance < $cost) {
            $this->addError('quantity', 'Insufficient vendor balance for this purchase.');

            return;
        }

        [$kind, $refId] = array_pad(explode(':', $this->planKey, 2), 2, null);
        $type = match (true) {
            $kind === 'upgrade' => CouponType::UPGRADE,
            $kind === 'minutes' => CouponType::EXTRA_MINUTES,
            $kind === 'shares' => CouponType::SHARE_ACTIVATION,
            $kind === 'cloud' && ($plan['special'] ?? false) => CouponType::STORAGE_ACTIVATION,
            $kind === 'cloud' => CouponType::CLOUD,
            $kind === 'cloud_renewal' => CouponType::CLOUD_RENEWAL,
            $kind === 'website_renewal' => CouponType::WEBSITE_RENEWAL,
            $kind === 'pin_reset' => CouponType::PIN_RESET,
            $kind === 'loan_fee' => CouponType::LOAN_FEE,
            default => CouponType::ACTIVATION,
        };

        // Special coupons expire (settings.token.special_expiry_days); regular codes never do.
        $expiresAt = $type === CouponType::STORAGE_ACTIVATION
            ? now()->addDays((int) config('settings.token.special_expiry_days', 90))
            : null;

        DB::transaction(function () use ($user, $plan, $cost, $kind, $refId, $type, $quantity, $expiresAt): void {
            $locked = User::whereKey($user->id)->lockForUpdate()->first();

            if ((float) $locked->agent_balance < $cost) {
                throw ValidationException::withMessages(['quantity' => 'Insufficient vendor balance.']);
            }

            $region = Coupon::regionFor($user);

            $batch = CouponBatch::create([
                'agent_id' => $user->id,
                'package_id' => in_array($kind, ['package', 'upgrade'], true) ? $refId : null,
                'call_type_id' => $kind === 'shares' ? $refId : null,
                'additional_minute_plan_id' => $kind === 'minutes' ? $refId : null,
                'cloud_storage_plan_id' => in_array($kind, ['cloud', 'cloud_renewal'], true) ? $refId : null,
                'type' => $type,
                'region' => $region,
                'quantity' => $quantity,
            ]);

            for ($i = 0; $i < $quantity; $i++) {
                Coupon::create([
                    'type' => $type,
                    'region' => $region,
                    'agent_id' => $user->id,
                    'package_id' => in_array($kind, ['package', 'upgrade'], true) ? $refId : null,
                    'call_type_id' => $kind === 'shares' ? $refId : null,
                    'additional_minute_plan_id' => $kind === 'minutes' ? $refId : null,
                    'cloud_storage_plan_id' => in_array($kind, ['cloud', 'cloud_renewal'], true) ? $refId : null,
                    'batch_id' => $batch->id,
                    'price' => $plan['price'],
                    'expires_at' => $expiresAt,

                    'code' => Coupon::generateCode($user->username, $plan['label']),
                ]);
            }

            $locked->decrement('agent_balance', $cost);
        });

        $this->closeGenerate();
        $this->resetPage();
        session()->flash('token-success', "{$quantity} {$plan['label']} token(s) generated.");
    }

    public function render(): View
    {
        $user = auth()->user();

        $total = Coupon::query()->where('agent_id', $user->id)->count();
        $used = Coupon::query()->where('agent_id', $user->id)->whereNotNull('user_id')->count();

        $batches = CouponBatch::query()
            ->where('agent_id', $user->id)
            ->when($this->search !== '', fn ($q) => $q->where('reference', 'like', '%'.trim($this->search).'%'))
            ->when($this->typeFilter !== '', fn ($q) => $q->where('type', $this->typeFilter))
            ->with(['package', 'callType', 'additionalMinutePlan', 'cloudStoragePlan'])
            ->withCount([
                'coupons as total_count',
                'coupons as used_count' => fn ($q) => $q->whereNotNull('user_id'),
            ])
            ->latest()
            ->paginate(8);

        $planOptions = $this->planOptions();
        $plan = $planOptions->firstWhere('key', $this->planKey);

        return view('livewire.dashboard.agent.agent-dashboard', [
            'agentBalance' => (float) $user->agent_balance,
            'stats' => ['total' => $total, 'active' => $total - $used, 'used' => $used],
            'batches' => $batches,
            'types' => ['' => 'All types', 'activation' => 'Activation', 'share_activation' => 'Share Access', 'upgrade' => 'Upgrade', 'extra_minutes' => 'Extra Minutes', 'cloud' => 'Cloud', 'cloud_renewal' => 'Cloud Renewal', 'storage_activation' => 'Special Coupon', 'website_renewal' => 'Website Renewal', 'pin_reset' => 'PIN Reset', 'loan_fee' => 'Loan Application Fee'],
            'planOptions' => $planOptions,
            'selectedPlan' => $plan,
            'estimate' => $plan ? round($plan['agent_price'] * max(0, (int) $this->quantity), 2) : 0.0,
        ]);
    }
}
