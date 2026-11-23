<?php

namespace App\Services\Payments\DTO;

/**
 * Outcome of a payout initiation or payout verification.
 *
 * `ok` means the gateway gave a definitive answer — NOT that the money was delivered. `status` is
 * one of PayoutStatus values (`processing`/`pending`/`success`/`failed`), and only `success` means
 * the transfer completed. When `ok` is false (`unknown`) the payout may still have been accepted by
 * the provider, so callers must re-verify before treating it as failed or re-sending it.
 *
 * `traceId` (Korapay's trace) and `gatewayId` (Flutterwave's transfer id) are both "the provider's
 * own handle for this payout", kept separate because they are looked up differently on verify.
 */
final class PayoutResult
{
    public const STATUS_PROCESSING = 'processing';

    public const STATUS_PENDING = 'pending';

    public const STATUS_SUCCESS = 'success';

    public const STATUS_FAILED = 'failed';

    /** No definitive answer (transport error / unexpected HTTP status) — re-verify, never assume failed. */
    public const STATUS_UNKNOWN = 'unknown';

    /**
     * @param  array<string, mixed>  $data
     */
    public function __construct(
        public bool $ok,
        public string $status = self::STATUS_UNKNOWN,
        public float $amount = 0.0,
        public float $fee = 0.0,
        public string $currency = '',
        public string $reference = '',
        public ?string $traceId = null,
        public ?string $gatewayId = null,
        public ?string $message = null,
        public array $data = [],
    ) {}

    public function completed(): bool
    {
        return $this->ok && $this->status === self::STATUS_SUCCESS;
    }

    public function failed(): bool
    {
        return $this->ok && $this->status === self::STATUS_FAILED;
    }

    public function inFlight(): bool
    {
        return $this->ok && in_array($this->status, [self::STATUS_PROCESSING, self::STATUS_PENDING], true);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function unknown(string $message, array $data = []): self
    {
        return new self(false, self::STATUS_UNKNOWN, message: $message, data: $data);
    }
}
