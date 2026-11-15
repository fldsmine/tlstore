<?php

namespace App\Application\Money;

use App\Models\ExchangeRateSnapshot;
use Illuminate\Validation\ValidationException;

final class CurrencyConverter
{
    /** @var array<string, float|null> */
    private array $rates = [];

    /** @return array{amount_minor:int,rate:float} */
    public function convertMinor(int $amountMinor, string $fromCurrency, string $toCurrency): array
    {
        $from = strtoupper($fromCurrency);
        $to = strtoupper($toCurrency);
        if ($from === $to) {
            return ['amount_minor' => $amountMinor, 'rate' => 1.0];
        }

        $rate = $this->rate($from, $to);
        if ($rate === null || $rate <= 0) {
            throw ValidationException::withMessages([
                'currency' => "A current exchange rate from {$from} to {$to} is unavailable. Please try again later.",
            ]);
        }

        $major = CurrencyMinorUnits::fromMinor($amountMinor, $from) * $rate;
        return [
            'amount_minor' => CurrencyMinorUnits::toMinor($major, $to),
            'rate' => $rate,
        ];
    }

    private function rate(string $from, string $to): ?float
    {
        $key = $from.'>'.$to;
        if (array_key_exists($key, $this->rates)) {
            return $this->rates[$key];
        }

        $direct = $this->latestRate($from, $to);
        if ($direct !== null) {
            return $this->rates[$key] = $direct;
        }

        $inverse = $this->latestRate($to, $from);
        if ($inverse !== null && $inverse > 0) {
            return $this->rates[$key] = 1 / $inverse;
        }

        if ($from !== 'USD' && $to !== 'USD') {
            $fromToUsd = $this->rate($from, 'USD');
            $usdToTarget = $this->rate('USD', $to);
            if ($fromToUsd !== null && $usdToTarget !== null) {
                return $this->rates[$key] = $fromToUsd * $usdToTarget;
            }
        }

        return $this->rates[$key] = null;
    }

    private function latestRate(string $base, string $quote): ?float
    {
        $value = ExchangeRateSnapshot::query()
            ->where('base_currency', $base)
            ->where('quote_currency', $quote)
            ->orderByDesc('fetched_at')
            ->orderByDesc('id')
            ->value('rate');

        return $value !== null && (float) $value > 0 ? (float) $value : null;
    }
}
