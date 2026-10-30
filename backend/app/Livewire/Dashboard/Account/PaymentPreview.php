<?php

namespace App\Livewire\Dashboard\Account;

use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('components.layouts.app')]
#[Title('Payment Preview')]
class PaymentPreview extends Component
{
    public function render(): View
    {
        return view('livewire.dashboard.account.payment-preview');
    }
}
