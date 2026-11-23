<?php

namespace App\Services;

use App\Models\Country;
use Illuminate\Support\Collection;

class CurrencyService
{
    private ?Collection $countries = null;

    private function countries(): Collection
    {
        return $this->countries ??= Country::query()->with('currency')->get()->keyBy(fn (Country $c): string => strtoupper((string) $c->code));
    }

    public function resolve(string|Country|null $country): ?Country
    {
        if ($country instanceof Country) {
            return $country;
        }

        return ($country === null || $country === '') ? null : $this->countries()->get(strtoupper($country));
    }

    public function rate(string|Country|null $country): float
    {
        return (float) ($this->resolve($country)?->currency?->usd_exchange_rate ?: 0);
    }

    public function symbol(string|Country|null $country): string
    {
        $c = $this->resolve($country);

        return $c?->currency?->symbol ?: ($c?->currency_code ?? '$');
    }

    public function code(string|Country|null $country): string
    {
        return $this->resolve($country)?->currency_code ?? 'USD';
    }

    public function toLocal(float $usd, string|Country|null $country, int $decimals = 2): float
    {
        $rate = $this->rate($country);

        return round($rate > 0 ? $usd * $rate : $usd, $decimals);
    }

    public function toUsd(float $local, string|Country|null $country): float
    {
        $rate = $this->rate($country);

        return round($rate > 0 ? $local / $rate : $local, 6);
    }

    public function toNgn(float $usd): float
    {
        return $this->toLocal($usd, 'NG');
    }

    public function formatUsd(float $usd): string
    {
        return '$'.number_format($usd, 2);
    }

    public function formatLocal(float $usd, string|Country|null $country): string
    {
        return $this->symbol($country).number_format($this->toLocal($usd, $country), 2);
    }
}
