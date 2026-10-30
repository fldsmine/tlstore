<?php

namespace App\Services\Vas;

use App\Contracts\BillPaymentProvider;
use App\Services\Vas\Providers\FlutterwaveBillsProvider;
use App\Services\Vas\Providers\VtpassProvider;
use InvalidArgumentException;

class BillProviderManager
{
    /** @var array<string, class-string<BillPaymentProvider>> */
    private const DRIVERS = [
        'vtpass' => VtpassProvider::class,
        'flutterwave' => FlutterwaveBillsProvider::class,
    ];

    /** Resolve a SPECIFIC named driver (used to settle against an order's pinned provider). Throws on unknown. */
    public function driver(string $name): BillPaymentProvider
    {
        $class = self::DRIVERS[$name] ?? null;

        if ($class === null) {
            throw new InvalidArgumentException("Unknown bill payment provider [{$name}].");
        }

        return app($class);
    }

    /** The admin-configured provider — used ONLY when placing a new order. */
    public function active(): BillPaymentProvider
    {
        return $this->driver((string) config('settings.vtu.provider', 'vtpass'));
    }

    /** @return array<int, string> */
    public function names(): array
    {
        return array_keys(self::DRIVERS);
    }
}
