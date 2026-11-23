<?php

namespace App\Services\Vas\DTO;

use App\Enums\VtuStatus;

/**
 * The normalised outcome of a pay/requery. `reached` = the provider gave a definitive answer;
 * when false the order is left pending for the requery sweep to retry (never refunded on a mere
 * transport failure). `token` carries prepaid electricity tokens / exam pins; `raw` is stored on
 * the order for audit.
 */
final class BillFulfilmentResult
{
    /**
     * @param  array<string, mixed>  $raw
     */
    public function __construct(
        public bool $reached,
        public VtuStatus $status,
        public ?string $externalId = null,
        public ?string $token = null,
        public array $raw = [],
        public string $message = '',
    ) {}

    /** Could not reach the provider — leave the order pending and retry later. */
    public static function unreachable(string $message = ''): self
    {
        return new self(false, VtuStatus::PENDING, message: $message);
    }
}
