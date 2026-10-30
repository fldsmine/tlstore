<?php

namespace App\Livewire\Backoffice\Banks;

use App\Livewire\Backoffice\Concerns\AuthorizesAdminActions;
use App\Models\Bank;
use App\Models\Country;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('components.layouts.backoffice')]
#[Title('Banks — Backoffice')]
class ListBanks extends Component
{
    use AuthorizesAdminActions, WithPagination;

    #[Url]
    public string $search = '';

    #[Url]
    public string $countryFilter = 'NG';

    public bool $showModal = false;

    public ?string $editingId = null;

    public string $name = '';

    public string $code = '';

    public string $institutionCode = '';

    public string $tag = '';

    public string $country = 'NG';

    public string $type = 'commercial';

    public bool $isActive = true;

    public bool $showDetail = false;

    public ?string $detailId = null;

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedCountryFilter(): void
    {
        $this->resetPage();
    }

    public function openDetail(string $id): void
    {
        $this->detailId = Bank::query()->findOrFail($id)->id;
        $this->showDetail = true;
    }

    public function openCreate(): void
    {
        $this->authorizeWrite();
        $this->reset('editingId', 'name', 'code', 'institutionCode', 'tag', 'country', 'type');
        $this->country = 'NG';
        $this->type = 'commercial';
        $this->isActive = true;
        $this->resetValidation();
        $this->showModal = true;
    }

    public function edit(string $id): void
    {
        $this->authorizeWrite();
        $bank = Bank::query()->findOrFail($id);
        $this->editingId = $bank->id;
        $this->name = $bank->name;
        $this->code = (string) $bank->code;
        $this->institutionCode = (string) $bank->institution_code;
        $this->tag = (string) $bank->tag;
        $this->country = (string) $bank->country;
        $this->type = (string) $bank->type;
        $this->isActive = (bool) $bank->is_active;
        $this->showDetail = false;
        $this->resetValidation();
        $this->showModal = true;
    }

    public function save(): void
    {
        $this->authorizeWrite();

        $data = $this->validate([
            'name' => ['required', 'string', 'max:120'],
            'code' => ['required', 'string', 'max:20', Rule::unique('banks', 'code')->ignore($this->editingId)],
            'institutionCode' => ['nullable', 'string', 'max:20', 'regex:/^\d+$/'],
            'tag' => ['nullable', 'string', 'max:60'],
            'country' => ['required', 'string', 'size:2'],
            'type' => ['required', 'string', 'max:40'],
            'isActive' => ['boolean'],
        ], [
            'institutionCode.regex' => 'The institution code may only contain digits.',
        ]);

        $attributes = [
            'name' => $data['name'],
            'code' => $data['code'],
            'institution_code' => $data['institutionCode'] ?: null,
            'tag' => $data['tag'] ?: null,
            'country' => strtoupper($data['country']),
            'type' => $data['type'],
            'is_active' => $this->isActive,
        ];

        if ($this->editingId) {
            Bank::query()->whereKey($this->editingId)->update($attributes);
        } else {
            Bank::query()->create($attributes);
        }

        $this->showModal = false;
        $this->dispatch('toast', message: 'Bank saved successfully', type: 'success');
    }

    public function toggleActive(string $id): void
    {
        $this->authorizeWrite();
        $bank = Bank::query()->findOrFail($id);
        $bank->update(['is_active' => ! $bank->is_active]);
        $this->dispatch('toast', message: $bank->is_active ? 'Bank enabled successfully' : 'Bank disabled successfully', type: 'success');
    }

    public function delete(string $id): void
    {
        $this->authorizeWrite();
        $this->showDetail = false;
        $this->safeDelete(fn () => Bank::query()->whereKey($id)->delete(), 'bank');
    }

    public function render(): View
    {
        return view('livewire.backoffice.banks.list-banks', [
            'banks' => Bank::query()
                ->when(trim($this->search) !== '', fn (Builder $q) => $q->where(fn (Builder $sub) => $sub
                    ->where('name', 'like', '%'.trim($this->search).'%')
                    ->orWhere('code', 'like', '%'.trim($this->search).'%')
                    ->orWhere('institution_code', 'like', '%'.trim($this->search).'%')))
                ->when(trim($this->countryFilter) !== '', fn (Builder $q) => $q->where('country', $this->countryFilter))
                ->orderBy('country')
                ->orderBy('name')
                ->paginate(20),
            'detailBank' => $this->detailId ? Bank::query()->find($this->detailId) : null,
            'countries' => Bank::query()->select('country')->distinct()->orderBy('country')->pluck('country'),
            'countryNames' => Country::query()->pluck('name', 'code')->all(),
            'types' => ['commercial', 'digital', 'microfinance', 'payment_service', 'development', 'merchant', 'mobile_money'],
        ]);
    }
}
