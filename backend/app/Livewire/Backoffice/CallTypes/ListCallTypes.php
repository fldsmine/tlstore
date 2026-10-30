<?php

namespace App\Livewire\Backoffice\CallTypes;

use App\Livewire\Backoffice\Concerns\AuthorizesAdminActions;
use App\Models\CallType;
use App\Models\Package;
use App\Services\ShareQuotaService;
use Illuminate\Contracts\View\View;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('components.layouts.backoffice')]
#[Title('Call Types — Backoffice')]
class ListCallTypes extends Component
{
    use AuthorizesAdminActions;

    public bool $showModal = false;

    public ?string $editingId = null;

    public string $editingName = '';

    public string $color = '';

    public string $description = '';

    public string $ratePerMinute = '';

    public string $claimablePercentage = '';

    public string $sharePromptMinutes = '90';

    public string $price = '0';

    public string $agentPrice = '0';

    public string $foreignPrice = '';

    public string $foreignAgentPrice = '';

    public string $region = 'nigeria';

    public string $monthlyShareMinutes = '0';

    public string $shareTermDays = '30';

    public string $maxDailyShareholderEarning = '0';

    public string $minMinutes = '';

    public string $maxMinutes = '';

    public string $minPerBatch = '';

    public string $maxPerBatch = '';

    public bool $isActive = true;

    public bool $showDetail = false;

    public ?string $detailId = null;

    public function openDetail(string $id): void
    {
        $this->detailId = $id;
        $this->showDetail = true;
    }

    public function edit(string $id): void
    {
        $this->authorizeWrite();
        $ct = CallType::query()->findOrFail($id);
        $this->editingId = $ct->id;
        $this->editingName = (string) $ct->name;
        $this->color = (string) ($ct->color ?: '#18443E');
        $this->description = (string) $ct->description;
        $this->showDetail = false;
        $this->ratePerMinute = (string) $ct->rate_per_minute;
        $this->claimablePercentage = (string) $ct->claimable_percentage;
        $this->sharePromptMinutes = (string) $ct->share_purchase_prompt_minutes;
        $this->price = (string) $ct->price;
        $this->agentPrice = (string) $ct->agent_price;
        $this->foreignPrice = $ct->foreign_price === null ? '' : (string) $ct->foreign_price;
        $this->foreignAgentPrice = $ct->foreign_agent_price === null ? '' : (string) $ct->foreign_agent_price;
        $this->region = (string) ($ct->region ?: 'nigeria');
        $this->monthlyShareMinutes = (string) $ct->monthly_share_minutes;
        $this->shareTermDays = (string) $ct->share_term_days;
        $this->maxDailyShareholderEarning = (string) $ct->max_daily_shareholder_earning;
        $this->minMinutes = (string) $ct->min_minutes;
        $this->maxMinutes = (string) $ct->max_minutes;
        $this->minPerBatch = (string) $ct->min_per_batch;
        $this->maxPerBatch = (string) $ct->max_per_batch;
        $this->isActive = (bool) $ct->is_active;
        $this->resetValidation();
        $this->showModal = true;
    }

