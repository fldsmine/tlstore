<?php

namespace App\Services\Payments\DTO;

/** The outcome of starting a charge: a checkout URL to redirect to, or a failure message. */
final class InitiateResult
{
    public function __construct(
        public bool $ok,
        public ?string $checkoutUrl = null,
        public ?string $gatewayReference = null,
        public ?string $message = null,
    ) {}

    public static function success(string $checkoutUrl, ?string $gatewayReference = null): self
    {
        return new self(true, $checkoutUrl, $gatewayReference);
    }

    public static function failure(string $message): self
    {
        return new self(false, null, null, $message);
    }
}
