<?php

namespace App\Livewire\Backoffice\Cloud;

use App\Livewire\Backoffice\Concerns\AuthorizesAdminActions;
use App\Models\CloudStoragePlan;
use App\Models\Package;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('components.layouts.backoffice')]
#[Title('Cloud Storage Plans — Backoffice')]
class ListCloudStoragePlans extends Component
{
    use AuthorizesAdminActions, WithPagination;

    #[Url]
    public string $search = '';

    public bool $showModal = false;

    public ?string $editingId = null;

    public string $name = '';

    public string $description = '';

    public string $tokenPrice = '';

    public string $agentPrice = '';

    public string $foreignTokenPrice = '';

    public string $foreignAgentPrice = '';

    public string $renewalTokenPrice = '';

    public string $renewalAgentPrice = '';

    public string $foreignRenewalTokenPrice = '';

    public string $foreignRenewalAgentPrice = '';

    public string $extraContacts = '';

    public string $extraSms = '';

    public string $referralBonus = '';

    public string $durationDays = '';

    public string $sortOrder = '';

    public string $tierLevel = '';

    public bool $isActive = true;

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    /** Tier levels available on packages — a storage plan tagged with one becomes a special
     *  (coupon-only) plan: vendors mint it as a storage-activation code instead of selling it. */
    protected function tierLevelOptions(): array
    {
        return Package::query()->active()->ordered()->get(['tier_level', 'name'])
            ->mapWithKeys(fn (Package $p): array => [(string) $p->tier_level => 'Tier '.$p->tier_level.' — '.$p->name])
            ->all();
    }

    public function openCreate(): void
    {
        $this->authorizeWrite();
        $this->reset('editingId', 'name', 'description', 'tokenPrice', 'agentPrice', 'foreignTokenPrice', 'foreignAgentPrice', 'renewalTokenPrice', 'renewalAgentPrice', 'foreignRenewalTokenPrice', 'foreignRenewalAgentPrice', 'extraContacts', 'extraSms', 'referralBonus', 'durationDays', 'sortOrder', 'tierLevel');
        $this->isActive = true;
        $this->resetValidation();
        $this->showModal = true;
    }

    public function edit(string $id): void
    {
        $this->authorizeWrite();
        $plan = CloudStoragePlan::query()->findOrFail($id);
        $this->editingId = $plan->id;
        $this->name = $plan->name;
        $this->description = (string) $plan->description;
        $this->tokenPrice = (string) $plan->token_price;
        $this->agentPrice = (string) $plan->agent_price;
        $this->foreignTokenPrice = (string) $plan->foreign_token_price;
        $this->foreignAgentPrice = (string) $plan->foreign_agent_price;
        $this->renewalTokenPrice = (string) $plan->renewal_token_price;
        $this->renewalAgentPrice = (string) $plan->renewal_agent_price;
        $this->foreignRenewalTokenPrice = (string) $plan->foreign_renewal_token_price;
        $this->foreignRenewalAgentPrice = (string) $plan->foreign_renewal_agent_price;
        $this->extraContacts = (string) $plan->extra_contacts;
        $this->extraSms = (string) $plan->extra_sms;
        $this->referralBonus = (string) $plan->referral_bonus;
        $this->durationDays = (string) $plan->duration_days;
        $this->sortOrder = (string) $plan->sort_order;
        $this->tierLevel = $plan->tier_level !== null ? (string) $plan->tier_level : '';
        $this->isActive = (bool) $plan->is_active;
        $this->resetValidation();
        $this->showModal = true;
    }

