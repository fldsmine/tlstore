<?php

namespace App\Livewire\Backoffice\Packages;

use App\Livewire\Backoffice\Concerns\AuthorizesAdminActions;
use App\Models\CallType;
use App\Models\Package;
use App\Services\ShareQuotaService;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('components.layouts.backoffice')]
#[Title('Packages — Backoffice')]
class ListPackages extends Component
{
    use AuthorizesAdminActions;

    public bool $showModal = false;

    public ?string $editingId = null;

    public string $name = '';

    public string $description = '';

    public string $price = '';

    public string $agentPrice = '';

    public string $directReferralBonus = '';

    public string $indirectReferralBonus = '';

    public string $upgradePrice = '';

    public string $upgradeAgentPrice = '';

    public string $foreignUpgradePrice = '';

    public string $foreignUpgradeAgentPrice = '';

    public string $upgradeDirectReferralBonus = '';

    public string $upgradeIndirectReferralBonus = '';

    public string $dailyCallMinutes = '';

    public string $monthlyShareMinutes = '';

    public string $shareTermDays = '30';

    public string $maxDailyShareholderEarning = '';

    public string $callIdLimit = '';

    public string $tierLevel = '';

    public string $features = '';

    public string $foreignPrice = '';

    public string $foreignAgentPrice = '';

    public string $successMessage = '';

    public bool $enableCloud = false;

    public bool $cloudCalls = false;

    public bool $cloudSms = false;

    public string $cloudContactLimit = '';

    public string $cloudSmsLimit = '';

    public string $cloudDailyEarningCap = '';

    public string $cloudHourlyEarningCap = '';

    public bool $isFeatured = false;

    public bool $isActive = true;

    public array $callTypeConfig = [];

    public bool $showDetail = false;

    public ?string $detailId = null;

    public function openCreate(): void
    {
        $this->authorizeWrite();
        $this->reset('editingId', 'name', 'description', 'price', 'agentPrice', 'directReferralBonus', 'indirectReferralBonus', 'upgradePrice', 'upgradeAgentPrice', 'foreignUpgradePrice', 'foreignUpgradeAgentPrice', 'upgradeDirectReferralBonus', 'upgradeIndirectReferralBonus', 'dailyCallMinutes', 'monthlyShareMinutes', 'shareTermDays', 'maxDailyShareholderEarning', 'callIdLimit', 'tierLevel', 'features', 'successMessage', 'foreignPrice', 'foreignAgentPrice', 'enableCloud', 'cloudCalls', 'cloudSms', 'cloudContactLimit', 'cloudSmsLimit', 'cloudDailyEarningCap', 'cloudHourlyEarningCap', 'isFeatured');
        $this->isActive = true;

        $this->callTypeConfig = $this->callTypes()
            ->mapWithKeys(fn (CallType $ct) => [$ct->id => ['enabled' => true, 'rate' => '', 'claim' => '']])
            ->all();
        $this->resetValidation();
        $this->showModal = true;
    }

    public function openDetail(string $id): void
    {
        $this->detailId = $id;
        $this->showDetail = true;
    }

    public function edit(string $id): void
    {
        $this->authorizeWrite();
        $p = Package::query()->with('callTypes')->findOrFail($id);
        $this->editingId = $p->id;
        $this->name = (string) $p->name;
        $this->description = (string) $p->description;
        $this->price = (string) $p->price;
        $this->agentPrice = (string) $p->agent_price;
        $this->directReferralBonus = (string) $p->direct_referral_bonus;
        $this->indirectReferralBonus = (string) $p->indirect_referral_bonus;
        $this->upgradePrice = (string) $p->upgrade_price;
        $this->upgradeAgentPrice = (string) $p->upgrade_agent_price;
        $this->foreignUpgradePrice = $p->foreign_upgrade_price === null ? '' : (string) $p->foreign_upgrade_price;
        $this->foreignUpgradeAgentPrice = $p->foreign_upgrade_agent_price === null ? '' : (string) $p->foreign_upgrade_agent_price;
        $this->upgradeDirectReferralBonus = (string) $p->upgrade_direct_referral_bonus;
        $this->upgradeIndirectReferralBonus = (string) $p->upgrade_indirect_referral_bonus;
        $this->dailyCallMinutes = (string) $p->daily_call_minutes;
        $this->monthlyShareMinutes = (string) $p->monthly_share_minutes;
        $this->shareTermDays = (string) $p->share_term_days;
        $this->maxDailyShareholderEarning = (string) $p->max_daily_shareholder_earning;
        $this->callIdLimit = (string) $p->call_id_limit;
        $this->tierLevel = (string) $p->tier_level;
        $this->features = implode("\n", $p->features ?? []);
        $this->foreignPrice = $p->foreign_price === null ? '' : (string) $p->foreign_price;
        $this->foreignAgentPrice = $p->foreign_agent_price === null ? '' : (string) $p->foreign_agent_price;
        $this->successMessage = (string) $p->success_message;
        $this->enableCloud = (bool) $p->enable_cloud;
        $this->cloudCalls = (bool) $p->cloud_calls;
        $this->cloudSms = (bool) $p->cloud_sms;
        $this->cloudContactLimit = (string) $p->cloud_contact_limit;
        $this->cloudSmsLimit = (string) $p->cloud_sms_limit;
        $this->cloudDailyEarningCap = (string) $p->cloud_daily_earning_cap;
        $this->cloudHourlyEarningCap = (string) $p->cloud_hourly_earning_cap;
        $this->isFeatured = (bool) $p->is_featured;
        $this->isActive = (bool) $p->is_active;

        $attached = $p->callTypes->keyBy('id');
        $this->callTypeConfig = $this->callTypes()->mapWithKeys(function (CallType $ct) use ($attached) {
            $pivot = $attached->get($ct->id)?->pivot;

            return [$ct->id => [
                'enabled' => $pivot !== null && (bool) $pivot->is_available,
                'rate' => $pivot?->rate_per_minute !== null ? (string) $pivot->rate_per_minute : '',
                'claim' => $pivot?->claimable_percentage !== null ? (string) $pivot->claimable_percentage : '',
            ]];
        })->all();

        $this->showDetail = false;
        $this->resetValidation();
        $this->showModal = true;
    }