    public function save(): void
    {
        $this->authorizeWrite();

        $this->validate([
            'editingName' => ['required', 'string', 'max:120'],
            'color' => ['nullable', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'description' => ['nullable', 'string', 'max:255'],
            'ratePerMinute' => ['required', 'numeric', 'min:0'],
            'claimablePercentage' => ['required', 'numeric', 'min:0', 'max:100'],
            'sharePromptMinutes' => ['required', 'numeric', 'min:0'],
            'price' => ['required', 'numeric', 'min:0'],
            'agentPrice' => ['required', 'numeric', 'min:0'],
            'foreignPrice' => ['nullable', 'numeric', 'min:0'],
            'foreignAgentPrice' => ['nullable', 'numeric', 'min:0'],
            'region' => ['required', 'in:nigeria,foreign'],
            'monthlyShareMinutes' => ['required', 'numeric', 'min:0'],
            'shareTermDays' => ['required', 'integer', 'min:1', 'max:3650'],
            'maxDailyShareholderEarning' => ['required', 'numeric', 'min:0'],
            'minMinutes' => ['required', 'numeric', 'min:0'],
            'maxMinutes' => ['required', 'numeric', 'min:0'],
            'minPerBatch' => ['required', 'integer', 'min:0'],
            'maxPerBatch' => ['required', 'integer', 'min:0'],
        ], [
            'color.regex' => 'Pick a valid hex colour.',
        ]);

        $minimumDurationUnits = max(1, (int) ceil((float) $this->minMinutes * 100 - 1e-9));
        $maximumDurationUnits = (int) floor((float) $this->maxMinutes * 100 + 1e-9);
        if ((float) $this->minMinutes > (float) $this->maxMinutes
            || $maximumDurationUnits < $minimumDurationUnits) {
            throw ValidationException::withMessages([
                'maxMinutes' => 'Maximum call duration must include at least one valid 0.01-minute duration at or above the minimum.',
            ]);
        }

        $shareQuotas = app(ShareQuotaService::class);
        $minimumMonthlyMinutes = $shareQuotas->minimumMonthlyMinutesForDailyCap(
            (float) $this->maxDailyShareholderEarning,
            (float) $this->ratePerMinute,
            (float) $this->claimablePercentage,
        );

        if ($minimumMonthlyMinutes === null) {
            throw ValidationException::withMessages([
                'maxDailyShareholderEarning' => 'A positive daily cap requires a positive call rate and claimable percentage.',
            ]);
        }

        $submittedMonthlyMinutes = (float) $this->monthlyShareMinutes;
        $monthlyQuota = max($submittedMonthlyMinutes, $minimumMonthlyMinutes);
        $this->monthlyShareMinutes = number_format($monthlyQuota, 2, '.', '');

        CallType::query()->whereKey($this->editingId)->update([
            'name' => $this->editingName,
            'color' => $this->color ?: null,
            'description' => $this->description ?: null,
            'rate_per_minute' => $this->ratePerMinute,
            'claimable_percentage' => $this->claimablePercentage,
            'share_purchase_prompt_minutes' => $this->sharePromptMinutes,
            'price' => $this->price,
            'agent_price' => $this->agentPrice,
            'foreign_price' => $this->foreignPrice !== '' ? $this->foreignPrice : null,
            'foreign_agent_price' => $this->foreignAgentPrice !== '' ? $this->foreignAgentPrice : null,
            'region' => $this->region,
            'monthly_share_minutes' => $monthlyQuota,
            'share_term_days' => (int) $this->shareTermDays,
            'max_daily_shareholder_earning' => $this->maxDailyShareholderEarning,
            'min_minutes' => $this->minMinutes,
            'max_minutes' => $this->maxMinutes,
            'min_per_batch' => (int) $this->minPerBatch,
            'max_per_batch' => (int) $this->maxPerBatch,
            'is_active' => $this->isActive,
        ]);

        $adjustedPackages = 0;
        $affectedPackages = Package::query()
            ->whereHas('callTypes', fn ($query) => $query->where('call_types.id', $this->editingId))
            ->with('callTypes')
            ->get();

        foreach ($affectedPackages as $package) {
            $packageMinimum = $shareQuotas->minimumMonthlyMinutesForPackage($package);
            if ($packageMinimum === null || (float) $package->monthly_share_minutes + 1e-9 >= $packageMinimum) {
                continue;
            }

            $package->update(['monthly_share_minutes' => $packageMinimum]);
            $adjustedPackages++;
        }

        $this->showModal = false;
        $message = 'Call type updated successfully.';
        if ($monthlyQuota > $submittedMonthlyMinutes + 1e-9) {
            $message .= ' Its monthly share quota was raised to '.number_format($monthlyQuota, 2).' minutes to support the daily cap.';
        }
        if ($adjustedPackages > 0) {
            $message .= ' '.$adjustedPackages.' package quota'.($adjustedPackages === 1 ? ' was' : 's were').' also raised to keep their caps reachable.';
        }

        $this->dispatch('toast', message: $message, type: 'success');
    }

    public function render(): View
    {
        return view('livewire.backoffice.call-types.list-call-types', [
            'callTypes' => CallType::query()->withCount('packages')->orderBy('name')->get(),
            'detailCallType' => $this->detailId ? CallType::query()->withCount('packages')->find($this->detailId) : null,
        ]);
    }
}
