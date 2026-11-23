<?php

namespace App\Livewire\Backoffice\MinutePlans;

use App\Livewire\Backoffice\Concerns\AuthorizesAdminActions;
use App\Models\AdditionalMinutePlan;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('components.layouts.backoffice')]
#[Title('Minute Plans — Backoffice')]
class ListMinutePlans extends Component
{
    use AuthorizesAdminActions;

    public bool $showModal = false;

    public ?string $editingId = null;

    public string $name = '';

    public string $description = '';

    public string $price = '';

    public string $agentPrice = '';

    public string $dailyCallMinutes = '';

    public string $shareMinutes = '';

    public string $durationDays = '';

    public string $paylaterDurationDays = '';

    public string $minEarningRate = '';

    public string $callIdBonus = '';

    public string $referralBonus = '';

    public string $features = '';

    public string $foreignPrice = '';

    public string $foreignAgentPrice = '';

    public bool $isFeatured = false;

    public bool $isActive = true;

    public bool $showDetail = false;

    public ?string $detailId = null;

    public function openCreate(): void
    {
        $this->authorizeWrite();
        $this->reset('editingId', 'name', 'description', 'price', 'agentPrice', 'dailyCallMinutes', 'shareMinutes', 'durationDays', 'paylaterDurationDays', 'minEarningRate', 'callIdBonus', 'referralBonus', 'features', 'foreignPrice', 'foreignAgentPrice', 'isFeatured');
        $this->isActive = true;
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
        $p = AdditionalMinutePlan::query()->findOrFail($id);
        $this->editingId = $p->id;
        $this->name = (string) $p->name;
        $this->description = (string) $p->description;
        $this->price = (string) $p->price;
        $this->agentPrice = (string) $p->agent_price;
        $this->dailyCallMinutes = (string) $p->daily_call_minutes;
        $this->shareMinutes = (string) $p->share_minutes;
        $this->durationDays = (string) $p->duration_days;
        $this->paylaterDurationDays = (string) $p->paylater_duration_days;
        $this->minEarningRate = (string) $p->min_earning_rate;
        $this->callIdBonus = (string) $p->call_id_bonus;
        $this->referralBonus = (string) $p->referral_bonus;
        $this->features = implode("\n", $p->features ?? []);
        $this->foreignPrice = $p->foreign_price === null ? '' : (string) $p->foreign_price;
        $this->foreignAgentPrice = $p->foreign_agent_price === null ? '' : (string) $p->foreign_agent_price;
        $this->isFeatured = (bool) $p->is_featured;
        $this->isActive = (bool) $p->is_active;
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
            'dailyCallMinutes' => ['required', 'numeric', 'min:0'],
            'shareMinutes' => ['required', 'numeric', 'min:0'],
            'durationDays' => ['required', 'integer', 'min:0'],
            'paylaterDurationDays' => ['required', 'integer', 'min:0'],
            'minEarningRate' => ['required', 'numeric', 'min:0'],
            'callIdBonus' => ['required', 'integer', 'min:0'],
            'referralBonus' => ['required', 'numeric', 'min:0'],
            'foreignPrice' => ['nullable', 'numeric', 'min:0'],
            'foreignAgentPrice' => ['nullable', 'numeric', 'min:0'],
        ]);

        if (((float) $this->dailyCallMinutes > 0 || (float) $this->shareMinutes > 0) && (int) $this->durationDays < 1) {
            $this->addError('durationDays', 'A minute top-up must have a duration of at least one day.');

            return;
        }

        $attributes = [
            'name' => $this->name,
            'description' => $this->description ?: null,
            'price' => $this->price,
            'agent_price' => $this->agentPrice,
            'daily_call_minutes' => $this->dailyCallMinutes,
            'share_minutes' => $this->shareMinutes,
            'duration_days' => (int) $this->durationDays,
            'paylater_duration_days' => (int) $this->paylaterDurationDays,
            'min_earning_rate' => $this->minEarningRate,
            'call_id_bonus' => (int) $this->callIdBonus,
            'referral_bonus' => $this->referralBonus,
            'features' => collect(explode("\n", $this->features))->map(fn ($l) => trim($l))->filter()->values()->all(),

            'foreign_price' => $this->foreignPrice !== '' ? $this->foreignPrice : null,
            'foreign_agent_price' => $this->foreignAgentPrice !== '' ? $this->foreignAgentPrice : null,
            'is_featured' => $this->isFeatured,
            'is_active' => $this->isActive,
        ];

        if ($this->editingId) {
            AdditionalMinutePlan::query()->whereKey($this->editingId)->update($attributes);
        } else {
            AdditionalMinutePlan::query()->create($attributes);
        }

        $this->showModal = false;
        $this->dispatch('toast', message: 'Plan saved successfully', type: 'success');
    }

    public function toggleActive(string $id): void
    {
        $this->authorizeWrite();
        $p = AdditionalMinutePlan::query()->findOrFail($id);
        $p->update(['is_active' => ! $p->is_active]);
        $this->dispatch('toast', message: $p->is_active ? 'Plan enabled successfully' : 'Plan disabled successfully', type: 'success');
    }

    public function delete(string $id): void
    {
        $this->authorizeWrite();
        $this->showDetail = false;
        $this->safeDelete(fn () => AdditionalMinutePlan::query()->whereKey($id)->delete(), 'plan');
    }

    public function render(): View
    {
        return view('livewire.backoffice.minute-plans.list-minute-plans', [
            'plans' => AdditionalMinutePlan::query()->ordered()->get(),
            'detailPlan' => $this->detailId ? AdditionalMinutePlan::query()->find($this->detailId) : null,
        ]);
    }
}
