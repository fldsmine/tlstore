<?php

namespace App\Services\Payments;

use App\Enums\PaymentProvider;
use App\Enums\TransactionType;
use App\Models\Admin;
use App\Models\PaymentResolutionLog;
use App\Models\ProductOrder;
use App\Models\Transaction;
use App\Services\Payments\DTO\VerifyResult;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Backoffice-only workflow for rechecking a provider, confirming a charge from external evidence,
 * or closing a request as unpaid. All paid outcomes still use ChargeSettlement and its usual guards.
 */
class PaymentResolutionService
{
    /** @var array<string, string> */
    public const VERIFICATION_SOURCES = [
        'gateway_dashboard' => 'Gateway dashboard',
        'merchant_statement' => 'Merchant statement',
        'bank_statement' => 'Bank statement',
        'transfer_receipt' => 'Transfer receipt',
        'other' => 'Other official evidence',
    ];

    public function __construct(
        private ChargeSettlement $settlement,
        private PaymentReferenceLedger $ledger,
    ) {}

    /**
     * Re-query the provider pinned on the payment request. A success is settled only after all normal
     * amount, currency, reference, duplicate-payment and entitlement checks pass.
     */
    public function recheck(string $kind, string $id, Admin $admin): string
    {
        $this->authorizeWrite($admin);
        $record = $this->findRecord($kind, $id);
        $this->assertReviewable($record);
        $this->assertNotAlreadySettled($record);

        $context = [
            'action' => 'provider_recheck',
            'admin_id' => $admin->id,
            'verification_source' => 'provider_api',
            'gateway_reference' => null,
            'note' => null,
        ];

        return $this->settlement->reverifyForBackoffice($record->reference, $context);
    }

    /**
     * Record an admin's manual payment verification and route the result through the ordinary
     * settlement service. Manual approval requires a super admin and a distinct provider/bank
     * transaction reference, exact currency, sufficient amount, evidence source and an audit note.
     *
     * @param  array{amount: string|float|int, currency: string, gateway_reference: string, verification_source: string, evidence_reference: string, note: string, attested: bool}  $verification
     */
    public function confirmManually(string $kind, string $id, Admin $admin, array $verification): string
    {
        $this->authorizeSuper($admin);

        if (($verification['attested'] ?? false) !== true) {
            throw ValidationException::withMessages(['manualAttestation' => 'Confirm that you checked the official payment evidence before approving.']);
        }

        $record = $this->findRecord($kind, $id);
        $this->assertReviewable($record);
        $this->assertNotAlreadySettled($record);

        $provider = (string) $record->provider;
        $providerReference = trim((string) ($verification['gateway_reference'] ?? ''));
        $evidenceReference = trim((string) ($verification['evidence_reference'] ?? ''));
        $source = trim((string) ($verification['verification_source'] ?? ''));
        $note = trim((string) ($verification['note'] ?? ''));
        $currency = strtoupper(trim((string) ($verification['currency'] ?? '')));
        $rawAmount = $verification['amount'] ?? null;
        if (! is_numeric($rawAmount)) {
            throw ValidationException::withMessages(['manualAmount' => 'Enter the amount verified in the payment evidence.']);
        }
        $amount = round((float) $rawAmount, 2);
        $expected = $this->expectedAmount($record);
        $expectedCurrency = $this->expectedCurrency($record);

        if (strlen($providerReference) < 3 || strlen($providerReference) > 120 || hash_equals(strtoupper((string) $record->reference), strtoupper($providerReference))) {
            throw ValidationException::withMessages(['manualGatewayReference' => 'Enter the provider or bank transfer reference, not the request reference.']);
        }

        if (strlen($evidenceReference) < 3 || strlen($evidenceReference) > 120) {
            throw ValidationException::withMessages(['evidenceReference' => 'Add a short reference or locator for the evidence you checked.']);
        }

        if (! array_key_exists($source, self::VERIFICATION_SOURCES)) {
            throw ValidationException::withMessages(['verificationSource' => 'Choose where you verified the payment.']);
        }

        if (strlen($note) < 10 || strlen($note) > 1000) {
            throw ValidationException::withMessages(['manualNote' => 'Add a verification note of 10 to 1,000 characters.']);
        }

        if ($expected <= 0 || $expectedCurrency === '') {
            throw ValidationException::withMessages(['manualAmount' => 'This request has no valid expected amount/currency. It cannot be manually approved.']);
        }

        if ($currency !== $expectedCurrency) {
            throw ValidationException::withMessages(['manualCurrency' => "The verified currency must match the requested {$expectedCurrency} currency."]);
        }

        if (! is_finite($amount) || $amount <= 0 || $amount > 999999999999.99 || $amount < $expected - 0.01) {
            throw ValidationException::withMessages(['manualAmount' => 'The verified payment must cover the full requested amount and be within the accepted amount range.']);
        }

        if ($this->ledger->findByGatewayReference($provider, $providerReference) !== null) {
            throw ValidationException::withMessages(['manualGatewayReference' => 'That provider payment reference is already attached to another settled charge.']);
        }

        $resolution = [
            'action' => 'manual_confirm',
            'admin_id' => $admin->id,
            'verification_source' => $source,
            'evidence_reference' => $evidenceReference,
            'gateway_reference' => $providerReference,
            'amount' => $amount,
            'currency' => $currency,
            'note' => $note,
            'details' => ['manual_override' => true, 'admin_attested' => true],
        ];

        $verified = new VerifyResult(
            ok: true,
            status: VerifyResult::STATUS_SUCCESS,
            amount: $amount,
            currency: $currency,
            reference: (string) $record->reference,
            gatewayReference: $providerReference,
        );

        return $this->settlement->finalizeManually($record->reference, $verified, $resolution);
    }

