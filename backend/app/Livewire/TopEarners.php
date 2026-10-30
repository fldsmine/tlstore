<?php

namespace App\Livewire;

use App\Enums\TransactionType;
use App\Enums\UserStatus;
use App\Models\User;
use App\Services\CurrencyService;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('components.layouts.public')]
#[Title('Top Earners')]
class TopEarners extends Component
{
    /**
     * Ranked by lifetime referral-earning history (direct + indirect Connect Bonuses),
     * summed from the ledger so withdrawals don't shrink the standing.
     */
    public function earners(): array
    {
        $fx = app(CurrencyService::class);

        $referralCredits = fn ($query) => $query
            ->where('type', TransactionType::REFERRAL_BONUS->value)
            ->where('direction', 'credit');

        return User::query()
            ->select('id', 'username', 'avatar')
            ->where('status', UserStatus::ACTIVE->value)
            ->excludingSystem()
            ->whereHas('transactions', $referralCredits)
            ->withSum(['transactions as referral_earned' => $referralCredits], 'amount')
            ->orderByDesc('referral_earned')
            ->take(30)
            ->get()
            ->values()
            ->map(fn (User $user, int $index): array => [
                'rank' => $index + 1,
                'name' => $user->username,
                'usd' => number_format((float) $user->referral_earned, 2),
                'ngn' => number_format($fx->toNgn((float) $user->referral_earned), 2),
                'avatar' => $user->avatar,
            ])
            ->all();
    }

    public function render(): View
    {
        return view('livewire.top-earners');
    }
}
