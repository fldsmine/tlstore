<?php

namespace App\Services\Payments\Concerns;

use App\Enums\PaymentProvider;
use App\Enums\TransactionType;
use App\Models\PaymentResolutionLog;
use App\Models\Transaction;
use App\Services\Payments\DTO\VerifyResult;
use App\Services\Payments\PaymentReferenceLedger;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Shared settlement for gateway-collected charges. The consuming service provides its transaction
 * type and domain effect; provider pinning, server-side verification, row-locked idempotency,
 * amount/currency/reference reconciliation, the single-use payment ledger, and the manual-review
 * audit all use this same path.
 */
trait SettlesGatewayCharge
{
    abstract protected function transactionType(): TransactionType;

    /** Apply the domain effect of a completed charge (credit a wallet / extend a subscription). */
    abstract protected function applyCompletion(Transaction $transaction): void;

    /** Normal callback/webhook/sweep path: only pending rows may settle. */
    public function finalize(string $reference): string
    {
        $transaction = $this->chargeForReference($reference);

        if ($transaction === null) {
            return 'failed';
        }

        if ($transaction->status !== 'pending') {
            return $transaction->status;
        }

        $result = $this->verifyFromPinnedProvider($transaction);

        if (! $result->ok) {
            return 'unavailable'; // no definitive answer: keep the charge pending
        }

        if ($result->status === VerifyResult::STATUS_PENDING) {
            return 'pending';
        }

        return $this->settleVerified($transaction, $result, allowFailed: false);
    }

    /**
     * Backoffice provider recheck. It can recover a previously failed row only when the pinned
     * provider now authoritatively reports success, and it can never bypass the usual reconciliation
     * or single-use ledger.
     *
     * @param  array<string, mixed>  $resolution  Admin/audit context supplied by PaymentResolutionService.
     */
    public function reverifyForBackoffice(string $reference, array $resolution): string
    {
        $transaction = $this->chargeForReference($reference);

        if ($transaction === null) {
            return 'failed';
        }

        if (! in_array($transaction->status, ['pending', 'failed'], true)) {
            return $transaction->status;
        }

        if ($this->ledger()->isSettled($reference)) {
            $this->writeResolution($transaction, $resolution, $transaction->status, $transaction->status, [
                'outcome' => 'already_settled',
            ]);

            return $transaction->status;
        }

        $before = $transaction->status;
        $result = $this->verifyFromPinnedProvider($transaction);
        $context = $this->withVerificationResult($resolution, $result);

        if (! $result->ok) {
            $this->writeResolution($transaction, $context, $before, $transaction->status, ['outcome' => 'unavailable']);

            return 'unavailable';
        }

        if ($result->status === VerifyResult::STATUS_PENDING) {
            $this->writeResolution($transaction, $context, $before, $transaction->status, ['outcome' => 'pending']);

            return 'pending';
        }

        if ($result->failed() && $before === 'failed') {
            $this->writeResolution($transaction, $context, $before, $transaction->status, ['outcome' => 'gateway_reported_failed']);

            return 'failed';
        }

        return $this->settleVerified($transaction, $result, allowFailed: true, resolution: $context);
    }

    /**
     * Backoffice-confirmed payment. The admin's claim is represented as a successful VerifyResult,
     * then passed through the identical reconciliation, duplicate-payment and entitlement guards.
     *
     * @param  array<string, mixed>  $resolution
     */
    public function finalizeManually(string $reference, VerifyResult $result, array $resolution): string
    {
        $transaction = $this->chargeForReference($reference);

        if ($transaction === null) {
            return 'failed';
        }

        if (! in_array($transaction->status, ['pending', 'failed'], true)) {
            return $transaction->status;
        }

        if ($this->ledger()->isSettled($reference)) {
            $this->writeResolution($transaction, $resolution, $transaction->status, $transaction->status, [
                'outcome' => 'already_settled',
            ]);

            return $transaction->status;
        }

        if (! $result->succeeded()) {
            $this->writeResolution($transaction, $resolution, $transaction->status, $transaction->status, [
                'outcome' => 'invalid_manual_verification',
            ]);

            return $transaction->status;
        }

        return $this->settleVerified($transaction, $result, allowFailed: true, resolution: $resolution);
    }

