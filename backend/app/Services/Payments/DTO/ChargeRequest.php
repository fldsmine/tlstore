<?php

namespace App\Services\Payments\DTO;

/**
 * A request to start a hosted-checkout charge. `amount`/`currency` are in the gateway's charge
 * currency (major units, e.g. 2500.00 NGN). `reference` is OUR reference and the settlement key.
 */
final class ChargeRequest
{
    /**
     * @param  array<string, mixed>  $metadata
     */
    public function __construct(
        public string $reference,
        public float $amount,
        public string $currency,
        public string $email,
        public string $customerName,
        public string $narration,
        public string $callbackUrl,
        public ?string $notificationUrl = null,
        public array $metadata = [],
    ) {}
}
