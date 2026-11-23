<?php

namespace App\Services\Payments\DTO;

/**
 * The result of a server-side verify. `ok` = the verify call itself reached a definitive answer;
 * when false the caller leaves the charge pending for the requery sweep to retry. `amount`/
 * `currency` are the AMOUNT ACTUALLY CHARGED (major units) — reconciled against meta.amount_ngn
 * before any credit. `status` is the resolved payment status.
 */
final class VerifyResult
{
    public const STATUS_SUCCESS = 'success';

    public const STATUS_FAILED = 'failed';

    public const STATUS_PENDING = 'pending';

    public function __construct(
        public bool $ok,
        public string $status,
        public float $amount = 0.0,
        public string $currency = '',
        public string $reference = '',
        public ?string $gatewayReference = null,
    ) {}

    /** Transport/HTTP failure — no definitive answer; keep the charge pending. */
    public static function unavailable(): self
    {
        return new self(false, self::STATUS_PENDING);
    }

    public function succeeded(): bool
    {
        return $this->ok && $this->status === self::STATUS_SUCCESS;
    }

    public function failed(): bool
    {
        return $this->ok && $this->status === self::STATUS_FAILED;
    }
}