    private function chargeForReference(string $reference): ?Transaction
    {
        return Transaction::query()
            ->where('reference', $reference)
            ->where('type', $this->transactionType()->value)
            ->first();
    }

    private function verifyFromPinnedProvider(Transaction $transaction): VerifyResult
    {
        $provider = (string) $transaction->provider;

        if (! in_array($provider, PaymentProvider::values(), true)) {
            Log::critical('Charge has no resolvable provider', [
                'reference' => $transaction->reference,
                'provider' => $provider,
            ]);

            return VerifyResult::unavailable();
        }

        try {
            // Always use the provider that originated the charge, not the current admin setting.
            return $this->gateways->driver($provider)->verify((string) $transaction->reference);
        } catch (Throwable $e) {
            Log::warning('Payment verification could not be completed', [
                'reference' => $transaction->reference,
                'provider' => $provider,
                'message' => $e->getMessage(),
            ]);

            return VerifyResult::unavailable();
        }
    }

    private function settleVerified(
        Transaction $transaction,
        VerifyResult $result,
        bool $allowFailed,
        ?array $resolution = null,
    ): string {
        return DB::transaction(function () use ($transaction, $result, $allowFailed, $resolution): string {
            $locked = Transaction::query()->whereKey($transaction->id)->lockForUpdate()->first();

            if ($locked === null) {
                return 'failed';
            }

            if ($locked->status === 'completed') {
                $this->writeResolution($locked, $resolution, 'completed', 'completed', ['outcome' => 'already_completed']);

                return 'completed';
            }

            if ($locked->status !== 'pending' && ! ($allowFailed && $locked->status === 'failed')) {
                return $locked->status;
            }

            $fromStatus = $locked->status;

            if ($result->failed()) {
                if ($locked->status === 'pending') {
                    $locked->update(['status' => 'failed']);
                }

                $this->writeResolution($locked, $resolution, $fromStatus, $locked->status, [
                    'outcome' => 'gateway_reported_failed',
                    'verified_reference' => $result->reference,
                    'verified_currency' => $result->currency,
                    'verified_amount' => round($result->amount, 2),
                ]);

                return 'failed';
            }

            if (! $result->succeeded()) {
                return 'pending';
            }

            // Mandatory reconciliation BEFORE crediting: the provider must confirm OUR reference,
            // OUR currency, and at least the expected charge. A provider fee added on top is fine.
            $expectedNgn = (float) ($locked->meta['amount_ngn'] ?? 0);
            $expectedCurrency = strtoupper((string) ($locked->meta['currency'] ?? ''));
            $reconcileError = null;

            if ($result->reference !== $locked->reference
                || strtoupper($result->currency) !== $expectedCurrency
                || $result->amount < $expectedNgn - 0.01) {
                $reconcileError = [
                    'reference' => $result->reference,
                    'currency' => $result->currency,
                    'amount' => round($result->amount, 2),
                ];

                $locked->update([
                    'status' => 'failed',
                    'meta' => array_merge((array) $locked->meta, ['reconcile_error' => $reconcileError]),
                ]);

                Log::critical('Payment reconciliation mismatch', ['reference' => $locked->reference]);
                $this->writeResolution($locked, $resolution, $fromStatus, 'failed', [
                    'outcome' => 'reconciliation_mismatch',
                    'reconcile_error' => $reconcileError,
                ]);

                return 'failed';
            }

            // Claim our reference and the provider's payment id before granting any entitlement.
            $claim = $this->ledger()->claim(
                $locked,
                $result,
                $expectedNgn,
                $this->ledgerResolutionMeta($resolution),
            );

            if ($claim === null) {
                $locked->update([
                    'status' => 'failed',
                    'meta' => array_merge((array) $locked->meta, ['manual_review' => [
                        'reason' => 'duplicate_reference',
                        'error' => 'This payment reference has already been settled by another charge.',
                        'flagged_at' => now()->toIso8601String(),
                    ]]),
                ]);

                Log::critical('Duplicate payment reference — charge failed without crediting', [
                    'reference' => $locked->reference,
                    'type' => $locked->type?->value,
                    'provider' => $locked->provider,
                    'gateway_reference' => $result->gatewayReference,
                ]);

                $this->writeResolution($locked, $resolution, $fromStatus, 'failed', ['outcome' => 'duplicate_reference']);

                return 'failed';
            }

            // Re-check eligibility at settlement. If the domain refuses, retain the ledger claim so
            // this real payment cannot be credited a second time on a later retry.
            try {
                $this->applyCompletion($locked);
            } catch (Throwable $e) {
                $locked->update([
                    'status' => 'failed',
                    'meta' => array_merge((array) $locked->meta, ['manual_review' => [
                        'reason' => 'entitlement_not_granted',
                        'error' => $e->getMessage(),
                        'flagged_at' => now()->toIso8601String(),
                    ]]),
                ]);

                Log::critical('Charge settled but its entitlement could not be granted — flagged for manual reconciliation', [
                    'reference' => $locked->reference,
                    'type' => $locked->type?->value,
                    'error' => $e->getMessage(),
                ]);

                $this->writeResolution($locked, $resolution, $fromStatus, 'failed', [
                    'outcome' => 'entitlement_not_granted',
                    'error' => $e->getMessage(),
                ]);

                return 'failed';
            }

            $meta = (array) $locked->meta;
            if ($resolution !== null) {
                $meta['payment_resolution'] = $this->resolutionSnapshot($resolution);
            }

            $locked->update([
                'status' => 'completed',
                'gateway_reference' => $result->gatewayReference ?? $locked->gateway_reference,
                'meta' => $meta,
            ]);

            $this->writeResolution($locked, $resolution, $fromStatus, 'completed', ['outcome' => 'completed']);

            return 'completed';
        });
    }

