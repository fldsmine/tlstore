<?php

namespace App\Livewire\Dashboard\Calls;

use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('components.layouts.app')]
#[Title('Call Preview')]
class CallPreview extends Component
{
    public ?string $number = null;

    public function mount(): void
    {
        $this->number = request()->query('n');
    }

    public function render(): View
    {
        $user = auth()->user();

        $beneficiary = $user->allocatedNumbers()->active()
            ->when($this->number, fn ($q) => $q->whereKey($this->number))
            ->first()
            ?? $user->allocatedNumbers()->active()->first();

        return view('livewire.dashboard.calls.call-preview', [
            'beneficiary' => $beneficiary,
            'rate' => $beneficiary ? $beneficiary->ratePerMinute($user->package) : 0.0,
        ]);
    }
}
