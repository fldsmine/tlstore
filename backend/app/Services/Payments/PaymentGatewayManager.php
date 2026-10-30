<?php

namespace App\Services\Payments;

use App\Contracts\PaymentGateway;
use App\Enums\ChargePurpose;
use App\Services\Payments\Gateways\BachsGateway;
use App\Services\Payments\Gateways\FlutterwaveGateway;
use App\Services\Payments\Gateways\KorapayGateway;
use App\Services\Payments\Gateways\ManualBankGateway;
use App\Services\Payments\Gateways\PaystackGateway;
use InvalidArgumentException;

class PaymentGatewayManager
{
    /** @var array<string, class-string<PaymentGateway>> */
    private const DRIVERS = [
        'korapay' => KorapayGateway::class,
        'paystack' => PaystackGateway::class,
        'flutterwave' => FlutterwaveGateway::class,
        'bachs' => BachsGateway::class,
        'manual' => ManualBankGateway::class,
    ];

    /** Resolve a SPECIFIC named driver. Throws on an unknown name — never silently defaults. */
    public function driver(string $name): PaymentGateway
    {
        $class = self::DRIVERS[$name] ?? null;

        if ($class === null) {
            throw new InvalidArgumentException("Unknown payment provider [{$name}].");
        }

        return app($class);
    }

    /** Resolve the admin-configured provider for a purpose — used ONLY when starting a charge. */
    public function for(ChargePurpose $purpose): PaymentGateway
    {
        return $this->driver((string) config("settings.payment.{$purpose->settingKey()}", 'korapay'));
    }

    /**
     * True when the configured provider for a purpose is usable right now (it has its keys, and the
     * setting names a driver we know). UI uses this to hide pay buttons that could not work; the
     * services re-check it before starting any charge.
     */
    public function availableFor(ChargePurpose $purpose): bool
    {
        try {
            return $this->for($purpose)->configured();
        } catch (InvalidArgumentException) {
            return false;
        }
    }
}
