<?php

namespace App\Services\Payments\DTO;

/**
 * A parsed webhook. `reference` is OUR reference (the settlement key); `status` is advisory only —
 * settlement always re-verifies server-side before crediting, never trusting the webhook body.
 */
final class WebhookEvent
{
    public function __construct(
        public string $reference,
        public string $status,
        public ?string $gatewayReference = null,
    ) {}
}