    public function save(): void
    {
        $this->authorizeWrite();

        $tierOptions = $this->tierLevelOptions();

        $data = $this->validate([
            'name' => ['required', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:255'],
            'tokenPrice' => ['required', 'numeric', 'min:0'],
            'agentPrice' => ['required', 'numeric', 'min:0'],
            'foreignTokenPrice' => ['nullable', 'numeric', 'min:0'],
            'foreignAgentPrice' => ['nullable', 'numeric', 'min:0'],
            'renewalTokenPrice' => ['nullable', 'numeric', 'min:0'],
            'renewalAgentPrice' => ['nullable', 'numeric', 'min:0'],
            'foreignRenewalTokenPrice' => ['nullable', 'numeric', 'min:0'],
            'foreignRenewalAgentPrice' => ['nullable', 'numeric', 'min:0'],
            'extraContacts' => ['required', 'integer', 'min:0'],
            'extraSms' => ['required', 'integer', 'min:0'],
            'referralBonus' => ['required', 'numeric', 'min:0'],
            'durationDays' => ['required', 'integer', 'min:0'],
            'sortOrder' => ['required', 'integer', 'min:0'],
            'tierLevel' => ['nullable', 'integer', 'min:1', Rule::in(array_keys($tierOptions))],
            'isActive' => ['boolean'],
        ]);

        // A lifetime plan (no duration) never expires, so it can't carry a renewal price.
        if ((int) $data['durationDays'] === 0 && (float) ($data['renewalTokenPrice'] ?: 0) > 0) {
            throw ValidationException::withMessages([
                'renewalTokenPrice' => 'Lifetime plans (0 duration) can\'t be renewable — clear the renewal price or set a duration.',
            ]);
        }

        $attributes = [
            'name' => $data['name'],
            'description' => $data['description'] ?: null,
            'token_price' => $data['tokenPrice'],
            'agent_price' => $data['agentPrice'],
            'foreign_token_price' => $data['foreignTokenPrice'] !== '' ? $data['foreignTokenPrice'] : null,
            'foreign_agent_price' => $data['foreignAgentPrice'] !== '' ? $data['foreignAgentPrice'] : null,
            'renewal_token_price' => $data['renewalTokenPrice'] !== '' ? $data['renewalTokenPrice'] : 0,
            'renewal_agent_price' => $data['renewalAgentPrice'] !== '' ? $data['renewalAgentPrice'] : 0,
            'foreign_renewal_token_price' => $data['foreignRenewalTokenPrice'] !== '' ? $data['foreignRenewalTokenPrice'] : null,
            'foreign_renewal_agent_price' => $data['foreignRenewalAgentPrice'] !== '' ? $data['foreignRenewalAgentPrice'] : null,
            'extra_contacts' => $data['extraContacts'],
            'extra_sms' => $data['extraSms'],
            'referral_bonus' => $data['referralBonus'],
            'duration_days' => $data['durationDays'],
            'sort_order' => $data['sortOrder'],
            'tier_level' => $data['tierLevel'] !== '' && $data['tierLevel'] !== null ? (int) $data['tierLevel'] : null,
            'is_active' => $this->isActive,
        ];

        $plan = $this->editingId ? CloudStoragePlan::query()->findOrFail($this->editingId) : new CloudStoragePlan;
        $plan->fill($attributes)->save();

        $this->showModal = false;
        $this->dispatch('toast', message: 'Cloud storage plan saved successfully', type: 'success');
    }

    public function toggleActive(string $id): void
    {
        $this->authorizeWrite();
        $plan = CloudStoragePlan::query()->findOrFail($id);
        $plan->update(['is_active' => ! $plan->is_active]);
        $this->dispatch('toast', message: $plan->is_active ? 'Cloud storage plan enabled successfully' : 'Cloud storage plan disabled successfully', type: 'success');
    }

    public function delete(string $id): void
    {
        $this->authorizeWrite();
        $this->safeDelete(fn () => CloudStoragePlan::query()->whereKey($id)->delete(), 'cloud storage plan');
    }

    public function render(): View
    {
        return view('livewire.backoffice.cloud.list-cloud-storage-plans', [
            'plans' => CloudStoragePlan::query()
                ->when(trim($this->search) !== '', fn (Builder $q) => $q->where('name', 'like', '%'.trim($this->search).'%'))
                ->orderBy('sort_order')
                ->orderBy('name')
                ->paginate(20),
            'tierOptions' => $this->tierLevelOptions(),
        ]);
    }
}
