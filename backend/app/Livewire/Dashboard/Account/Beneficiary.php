<?php

namespace App\Livewire\Dashboard\Account;

use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('components.layouts.app')]
#[Title('Beneficiary')]
class Beneficiary extends Component
{
    public function render(): View
    {
        return view('livewire.dashboard.account.beneficiary', [
            'numbers' => auth()->user()->allocatedNumbers()->active()->latest()->get(),
        ]);
    }
}