    public function save(): void
    {
        $this->authorizeWrite();

        $this->validate([
            'name' => ['required', 'string', 'max:120'],
            'price' => ['required', 'numeric', 'min:0'],
            'agentPrice' => ['required', 'numeric', 'min:0'],
            'directReferralBonus' => ['required', 'numeric', 'min:0'],
            'indirectReferralBonus' => ['required', 'numeric', 'min:0'],
            'upgradePrice' => ['nullable', 'numeric', 'min:0'],
            'upgradeAgentPrice' => ['nullable', 'numeric', 'min:0'],
            'foreignUpgradePrice' => ['nullable', 'numeric', 'min:0'],
            'foreignUpgradeAgentPrice' => ['nullable', 'numeric', 'min:0'],
            'upgradeDirectReferralBonus' => ['nullable', 'numeric', 'min:0'],
            'upgradeIndirectReferralBonus' => ['nullable', 'numeric', 'min:0'],
            'dailyCallMinutes' => ['required', 'numeric', 'min:0'],
            'monthlyShareMinutes' => ['required', 'numeric', 'min:0'],
            'shareTermDays' => ['required', 'integer', 'min:1', 'max:3660'],
            'maxDailyShareholderEarning' => ['required', 'numeric', 'min:0'],
            'callIdLimit' => ['required', 'integer', 'min:0'],
            'tierLevel' => ['required', 'integer', 'min:0'],
            'foreignPrice' => ['nullable', 'numeric', 'min:0'],
            'foreignAgentPrice' => ['nullable', 'numeric', 'min:0'],
            'successMessage' => ['nullable', 'string', 'max:500'],
            'cloudContactLimit' => ['nullable', 'integer', 'min:0'],
            'cloudSmsLimit' => ['nullable', 'integer', 'min:0'],
            'cloudDailyEarningCap' => ['nullable', 'numeric', 'min:0'],
            'cloudHourlyEarningCap' => ['nullable', 'numeric', 'min:0'],
            'callTypeConfig' => ['array'],
            'callTypeConfig.*' => ['array'],
            'callTypeConfig.*.enabled' => ['boolean'],
            'callTypeConfig.*.rate' => ['nullable', 'numeric', 'min:0'],
            'callTypeConfig.*.claim' => ['nullable', 'numeric', 'min:0', 'max:100'],
        ]);

        $submittedMonthlyMinutes = (float) $this->monthlyShareMinutes;
        $monthlyQuota = $submittedMonthlyMinutes;
        $dailyCap = (float) $this->maxDailyShareholderEarning;

        if ($dailyCap > 0) {
            $enabledTypeIds = collect($this->callTypeConfig)
                ->filter(fn (array $config): bool => ! empty($config['enabled']))
                ->keys()
                ->all();
            $shareQuotas = app(ShareQuotaService::class);
            $minimums = CallType::query()
                ->whereIn('id', $enabledTypeIds)
                ->where('is_active', true)
                ->get()
                ->filter(fn (CallType $callType): bool => $shareQuotas->hasValidDurationBounds($callType))
                ->map(function (CallType $callType) use ($shareQuotas, $dailyCap): ?float {
                    $config = $this->callTypeConfig[$callType->id] ?? [];
                    $percentage = ($config['claim'] ?? '') !== ''
                        ? (float) $config['claim']
                        : (float) $callType->claimable_percentage;

                    return $shareQuotas->minimumMonthlyMinutesForDailyCap(
                        $dailyCap,
                        (float) $callType->rate_per_minute,
                        $percentage,
                    );
                })
                ->filter(fn (?float $minimum): bool => $minimum !== null)
                ->values();

            if ($minimums->isEmpty()) {
                throw ValidationException::withMessages([
                    'maxDailyShareholderEarning' => 'A positive daily cap requires at least one active included call type with a positive rate and claimable percentage.',
                ]);
            }

            $monthlyQuota = max($submittedMonthlyMinutes, (float) $minimums->min());
            $this->monthlyShareMinutes = number_format($monthlyQuota, 2, '.', '');
        }

        $attributes = [
            'name' => $this->name,
            'description' => $this->description ?: null,
            'price' => $this->price,
            'agent_price' => $this->agentPrice,
            'direct_referral_bonus' => $this->directReferralBonus,
            'indirect_referral_bonus' => $this->indirectReferralBonus,
            'upgrade_price' => (float) ($this->upgradePrice ?: 0),
            'upgrade_agent_price' => (float) ($this->upgradeAgentPrice ?: 0),
            'foreign_upgrade_price' => $this->foreignUpgradePrice !== '' ? $this->foreignUpgradePrice : null,
            'foreign_upgrade_agent_price' => $this->foreignUpgradeAgentPrice !== '' ? $this->foreignUpgradeAgentPrice : null,
            'upgrade_direct_referral_bonus' => (float) ($this->upgradeDirectReferralBonus ?: 0),
            'upgrade_indirect_referral_bonus' => (float) ($this->upgradeIndirectReferralBonus ?: 0),
            'daily_call_minutes' => $this->dailyCallMinutes,
            'monthly_share_minutes' => $monthlyQuota,
            'share_term_days' => (int) $this->shareTermDays,
            'max_daily_shareholder_earning' => (float) $this->maxDailyShareholderEarning,
            'call_id_limit' => (int) $this->callIdLimit,
            'tier_level' => (int) $this->tierLevel,
            'features' => $this->featureLines(),

            'foreign_price' => $this->foreignPrice !== '' ? $this->foreignPrice : null,
            'foreign_agent_price' => $this->foreignAgentPrice !== '' ? $this->foreignAgentPrice : null,
            'success_message' => $this->successMessage ?: null,
            'enable_cloud' => $this->enableCloud,

            'cloud_calls' => $this->enableCloud && $this->cloudCalls,
            'cloud_sms' => $this->enableCloud && $this->cloudSms,
            'cloud_contact_limit' => (int) ($this->cloudContactLimit ?: 0),
            'cloud_sms_limit' => (int) ($this->cloudSmsLimit ?: 0),
            'cloud_daily_earning_cap' => (float) ($this->cloudDailyEarningCap ?: 0),
            'cloud_hourly_earning_cap' => (float) ($this->cloudHourlyEarningCap ?: 0),
            'is_featured' => $this->isFeatured,
            'is_active' => $this->isActive,
        ];

        if ($this->editingId) {
            $package = Package::query()->findOrFail($this->editingId);
            $package->update($attributes);
        } else {
            $package = Package::query()->create($attributes);
        }

        $package->callTypes()->sync($this->syncPayload());

        $this->showModal = false;
        $message = 'Package saved successfully.';
        if ($monthlyQuota > $submittedMonthlyMinutes + 1e-9) {
            $message .= ' Monthly share minutes were raised to '.number_format($monthlyQuota, 2).' to keep the daily cap reachable.';
        }
        $this->dispatch('toast', message: $message, type: 'success');
    }

