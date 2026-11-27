<?php

namespace App\Application\Support;

use App\Models\SystemSetting;

final class PaymentGatewaySettings
{
    private const LEGACY_DEFAULTS = [
        'korapay' => ['NGN', 'GHS', 'KES', 'UGX', 'USD'],
        'paystack' => ['NGN', 'GHS', 'ZAR', 'USD'],
        'flutterwave' => ['NGN', 'USD', 'GHS', 'KES', 'UGX', 'ZAR', 'GBP', 'EUR'],
        'bachs' => ['USD', 'NGN', 'GHS', 'KES', 'ZAR'],
        'fincra' => ['NGN', 'GHS', 'KES', 'UGX', 'TZS', 'ZMW', 'USD', 'XAF', 'XOF'],
        'manual' => [],
    ];

    /** @return array<string, list<string>> */
    public static function all(): array
    {
        $configuredOptions = SystemSetting::query()
            ->where('setting_key', 'payment.active_payment_currency')
            ->first();
        if ($configuredOptions) {
            $normalized = [];
            foreach (self::options() as $option) {
                $normalized[$option['gateway']] ??= [];
                $normalized[$option['gateway']][] = $option['currency'];
            }
            foreach ($normalized as $gateway => $currencies) {
                $normalized[$gateway] = array_values(array_unique($currencies));
            }
            if (self::manualEnabled()) {
                $normalized['manual'] = [];
            }

            return $normalized;
        }

        $value = SystemSetting::query()
            ->where('setting_key', 'payment.active_gateways')
            ->first()?->value ?? [];

        if (! is_array($value)) {
            return [];
        }

        // Older deployments saved active gateways as a simple list of names.
        if (array_is_list($value)) {
            $normalized = [];
            foreach ($value as $gateway) {
                if (is_string($gateway) && isset(self::LEGACY_DEFAULTS[$gateway])) {
                    $normalized[$gateway] = self::LEGACY_DEFAULTS[$gateway];
                }
            }

            return $normalized;
        }

        $normalized = [];
        foreach (self::LEGACY_DEFAULTS as $gateway => $fallback) {
            if (! array_key_exists($gateway, $value)) {
                continue;
            }

            $currencies = $value[$gateway];
            if (! is_array($currencies)) {
                continue;
            }

            $normalized[$gateway] = array_values(array_unique(array_filter(
                array_map(static fn ($currency): string => strtoupper(trim((string) $currency)), $currencies),
                static fn (string $currency): bool => preg_match('/^[A-Z]{3}$/', $currency) === 1,
            )));
        }

        return $normalized;
    }

    /** @return list<array{option_key:string,gateway:string,currency:string,label:string}> */
    public static function options(): array
    {
        $value = SystemSetting::query()
            ->where('setting_key', 'payment.active_payment_currency')
            ->first()?->value;
        if (! is_array($value)) {
            return [];
        }

        $options = [];
        foreach ($value as $key => $label) {
            if (! is_string($key) || ! is_string($label) || trim($label) === '') {
                continue;
            }
            if (preg_match('/^(korapay|korapy|paystack|flutterwave|bachs|fincra)\.([A-Za-z]{3})$/i', $key, $matches) !== 1) {
                continue;
            }
            $gateway = strtolower($matches[1]) === 'korapy' ? 'korapay' : strtolower($matches[1]);
            if (! in_array($gateway, config('trustlens.implemented_payment_gateways', []), true)) {
                continue;
            }
            $options[] = [
                'option_key' => $gateway.'.'.strtoupper($matches[2]),
                'gateway' => $gateway,
                'currency' => strtoupper($matches[2]),
                'label' => trim($label),
            ];
        }

        return $options;
    }

    public static function supports(string $gateway, string $currency): bool
    {
        return in_array(strtoupper($currency), self::all()[strtolower($gateway)] ?? [], true);
    }

    private static function manualEnabled(): bool
    {
        $value = SystemSetting::query()->where('setting_key', 'payment.active_gateways')->first()?->value;
        if (! is_array($value)) return false;
        if (array_is_list($value)) return in_array('manual', $value, true);

        return array_key_exists('manual', $value);
    }
}
