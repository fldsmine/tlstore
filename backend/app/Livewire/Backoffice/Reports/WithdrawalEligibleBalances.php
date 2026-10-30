<?php

namespace App\Livewire\Backoffice\Reports;

use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('components.layouts.backoffice')]
#[Title('Withdrawal-eligible balances — Backoffice')]
class WithdrawalEligibleBalances extends Component
{
    use WithPagination;

    #[Url]
    public string $wallet = 'shareholder';

    #[Url]
    public string $search = '';

    public function selectWallet(string $wallet): void
    {
        if (in_array($wallet, User::SPENDABLE_WALLETS, true)) {
            $this->wallet = $wallet;
            $this->resetPage();
        }
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function render(): View
    {
        abort_unless(auth('admin')->check(), 403);

        $walletKey = in_array($this->wallet, User::SPENDABLE_WALLETS, true)
            ? $this->wallet
            : User::SPENDABLE_WALLETS[0];

        $selects = [];
        $bindings = [];
        $walletSummaries = [];

        foreach (User::SPENDABLE_WALLETS as $key) {
            $column = User::WALLET_COLUMNS[$key];
            $minimum = (float) config("settings.withdrawal.wallets.{$key}.min", 0);
            $selects[] = "sum(case when {$column} >= ? then 1 else 0 end) as cnt_{$key}";
            $selects[] = "sum(case when {$column} >= ? then {$column} else 0 end) as sum_{$key}";
            $bindings[] = $minimum;
            $bindings[] = $minimum;
        }

        // Keep the same predicate as the Withdrawal-eligible balances cards on the main report.
        // This single pass computes the six unfiltered totals; search only filters the table rows.
        $aggregate = User::query()->selectRaw(implode(', ', $selects), $bindings)->first();

        foreach (User::SPENDABLE_WALLETS as $key) {
            $walletSummaries[] = [
                'key' => $key,
                'column' => User::WALLET_COLUMNS[$key],
                'label' => User::WALLET_LABELS[$key],
                'min' => (float) config("settings.withdrawal.wallets.{$key}.min", 0),
                'enabled' => (bool) config("settings.withdrawal.wallets.{$key}.enabled", true),
                'count' => (int) ($aggregate->{"cnt_{$key}"} ?? 0),
                'total' => (float) ($aggregate->{"sum_{$key}"} ?? 0),
            ];
        }

        $selectedWallet = collect($walletSummaries)->firstWhere('key', $walletKey);
        $search = trim($this->search);

        $users = User::query()
            ->where($selectedWallet['column'], '>=', $selectedWallet['min'])
            ->when($search !== '', function (Builder $query) use ($search): void {
                $term = '%'.$search.'%';
                $query->where(function (Builder $match) use ($term): void {
                    $match->where('username', 'like', $term)
                        ->orWhere('first_name', 'like', $term)
                        ->orWhere('last_name', 'like', $term)
                        ->orWhere('email', 'like', $term)
                        ->orWhere('phone', 'like', $term);
                });
            })
            ->orderByDesc($selectedWallet['column'])
            ->orderBy('username')
            ->paginate(50)
            ->withQueryString();

        return view('livewire.backoffice.reports.withdrawal-eligible-balances', [
            'walletSummaries' => $walletSummaries,
            'selectedWallet' => $selectedWallet,
            'users' => $users,
        ]);
    }
}