    public function toggleActive(string $id): void
    {
        $this->authorizeWrite();
        $p = Package::query()->findOrFail($id);
        $p->update(['is_active' => ! $p->is_active]);
        $this->dispatch('toast', message: $p->is_active ? 'Package enabled successfully' : 'Package disabled successfully', type: 'success');
    }

    public function delete(string $id): void
    {
        $this->authorizeWrite();
        $this->showDetail = false;
        $p = Package::query()->withCount('users')->findOrFail($id);
        if ($p->users_count > 0) {
            $this->dispatch('toast', message: 'Cannot delete — users hold this package. Disable it instead.', type: 'warning');

            return;
        }
        $this->safeDelete(fn () => $p->delete(), 'package');
    }

    protected function syncPayload(): array
    {
        $payload = [];

        foreach ($this->callTypeConfig as $callTypeId => $config) {
            if (empty($config['enabled'])) {
                continue;
            }

            $payload[$callTypeId] = [
                'is_available' => true,
                'rate_per_minute' => ($config['rate'] ?? '') !== '' ? (float) $config['rate'] : null,
                'claimable_percentage' => ($config['claim'] ?? '') !== '' ? (float) $config['claim'] : null,
            ];
        }

        return $payload;
    }

    protected function featureLines(): array
    {
        return collect(explode("\n", $this->features))
            ->map(fn (string $line) => trim($line))
            ->filter()
            ->values()
            ->all();
    }

    protected function callTypes(): Collection
    {
        return CallType::query()->orderBy('name')->get();
    }

    public function render(): View
    {
        return view('livewire.backoffice.packages.list-packages', [
            'packages' => Package::query()->withCount(['users', 'callTypes'])->ordered()->get(),
            'allCallTypes' => $this->callTypes(),
            'detailPackage' => $this->detailId ? Package::query()->withCount('users')->with('callTypes')->find($this->detailId) : null,
        ]);
    }
}
