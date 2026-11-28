<?php

namespace App\Application\Payments;

use App\Application\Money\CurrencyMinorUnits;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;

/**
 * Shared Fincra plumbing.
 *
 * Fincra authenticates with an `api-key` header (the secret key from the
 * portal) plus an optional `x-pub-key`/`x-business-id`, not a bearer token, so
 * the checkout, verification, balance and payout callers cannot simply use
 * `Http::withToken()`. Keeping the header construction and amount conversion
 * here stops those four code paths from drifting apart.
 */
final class FincraApi
{
    /** @param array<string, mixed> $config */
    public static function client(array $config): PendingRequest
    {
        return Http::acceptJson()
            ->timeout(15)
            ->withHeaders(array_filter([
                'api-key' => (string) ($config['secret_key'] ?? ''),
                'x-pub-key' => (string) ($config['public_key'] ?? ''),
                'x-business-id' => (string) ($config['business_id'] ?? ''),
            ], static fn (string $value): bool => $value !== ''));
    }

    /** @param array<string, mixed> $config */
    public static function baseUrl(array $config): string
    {
        return rtrim((string) ($config['base_url'] ?? 'https://api.fincra.com'), '/');
    }

    /**
     * Fincra charges in major units (e.g. 1500 means NGN 1,500.00), not in
     * kobo/pesewas like Paystack. Whole amounts stay integers because the
     * checkout reference documents `amount` as an integer.
     */
    public static function amount(int $amountMinor, string $currency): int|float
    {
        $major = CurrencyMinorUnits::fromMinor($amountMinor, $currency);

        return floor($major) === $major ? (int) $major : $major;
    }
}
