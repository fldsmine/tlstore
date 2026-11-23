<?php

namespace App\Livewire\Backoffice\Countries;

use App\Livewire\Backoffice\Concerns\AuthorizesAdminActions;
use App\Models\Country;
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
#[Title('Countries — Backoffice')]
class ListCountries extends Component
{
    use AuthorizesAdminActions, WithPagination;

    #[Url]
    public string $search = '';

    public bool $showModal = false;

    public ?string $editingId = null;

    public string $name = '';

    public string $code = '';

    public string $currencyCode = '';

    public string $flag = '';

    public bool $isActive = true;

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function openCreate(): void
    {
        $this->authorizeWrite();
        $this->reset('editingId', 'name', 'code', 'currencyCode', 'flag');
        $this->isActive = true;
        $this->resetValidation();
        $this->showModal = true;
    }

    public function edit(string $id): void
    {
        $this->authorizeWrite();
        $country = Country::query()->findOrFail($id);
        $this->editingId = $country->id;
        $this->name = $country->name;
        $this->code = (string) $country->code;
        $this->currencyCode = (string) $country->currency_code;
        $this->flag = (string) $country->flag;
        $this->isActive = (bool) $country->is_active;
        $this->resetValidation();
        $this->showModal = true;
    }

    public function save(): void
    {
        $this->authorizeWrite();

        $this->code = strtoupper(trim($this->code));
        $this->currencyCode = strtoupper(trim($this->currencyCode));

        $data = $this->validate([
            'name' => ['required', 'string', 'max:120'],
            'code' => ['required', 'string', 'size:2'],
            'currencyCode' => ['required', 'string', 'size:3', Rule::exists('currencies', 'code')],
            'flag' => ['nullable', 'string', 'max:16'],
            'isActive' => ['boolean'],
        ], [
            'currencyCode.exists' => 'Pick a currency that exists. Add it under Currencies first.',
        ]);

        $attributes = [
            'name' => $data['name'],
            'code' => strtoupper($data['code']),
            'currency_code' => strtoupper($data['currencyCode']),
            'flag' => $data['flag'] ?: null,
            'is_active' => $this->isActive,
        ];

        if ($this->editingId) {
            Country::query()->whereKey($this->editingId)->update($attributes);
        } else {
            Country::query()->create($attributes);
        }

        $this->showModal = false;
        $this->dispatch('toast', message: 'Country saved successfully', type: 'success');
    }

    public function toggleActive(string $id): void
    {
        $this->authorizeWrite();
        $country = Country::query()->findOrFail($id);
        $country->update(['is_active' => ! $country->is_active]);
        $this->dispatch('toast', message: $country->is_active ? 'Country enabled successfully' : 'Country disabled successfully', type: 'success');
    }

    public function delete(string $id): void
    {
        $this->authorizeWrite();
        $this->safeDelete(fn () => Country::query()->whereKey($id)->delete(), 'country');
    }

    public function render(): View
    {
        return view('livewire.backoffice.countries.list-countries', [
            'countries' => Country::query()
                ->with('currency')
                ->when(trim($this->search) !== '', fn (Builder $q) => $q->where('name', 'like', '%'.trim($this->search).'%')->orWhere('currency_code', 'like', '%'.trim($this->search).'%')->orWhere('code', 'like', '%'.trim($this->search).'%'))
                ->orderBy('name')
                ->paginate(30),

            'currencies' => Currency::query()
                ->where('is_active', true)
                ->orWhere('code', $this->currencyCode)
                ->orderBy('code')
                ->get(['code', 'name', 'symbol']),
        ]);
    }
}
