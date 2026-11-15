<?php

namespace App\Application\Support;

use App\Application\Money\CurrencyMinorUnits;
use App\Application\Money\CurrencyConverter;
use App\Models\PackageAddon;
use Illuminate\Validation\ValidationException;

final class PackageAddonPriceQuote
{
    public function __construct(private readonly CurrencyConverter $currencyConverter) {}

    public function amountMinor(PackageAddon $addon, string $currency): int
    {
        return $this->quote($addon, $currency)['amount_minor'];
    }

    /** @return array{amount_minor:int,rate:float} */
    public function quote(PackageAddon $addon, string $currency): array
    {
        $currency = strtoupper($currency);
        $discount = min(max((float) $addon->discount, 0), 100);
        $payableMinor = (int) round($addon->price_minor * (100 - $discount) / 100);

        return $this->currencyConverter->convertMinor($payableMinor, $addon->currency, $currency);
    }

    public function optionsFor(PackageAddon $addon): array
    {
        $options = [];
        foreach (PaymentGatewaySettings::options() as $option) {
            try {
                $options[] = $option + ['amount_minor' => $this->amountMinor($addon, $option['currency']), 'currency_minor_units' => CurrencyMinorUnits::precision($option['currency'])];
            } catch (ValidationException) {}
        }
        return $options;
    }
}
