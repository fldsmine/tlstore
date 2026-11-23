<?php

namespace App\Services\Payments\DTO;

/**
 * A single payout (disbursement) request. `reference` is OUR unique payout reference and the
 * reconciliation key the Payout Verification API is queried with. Amounts are in the payout
 * currency (major units, 2 decimals).
 *
 * `debitCurrency` is only used by gateways that let a transfer be funded from another wallet
 * (Flutterwave): blank means the payout currency's wallet is debited, like every Korapay payout.
 */
final class PayoutRequest
{
    /**
     * @param  array<string, string>  $metadata
     */
    public function __construct(
        public string $reference,
        public string $destinationType,
        public float $amount,
        public string $currency,
        public string $narration,
        public string $accountNumber,
        public string $bankCode,
        public ?string $bankName = null,
        public ?string $accountName = null,
        public ?string $customerName = null,
        public ?string $customerEmail = null,
        public ?string $debitCurrency = null,
        public array $metadata = [],
    ) {}
}
