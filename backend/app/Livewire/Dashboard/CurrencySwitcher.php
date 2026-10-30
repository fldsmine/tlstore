<?php

namespace App\Livewire\Dashboard;

use App\Support\DisplayCurrency;
use Illuminate\Contracts\View\View;
use Livewire\Component;

class CurrencySwitcher extends Component
{
    public function switchTo(string $code): void
    {
        $code = strtoupper(trim($code));
        $local = strtoupper(DisplayCurrency::localCode());

        if ($code !== 'USD' && $code !== $local) {
            return;
        }

        auth()->user()->forceFill(['display_currency' => $code === 'USD' ? null : $code])->save();

        $this->js('window.location.reload()');
    }

    public function render(): View
    {
        return view('livewire.dashboard.currency-switcher', [
            'active' => DisplayCurrency::code(),
            'localCode' => DisplayCurrency::localCode(),
            'hasLocal' => DisplayCurrency::hasLocal(),
        ]);
    }
}
