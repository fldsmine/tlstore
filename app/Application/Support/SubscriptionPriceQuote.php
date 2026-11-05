<?php

namespace App\Application\Support;

use App\Application\Money\CurrencyMinorUnits;
use App\Models\ExchangeRateSnapshot;
use App\Models\SubscriptionPlan;
use Illuminate\Validation\ValidationException;

final class SubscriptionPriceQuote
{
    /** @var array<string, float>|null */
    private ?array $usdRates = null;

    public function amountMinor(SubscriptionPlan $plan, string $currency): int
    {
        $currency = strtoupper($currency);
        $listedMajor = CurrencyMinorUnits::fromMinor($plan->price_minor, $plan->currency);
        $discount = min(max((float) $plan->discount, 0), 100);
        $payableMajor = $listedMajor * (100 - $discount) / 100;

        if ($currency === strtoupper($plan->currency)) {
            return CurrencyMinorUnits::toMinor($payableMajor, $currency);
        }

        $rates = $this->usdRates();
        $sourceRate = $rates[strtoupper($plan->currency)] ?? null;
        $targetRate = $rates[$currency] ?? null;
        if ($sourceRate === null || $targetRate === null || $sourceRate <= 0 || $targetRate <= 0) {
            throw ValidationException::withMessages(['currency' => 'A current exchange rate is not available for this package currency.']);
        }

        return CurrencyMinorUnits::toMinor(($payableMajor / $sourceRate) * $targetRate, $currency);
    }

    /** @return list<array{option_key:string,gateway:string,currency:string,label:string,amount_minor:int,currency_minor_units:int}> */
    public function optionsFor(SubscriptionPlan $plan, ?array $configuredOptions = null): array
    {
        $options = [];
        foreach ($configuredOptions ?? PaymentGatewaySettings::options() as $option) {
            try {
                $options[] = $option + [
                    'amount_minor' => $this->amountMinor($plan, $option['currency']),
                    'currency_minor_units' => CurrencyMinorUnits::precision($option['currency']),
                ];
            } catch (ValidationException) {
                // Do not offer a gateway/currency choice that cannot be quoted safely.
            }
        }

        return $options;
    }

    /** @return array<string, float> */
    private function usdRates(): array
    {
        if ($this->usdRates !== null) {
            return $this->usdRates;
        }

        $rates = ['USD' => 1.0];
        foreach (ExchangeRateSnapshot::query()
            ->where('base_currency', 'USD')
            ->orderByDesc('fetched_at')
            ->orderByDesc('id')
            ->get(['quote_currency', 'rate']) as $snapshot) {
            $currency = strtoupper($snapshot->quote_currency);
            if (! array_key_exists($currency, $rates) && (float) $snapshot->rate > 0) {
                $rates[$currency] = (float) $snapshot->rate;
            }
        }

        return $this->usdRates = $rates;
    }
}
