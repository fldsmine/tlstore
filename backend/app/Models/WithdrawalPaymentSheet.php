<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class WithdrawalPaymentSheet extends Model
{
    use HasUuids;

    protected function casts(): array
    {
        return [
            'total_amount' => 'decimal:2',
            'currency' => 'string',
            'withdrawal_ids' => 'array',
        ];
    }

    public static function getNextSequenceNumber(): int
    {
        return (int) self::query()->max('sequence_number') + 1;
    }

    public function generateFileName(string $format = 'csv', ?string $processor = null): string
    {
        $app = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', (string) config('app.name')) ?: 'NEXTEL');
        $processor = strtoupper($processor ?? $this->processor);
        $currency = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', (string) ($this->currency ?: 'NGN')));
        $sequence = sprintf('%03d', $this->sequence_number);

        return "{$app}_{$currency}_WITHDRAWALS_{$processor}_{$sequence}.{$format}";
    }

    public function withdrawals(): Collection
    {

        return Withdrawal::query()
            ->whereIn('id', $this->withdrawal_ids ?? [])
            ->with('user:id,country')
            ->get();
    }

    public static function breakdown(Collection $withdrawals): array
    {
        $totals = $withdrawals
            ->groupBy(fn (Withdrawal $w): string => (string) ($w->local_currency ?: 'NGN'))
            ->map(fn ($group): float => (float) $group->sum(fn (Withdrawal $w): float => (float) ($w->local_amount_paid ?? $w->amount_paid)))
            ->all();

        ksort($totals);

        return $totals;
    }

    public function currencyTotals(): array
    {
        return static::breakdown($this->withdrawals());
    }

    public function formattedTotals(): string
    {
        $totals = $this->currencyTotals();

        if ($totals === []) {
            return ($this->currency ?: 'NGN').' '.number_format((float) $this->total_amount, 2);
        }

        return collect($totals)
            ->map(fn (float $amount, string $currency): string => $currency.' '.number_format($amount, 2))
            ->implode(' · ');
    }
}