    /** @return array<string, mixed> */
    private function withVerificationResult(array $resolution, VerifyResult $result): array
    {
        return array_merge($resolution, [
            'amount' => $result->ok ? round($result->amount, 2) : null,
            'currency' => $result->ok ? strtoupper($result->currency) : null,
            'gateway_reference' => $result->gatewayReference,
            'details' => [
                'provider_status' => $result->status,
                'provider_reference' => $result->reference,
                'provider_answered' => $result->ok,
            ],
        ]);
    }

    /** @return array<string, mixed> */
    private function ledgerResolutionMeta(?array $resolution): array
    {
        if ($resolution === null) {
            return [];
        }

        return [
            'backoffice_resolution' => $this->resolutionSnapshot($resolution),
        ];
    }

    /** @return array<string, mixed> */
    private function resolutionSnapshot(array $resolution): array
    {
        return [
            'action' => $resolution['action'] ?? null,
            'admin_id' => $resolution['admin_id'] ?? null,
            'verification_source' => $resolution['verification_source'] ?? null,
            'evidence_reference' => $resolution['evidence_reference'] ?? null,
            'gateway_reference' => $resolution['gateway_reference'] ?? null,
            'amount' => $resolution['amount'] ?? null,
            'currency' => $resolution['currency'] ?? null,
            'note' => $resolution['note'] ?? null,
            'resolved_at' => now()->toIso8601String(),
        ];
    }

    /** @param array<string, mixed>|null $resolution @param array<string, mixed> $details */
    private function writeResolution(
        Transaction $transaction,
        ?array $resolution,
        string $fromStatus,
        string $toStatus,
        array $details = [],
    ): void {
        if ($resolution === null || blank($resolution['action'] ?? null)) {
            return;
        }

        PaymentResolutionLog::query()->create([
            'admin_id' => $resolution['admin_id'] ?? null,
            'transaction_id' => $transaction->id,
            'reference' => (string) $transaction->reference,
            'provider' => $transaction->provider,
            'action' => $resolution['action'],
            'from_status' => $fromStatus,
            'to_status' => $toStatus,
            'verification_source' => $resolution['verification_source'] ?? null,
            'evidence_reference' => $resolution['evidence_reference'] ?? null,
            'gateway_reference' => $resolution['gateway_reference'] ?? null,
            'amount' => $resolution['amount'] ?? null,
            'currency' => $resolution['currency'] ?? null,
            'note' => $resolution['note'] ?? null,
            'details' => array_merge((array) ($resolution['details'] ?? []), $details),
            'resolved_at' => now(),
        ]);
    }

    private function ledger(): PaymentReferenceLedger
    {
        return $this->ledger;
    }
}
