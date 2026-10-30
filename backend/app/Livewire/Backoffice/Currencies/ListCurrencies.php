<?php

namespace App\Livewire\Backoffice\Currencies;

use App\Livewire\Backoffice\Concerns\AuthorizesAdminActions;
use App\Models\Currency;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('components.layouts.backoffice')]
#[Title('Currencies — Backoffice')]
class ListCurrencies extends Component
{
    use AuthorizesAdminActions, WithPagination;

    #[Url]
    public string $search = '';

    public bool $showModal = false;

    public ?string $editingId = null;

    public string $code = '';

    public string $name = '';

    public string $symbol = '';

    public string $usdExchangeRate = '';

    public bool $isActive = true;

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function openCreate(): void
    {
        $this->authorizeWrite();
        $this->reset('editingId', 'code', 'name', 'symbol', 'usdExchangeRate');
        $this->isActive = true;
        $this->resetValidation();
        $this->showModal = true;
    }

    public function edit(string $id): void
    {
        $this->authorizeWrite();
        $currency = Currency::query()->findOrFail($id);
        $this->editingId = $currency->id;
        $this->code = (string) $currency->code;
        $this->name = $currency->name;
        $this->symbol = (string) $currency->symbol;
        $this->usdExchangeRate = (string) $currency->usd_exchange_rate;
        $this->isActive = (bool) $currency->is_active;
        $this->resetValidation();
        $this->showModal = true;
    }

    public function save(): void
    {
        $this->authorizeWrite();

        $this->code = strtoupper(trim($this->code));

        $data = $this->validate([
            'code' => ['required', 'string', 'size:3', Rule::unique('currencies', 'code')->ignore($this->editingId)],
            'name' => ['required', 'string', 'max:120'],
            'symbol' => ['nullable', 'string', 'max:8'],
            'usdExchangeRate' => ['required', 'numeric', 'min:0'],
            'isActive' => ['boolean'],
        ]);

        $attributes = [
            'code' => strtoupper($data['code']),
            'name' => $data['name'],
            'symbol' => $data['symbol'] ?: null,
            'usd_exchange_rate' => $data['usdExchangeRate'],
            'is_active' => $this->isActive,
        ];

        if ($this->editingId) {
            Currency::query()->whereKey($this->editingId)->update($attributes);
        } else {
            Currency::query()->create($attributes);
        }

        $this->showModal = false;
        $this->dispatch('toast', message: 'Currency saved successfully', type: 'success');
    }

    public function toggleActive(string $id): void
    {
        $this->authorizeWrite();
        $currency = Currency::query()->findOrFail($id);
        $currency->update(['is_active' => ! $currency->is_active]);
        $this->dispatch('toast', message: $currency->is_active ? 'Currency enabled successfully' : 'Currency disabled successfully', type: 'success');
    }

    public function delete(string $id): void
    {
        $this->authorizeWrite();
        $currency = Currency::query()->findOrFail($id);

        if ($currency->countries()->exists()) {
            $this->dispatch('toast', message: 'Cannot delete a currency that is assigned to a country.', type: 'error');

            return;
        }

        $this->safeDelete(fn () => $currency->delete(), 'currency');
    }

    public function render(): View
    {
        return view('livewire.backoffice.currencies.list-currencies', [
            'currencies' => Currency::query()
                ->withCount('countries')
                ->when(trim($this->search) !== '', fn (Builder $q) => $q->where('name', 'like', '%'.trim($this->search).'%')->orWhere('code', 'like', '%'.trim($this->search).'%'))
                ->orderBy('code')
                ->paginate(30),
        ]);
    }
}
