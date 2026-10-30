<?php

namespace App\Livewire\Backoffice\Reports;

use App\Models\User;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Standalone "users by balance range" widget. Kept as its own Livewire component so
 * changing the wallet/step re-runs only this one query — not the entire Reports page.
 */
class BalanceDistribution extends Component
{
    /** Selectable range-bucket widths (USD). */
    public const DIST_STEPS = [10, 50, 100, 500, 1000, 5000, 10000, 50000, 100000];

    #[Url]
    public string $distWallet = 'shareholder';

    #[Url]
    public int $distStep = 1000;

    /**
     * @return array<string, string>
     */
    protected function walletOptions(): array
    {
        return User::WALLET_LABELS;
    }

    public function render(): View
    {
        $wallet = array_key_exists($this->distWallet, $this->walletOptions()) ? $this->distWallet : 'shareholder';
        $step = in_array($this->distStep, self::DIST_STEPS, true) ? $this->distStep : 1000;
        $column = User::WALLET_COLUMNS[$wallet];

        $cap = 50; // cap discrete rows; balances above collapse into one top "≥" row
        $counts = User::query()->excludingSystem()
            ->selectRaw("cast(floor({$column} / {$step}) as signed) as bucket, count(*) as c")
            ->groupBy('bucket')
            ->pluck('c', 'bucket');

        $maxBucket = (int) ($counts->keys()->map(fn ($b): int => (int) $b)->max() ?? 0);
        $overflow = (int) $counts->filter(fn ($c, $b): bool => (int) $b > $cap)->sum();

        $rows = [];
        if ($overflow > 0) {
            $rows[] = ['label' => '≥ '.number_format(($cap + 1) * $step), 'count' => $overflow, 'over' => true];
        }
        for ($b = min($maxBucket, $cap); $b >= 0; $b--) {
            $lo = $b * $step;
            $rows[] = ['label' => number_format($lo).' – '.number_format($lo + $step - 1), 'count' => (int) ($counts[$b] ?? 0)];
        }
        $peak = max(1, ...array_map(fn (array $r): int => $r['count'], $rows));

        return view('livewire.backoffice.reports.balance-distribution', [
            'distribution' => array_map(fn (array $r): array => $r + ['pct' => (int) round($r['count'] / $peak * 100)], $rows),
            'walletOptions' => $this->walletOptions(),
            'stepOptions' => self::DIST_STEPS,
        ]);
    }
}
