<?php

namespace App\Livewire\Dashboard\Vas;

use App\Services\Vas\VtuCatalog;
use App\Support\DisplayCurrency;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('components.layouts.app')]
#[Title('Value Added Services')]
class Services extends Component
{
    public function categories(): array
    {
        return collect(VtuCatalog::categories())
            ->map(fn (array $c, string $key): array => [
                'key' => $key,
                'label' => $c['label'],
                'icon' => $c['icon'],
                'blurb' => $c['blurb'],
            ])
            ->values()
            ->all();
    }

    public function render(): View
    {
        return view('livewire.dashboard.vas.services', [
            'categories' => $this->categories(),
            'enabled' => (bool) config('settings.vtu.enabled', false),
            'billsBalance' => (float) auth()->user()->bills_balance,
            'entrySymbol' => DisplayCurrency::symbol(),
            'entryCode' => DisplayCurrency::code(),
        ]);
    }
}
