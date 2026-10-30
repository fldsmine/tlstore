<?php

namespace App\Services\Payments;

use App\Models\PaymentReference;
use App\Models\Transaction;
use App\Services\Payments\DTO\VerifyResult;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * The single-use ledger for settled gateway payments.
 *
 * A payment reference — ours AND the provider's own transaction id — may credit an entitlement
 * exactly once, ever. Settlement (App\Services\Payments\Concerns\SettlesGatewayCharge) claims the
 * reference here, inside its row-locked DB transaction, BEFORE the domain effect runs:
 *
 *   - our reference is unique per charge (Transactions.reference) and unique in the ledger;
 *   - the provider's transaction id is unique per provider (composite unique index), so one real
 *     payment can never be replayed onto a second charge;
 *   - a duplicate claim fails closed: the charge is failed and flagged for manual review, never credited.
 *
 * The claim is deliberately NOT released when the entitlement cannot be granted: the money really
 * was received for that reference, so releasing it would re-open the door to a second credit. Those
 * charges stay flagged for manual reconciliation/refund instead.
 */
class PaymentReferenceLedger
{
    public function isSettled(string $reference): bool
    {
        return PaymentReference::query()->where('reference', $reference)->exists();
    }

    public function findByReference(string $reference): ?PaymentReference
    {
        return PaymentReference::query()->where('reference', $reference)->first();
    }

    public function findByGatewayReference(string $provider, string $gatewayReference): ?PaymentReference
    {
        return PaymentReference::query()
            ->where('provider', $provider)
            ->where('gateway_reference', $gatewayReference)
            ->first();
    }

    /**
     * Claim a settled reference for a charge. Returns the ledger row on success, or null when the
     * reference (or the provider transaction behind it) has already been consumed by another charge.
     */
    /**
     * @param  array<string, mixed>  $extraMeta
     */
    public function claim(Transaction $transaction, VerifyResult $result, float $amountNgn, array $extraMeta = []): ?PaymentReference
    {
        $provider = filled($transaction->provider) ? (string) $transaction->provider : null;
        $gatewayReference = filled($result->gatewayReference) ? (string) $result->gatewayReference : null;

        // Cheap pre-check first so an ordinary replay never touches the unique indexes…
        if ($this->isSettled((string) $transaction->reference)
            || ($provider !== null && $gatewayReference !== null && $this->findByGatewayReference($provider, $gatewayReference) !== null)) {
            return null;
        }

        try {
            // …and the unique indexes win the race: a savepoint keeps the outer settlement
            // transaction alive when the insert is rejected (Postgres aborts a failed statement).
            return DB::transaction(fn (): PaymentReference => PaymentReference::create([
                'reference' => (string) $transaction->reference,
                'transaction_id' => $transaction->id,
                'user_id' => $transaction->user_id,
                'provider' => $provider,
                'gateway_reference' => $gatewayReference,
                'type' => $transaction->type?->value ?? (string) $transaction->type,
                'currency' => strtoupper((string) ($result->currency ?: ($transaction->meta['currency'] ?? ''))),
                'amount_ngn' => round($amountNgn, 2),
                'settled_at' => now(),
                'meta' => array_merge([
                    'verified_amount' => round((float) $result->amount, 2),
                    'gateway_status' => $result->status,
                ], $extraMeta),
            ]));
        } catch (QueryException $e) {
            // Fail closed: if the ledger cannot be claimed (duplicate OR a database problem), the
            // charge must not be credited.
            Log::critical('Payment reference claim rejected', [
                'reference' => $transaction->reference,
                'provider' => $provider,
                'gateway_reference' => $gatewayReference,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Claim a settled reference for a GUEST order (the product pages), where there is no Transactions
     * row and no account at all. Same single-use guarantee as claim(): both OUR reference and the
     * provider's own transaction id are unique, so the same money can never fulfil two orders.
     *
     * Returns the ledger row on success, or null when either identifier is already consumed — the
     * caller must then fail the order and flag it for review instead of delivering the product.
     *
     * @param  array<string, mixed>  $meta
     */
    public function claimForOrder(
        string $reference,
        ?string $provider,
        ?string $gatewayReference,
        string $type,
        float $amountNgn,
        ?string $currency = null,
        array $meta = [],
    ): ?PaymentReference {
        // Cheap pre-check first so an ordinary replay never touches the unique indexes…
        if ($this->isSettled($reference)
            || ($provider !== null && $gatewayReference !== null && $this->findByGatewayReference($provider, $gatewayReference) !== null)) {
            return null;
        }

        try {
            // …and the unique indexes win the race (see claim(): the savepoint keeps the outer
            // settlement transaction alive when the insert is rejected).
            return DB::transaction(fn (): PaymentReference => PaymentReference::create([
                'reference' => $reference,
                'transaction_id' => null, // a guest order has no charge row
                'user_id' => null,        // and no account
                'provider' => $provider,
                'gateway_reference' => $gatewayReference,
                'type' => $type,
                'currency' => strtoupper((string) $currency),
                'amount_ngn' => round($amountNgn, 2),
                'settled_at' => now(),
                'meta' => $meta,
            ]));
        } catch (QueryException $e) {
            Log::critical('Guest order payment reference claim rejected', [
                'reference' => $reference,
                'provider' => $provider,
                'gateway_reference' => $gatewayReference,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }
}