    /** Close a still-pending request after the super admin confirms no successful payment exists. */
    public function closeAsUnpaid(string $kind, string $id, Admin $admin, string $note, bool $confirmed): void
    {
        $this->authorizeSuper($admin);

        if (! $confirmed) {
            throw ValidationException::withMessages(['closeAttestation' => 'Confirm that you checked for a successful payment before closing this request.']);
        }

        $record = $this->findRecord($kind, $id);
        $this->assertReviewable($record);
        $this->assertNotAlreadySettled($record);

        $note = trim($note);
        if (strlen($note) < 10 || strlen($note) > 1000) {
            throw ValidationException::withMessages(['closeNote' => 'Add a note of 10 to 1,000 characters explaining why the request is being closed as unpaid.']);
        }

        DB::transaction(function () use ($kind, $record, $admin, $note): void {
            $locked = $kind === 'transaction'
                ? Transaction::query()->whereKey($record->getKey())->lockForUpdate()->firstOrFail()
                : ProductOrder::query()->whereKey($record->getKey())->lockForUpdate()->firstOrFail();

            if ($locked->status !== 'pending') {
                throw ValidationException::withMessages(['closeNote' => 'Only a pending payment request can be closed as unpaid. Refresh and review its latest status.']);
            }

            if ($this->ledger->isSettled((string) $locked->reference)) {
                throw ValidationException::withMessages(['closeNote' => 'This payment reference is already recorded as settled and cannot be closed as unpaid.']);
            }

            $timestamp = now()->toIso8601String();
            $snapshot = [
                'action' => 'closed_unpaid',
                'admin_id' => $admin->id,
                'note' => $note,
                'resolved_at' => $timestamp,
            ];

            if ($locked instanceof Transaction) {
                $locked->update([
                    'status' => 'failed',
                    'meta' => array_merge((array) $locked->meta, ['payment_resolution' => $snapshot]),
                ]);
            } else {
                $locked->update([
                    'status' => ProductOrder::STATUS_FAILED,
                    'failure_reason' => Str::limit('Closed by backoffice as unpaid: '.$note, 250, ''),
                    'meta' => array_merge((array) $locked->meta, ['payment_resolution' => $snapshot]),
                ]);
            }

            PaymentResolutionLog::query()->create([
                'admin_id' => $admin->id,
                'transaction_id' => $locked instanceof Transaction ? $locked->id : null,
                'product_order_id' => $locked instanceof ProductOrder ? $locked->id : null,
                'reference' => (string) $locked->reference,
                'provider' => $locked->provider,
                'action' => 'closed_unpaid',
                'from_status' => 'pending',
                'to_status' => 'failed',
                'note' => $note,
                'details' => ['confirmed_no_successful_payment' => true],
                'resolved_at' => now(),
            ]);
        });
    }

    private function findRecord(string $kind, string $id): Transaction|ProductOrder
    {
        return match ($kind) {
            'transaction' => Transaction::query()->findOrFail($id),
            'product_order' => ProductOrder::query()->findOrFail($id),
            default => throw ValidationException::withMessages(['request' => 'Choose a valid payment request.']),
        };
    }

    private function assertReviewable(Transaction|ProductOrder $record): void
    {
        if ($record instanceof Transaction) {
            $type = $record->type instanceof TransactionType
                ? $record->type
                : TransactionType::tryFrom((string) $record->type);

            if ($type === null || ! $type->isGatewayCharge()) {
                throw ValidationException::withMessages(['request' => 'This transaction is not a gateway payment request.']);
            }
        }

        if (! in_array((string) $record->status, ['pending', 'failed'], true)) {
            throw ValidationException::withMessages(['request' => 'Only pending or failed payment requests can be resolved.']);
        }

        if (! in_array((string) $record->provider, PaymentProvider::values(), true)) {
            throw ValidationException::withMessages(['request' => 'This request has no recognized payment provider.']);
        }

        if ((string) $record->status === 'failed' && ! $this->hasInitiatedCheckout($record)) {
            throw ValidationException::withMessages(['request' => 'No checkout was successfully created for this failed request.']);
        }
    }

    private function assertNotAlreadySettled(Transaction|ProductOrder $record): void
    {
        if ($this->ledger->isSettled((string) $record->reference)) {
            throw ValidationException::withMessages(['request' => 'This payment is already recorded in the single-use settlement ledger and cannot be granted again.']);
        }
    }

    private function hasInitiatedCheckout(Transaction|ProductOrder $record): bool
    {
        if ($record instanceof ProductOrder) {
            return filled($record->checkout_url)
                || filled($record->gateway_reference)
                || isset($record->meta['manual_review'])
                || isset($record->meta['reconcile_error']);
        }

        return filled($record->gateway_reference)
            || filled($record->meta['checkout_url'] ?? null)
            || isset($record->meta['manual_review'])
            || isset($record->meta['reconcile_error']);
    }

    private function expectedAmount(Transaction|ProductOrder $record): float
    {
        return $record instanceof ProductOrder
            ? round((float) $record->amount_ngn, 2)
            : round((float) ($record->meta['amount_ngn'] ?? 0), 2);
    }

    private function expectedCurrency(Transaction|ProductOrder $record): string
    {
        return strtoupper((string) ($record instanceof ProductOrder
            ? $record->currency
            : ($record->meta['currency'] ?? '')));
    }

    private function authorizeWrite(Admin $admin): void
    {
        if (! $admin->canWrite()) {
            throw new AuthorizationException('You are not allowed to manage payment requests.');
        }
    }

    private function authorizeSuper(Admin $admin): void
    {
        if (! $admin->isSuper()) {
            throw new AuthorizationException('Only super admins can manually resolve payment requests.');
        }
    }
}
