<?php

namespace App\Livewire\Dashboard\Withdrawals;

use App\Models\Withdrawal;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('components.layouts.app')]
#[Title('Withdrawal Requested')]
class WithdrawalSuccess extends Component
{
    public string $reference = '';

    public function mount(): void
    {
        $this->reference = (string) request()->query('ref', '');
    }

    public function render(): View
    {
        $withdrawal = Withdrawal::query()
            ->where('user_id', auth()->id())
            ->where('reference', $this->reference)
            ->first();

        if (! $withdrawal) {
            return view('livewire.dashboard.withdrawals.withdrawal-success', ['withdrawal' => null]);
        }

        $amount = (float) $withdrawal->amount;
        $fee = (float) $withdrawal->fee;

        return view('livewire.dashboard.withdrawals.withdrawal-success', [
            'withdrawal' => $withdrawal,
            'amount' => $amount,
            'fee' => $fee,
            'net' => (float) $withdrawal->amount_paid,
        ]);
    }
}
