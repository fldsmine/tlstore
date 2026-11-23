<?php

namespace App\Services\Payments;

use App\Contracts\PaymentGateway;
use App\Enums\ChargePurpose;
use App\Enums\LoanStatus;
use App\Enums\TransactionType;
use App\Models\Loan;
use App\Models\Transaction;
use App\Models\User;
use App\Services\CurrencyService;
use App\Services\Payments\Concerns\SettlesGatewayCharge;
use App\Services\Payments\DTO\ChargeRequest;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use InvalidArgumentException;
use RuntimeException;
use Throwable;
use Illuminate\Validation\ValidationException;

/** Starts, settles and records the paid guarantor form required before manual disbursement. */
class LoanGuarantorFormService
{
    use SettlesGatewayCharge;

    public function __construct(
        private PaymentGatewayManager $gateways,
        private PaymentReferenceLedger $ledger,
    ) {}

    public function feeNgn(): float
    {
        return round((float) config('settings.loan.guarantor_form_fee_ngn', 1500), 2);
    }

    /** @return array{available: bool, fee_ngn: float, charge_amount?: float, currency?: string, provider?: string, reason?: string} */
    public function availability(): array
    {
        $feeNgn = $this->feeNgn();

        try {
            $gateway = $this->gateways->for(ChargePurpose::LOAN_APPLICATION_FEE);
        } catch (InvalidArgumentException) {
            return ['available' => false, 'fee_ngn' => $feeNgn, 'reason' => 'The loan payment channel is not configured.'];
        }

        if (! $gateway->configured()) {
            return ['available' => false, 'fee_ngn' => $feeNgn, 'reason' => 'The loan payment channel is not active right now.'];
        }

        $quote = $this->chargeQuote($gateway);
        if ($quote === null) {
            return ['available' => false, 'fee_ngn' => $feeNgn, 'reason' => 'The active payment channel cannot collect this guarantor fee currency.'];
        }

        return [
            'available' => true,
            'fee_ngn' => $feeNgn,
            'charge_amount' => $quote['amount'],
            'currency' => $quote['currency'],
            'provider' => $gateway->name(),
        ];
    }

    /** @return Collection<string, Transaction> Pending payment rows keyed by loan id. */
    public function pendingPaymentsForLoans(User $user, iterable $loans): Collection
    {
        $loanIds = collect($loans)->map(fn (Loan $loan): string => (string) $loan->id)->flip();
        if ($loanIds->isEmpty()) {
            return collect();
        }

        return Transaction::query()
            ->where('user_id', $user->id)
            ->where('type', TransactionType::LOAN_GUARANTOR_FORM_FEE->value)
            ->where('status', 'pending')
            ->latest()
            ->get()
            ->filter(fn (Transaction $transaction): bool => $loanIds->has((string) ($transaction->meta['loan_id'] ?? '')))
            ->unique(fn (Transaction $transaction): string => (string) ($transaction->meta['loan_id'] ?? ''))
            ->keyBy(fn (Transaction $transaction): string => (string) $transaction->meta['loan_id']);
    }

    /** Start a new charge or reuse this loan's outstanding hosted checkout. */
    public function start(User $user, string $loanId): array
    {
        $loan = Loan::query()->where('user_id', $user->id)->find($loanId);
        if ($loan === null || $loan->status !== LoanStatus::APPROVED) {
            return ['ok' => false, 'error' => 'The guarantor form is available only for a loan that is ready for this step.'];
        }

        if ($loan->hasCompletedGuarantorForm()) {
            return ['ok' => false, 'error' => 'The guarantor form has already been submitted.'];
        }

        if ($loan->guarantor_form_fee_paid_at !== null) {
            return ['ok' => false, 'error' => 'Payment is already verified. Open the form to complete it.'];
        }

        $availability = $this->availability();
        if (! $availability['available']) {
            return ['ok' => false, 'error' => $availability['reason'] ?? 'Guarantor-form payment is unavailable.'];
        }

        try {
            $gateway = $this->gateways->for(ChargePurpose::LOAN_APPLICATION_FEE);
            $quote = $this->chargeQuote($gateway);
            if ($quote === null) {
                return ['ok' => false, 'error' => 'The active payment channel cannot collect this guarantor fee currency.'];
            }

            return Cache::lock("charge-start:loan-guarantor-form:{$loanId}", 20)
                ->block(8, fn (): array => $this->createOrReuse($user, $loanId, $gateway, $quote));
        } catch (LockTimeoutException) {
            return ['ok' => false, 'error' => 'A guarantor-form payment is already being set up — please wait and try again.'];
        } catch (InvalidArgumentException) {
            return ['ok' => false, 'error' => 'The loan payment channel is not configured.'];
        }
    }

    /** Verify and settle only a pending fee belonging to this user and loan. */
    public function refreshPendingPayment(User $user, string $loanId, string $reference): string
    {
        if ($this->transactionForLoan($user, $loanId, $reference, ['pending']) === null) {
            return 'not_pending';
        }

        return $this->finalize($reference);
    }

    /**
     * Cancel only the specified user's payment for this loan. Keep provider-pending, unverifiable,
     * successful, or mismatched payments; remove the request only after a definitive failure.
     */
    public function cancelPendingPayment(User $user, string $loanId, string $reference): string
    {
        $pending = $this->transactionForLoan($user, $loanId, $reference, ['pending']);
        if ($pending === null) {
            return 'not_pending';
        }

        $status = $this->finalize($reference);
        if ($status === 'completed') {
            return 'completed';
        }
        if ($status === 'unavailable' || $status === 'pending') {
            return $status;
        }
        if ($status !== 'failed') {
            return 'not_pending';
        }

        return DB::transaction(function () use ($user, $loanId, $reference, $status): string {
            $payment = Transaction::query()
                ->where('reference', $reference)
                ->where('user_id', $user->id)
                ->where('type', TransactionType::LOAN_GUARANTOR_FORM_FEE->value)
                ->lockForUpdate()
                ->first();

            if ($payment === null) {
                return 'not_pending';
            }

            if ($this->ledger->isSettled($reference)) {
                return $payment->status === 'completed' ? 'completed' : 'needs_review';
            }

            $loan = Loan::query()
                ->whereKey($loanId)
                ->where('user_id', $user->id)
                ->lockForUpdate()
                ->first();

            if ($loan === null || $loan->status !== LoanStatus::APPROVED || $loan->hasCompletedGuarantorForm()) {
                return 'not_pending';
            }

            if ($loan->guarantor_form_fee_paid_at !== null) {
                return 'completed';
            }

            $meta = (array) $payment->meta;
            $definitiveProviderFailure = $payment->status === 'failed'
                && $status === 'failed'
                && ! isset($meta['reconcile_error'])
                && ! isset($meta['manual_review']);

            if (! $definitiveProviderFailure) {
                if (isset($meta['reconcile_error']) || isset($meta['manual_review'])) {
                    return 'needs_review';
                }

                return $payment->status === 'pending' ? 'pending' : 'not_pending';
            }

            $payment->delete();

            return 'cancelled';
        }, attempts: 3);
    }

    /** Store the applicant-confirmed guarantor details once the verified fee unlocks the form. */
    public function submit(User $user, string $loanId, array $form): Loan
    {
        $form = array_map(fn (mixed $value): mixed => is_string($value) ? trim($value) : $value, $form);
        unset($form['occupation'], $form['employer']);
        foreach (['email'] as $optionalField) {
            if (($form[$optionalField] ?? null) === '') {
                $form[$optionalField] = null;
            }
        }

        $validated = Validator::make($form, [
            'full_name' => ['required', 'string', 'max:120'],
            'relationship' => ['required', 'string', 'max:80'],
            'phone' => ['required', 'string', 'max:32', 'regex:/^[0-9+() .-]{7,32}$/'],
            'email' => ['nullable', 'email', 'max:255'],
            'residential_address' => ['required', 'string', 'max:255'],
            'signature' => ['required', 'string', 'max:120', 'same:full_name'],
            'consent' => ['accepted'],
        ])->validate();

        $validated['consent'] = true;

        return DB::transaction(function () use ($user, $loanId, $validated): Loan {
            $loan = Loan::query()
                ->whereKey($loanId)
                ->where('user_id', $user->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($loan->status !== LoanStatus::APPROVED) {
                throw ValidationException::withMessages(['guarantorForm' => 'The guarantor form is available only for a loan that is ready for this step.']);
            }

            if ($loan->guarantor_form_fee_paid_at === null || blank($loan->guarantor_form_fee_reference)) {
                throw ValidationException::withMessages(['guarantorForm' => 'Verify the guarantor-form fee before submitting this form.']);
            }

            if ($loan->hasCompletedGuarantorForm()) {
                throw ValidationException::withMessages(['guarantorForm' => 'The guarantor form has already been submitted.']);
            }

            $loan->update([
                'guarantor_form_data' => $validated,
                'guarantor_form_submitted_at' => now(),
            ]);

            return $loan->refresh();
        });
    }

    protected function transactionType(): TransactionType
    {
        return TransactionType::LOAN_GUARANTOR_FORM_FEE;
    }

    /** Apply only after the gateway confirms the pinned reference, amount and currency. */
    protected function applyCompletion(Transaction $transaction): void
    {
        $meta = (array) $transaction->meta;
        $loanId = (string) ($meta['loan_id'] ?? '');
        $feeNgn = round((float) ($meta['guarantor_form_fee_ngn'] ?? 0), 2);

        if ($loanId === '' || $feeNgn <= 0) {
            throw new RuntimeException('The guarantor-form payment has no valid loan or fee snapshot.');
        }

        $loan = Loan::query()
            ->whereKey($loanId)
            ->where('user_id', $transaction->user_id)
            ->lockForUpdate()
            ->first();

        if ($loan === null
            || $loan->status !== LoanStatus::APPROVED
            || $loan->hasCompletedGuarantorForm()
            || $loan->guarantor_form_fee_paid_at !== null) {
            throw new RuntimeException('The linked loan is no longer eligible for this guarantor-form payment.');
        }

        $loan->update([
            'guarantor_form_fee_reference' => (string) $transaction->reference,
            'guarantor_form_fee_amount' => $feeNgn,
            'guarantor_form_fee_currency' => 'NGN',
            'guarantor_form_fee_paid_at' => now(),
        ]);
    }

    /** @return array{ok: bool, error?: string, checkout_url?: string, reference?: string} */
    private function createOrReuse(User $user, string $loanId, PaymentGateway $gateway, array $quote): array
    {
        $prepared = DB::transaction(function () use ($user, $loanId, $gateway, $quote): array {
            $loan = Loan::query()
                ->whereKey($loanId)
                ->where('user_id', $user->id)
                ->lockForUpdate()
                ->first();

            if ($loan === null || $loan->status !== LoanStatus::APPROVED) {
                return ['error' => 'The guarantor form is available only for a loan that is ready for this step.'];
            }

            if ($loan->hasCompletedGuarantorForm()) {
                return ['error' => 'The guarantor form has already been submitted.'];
            }

            if ($loan->guarantor_form_fee_paid_at !== null) {
                return ['error' => 'Payment is already verified. Open the form to complete it.'];
            }

            if ($this->hasPaymentReviewHold($user, $loanId)) {
                return ['error' => 'A previous payment needs staff review. Do not start a second payment request yet.'];
            }

            $pending = $this->pendingPaymentForLoan($user, $loanId);
            if ($pending !== null) {
                $checkoutUrl = $pending->meta['checkout_url'] ?? null;
                if (filled($checkoutUrl)) {
                    return ['reuse' => [
                        'ok' => true,
                        'checkout_url' => (string) $checkoutUrl,
                        'reference' => (string) $pending->reference,
                    ]];
                }

                return ['error' => 'A guarantor-form payment is already being set up. Please wait or cancel that request before trying again.'];
            }

            $reference = 'LGF-'.Str::upper(Str::random(16));
            $transaction = Transaction::query()->create([
                'user_id' => $user->id,
                'type' => TransactionType::LOAN_GUARANTOR_FORM_FEE,
                'direction' => 'debit',
                'amount' => $quote['amount_usd'],
                'wallet' => null,
                'status' => 'pending',
                'reference' => $reference,
                'provider' => $gateway->name(),
                'description' => 'Loan guarantor form fee',
                'meta' => [
                    'kind' => 'loan_guarantor_form_fee',
                    'gateway' => $gateway->name(),
                    'loan_id' => $loan->id,
                    'guarantor_form_fee_ngn' => $quote['fee_ngn'],
                    'charge_amount' => $quote['amount'],
                    'charge_currency' => $quote['currency'],
                    'amount_ngn' => $quote['amount'],
                    'currency' => $quote['currency'],
                ],
            ]);

            return ['transaction' => $transaction];
        });

        if (isset($prepared['reuse'])) {
            return $prepared['reuse'];
        }

        if (isset($prepared['error'])) {
            return ['ok' => false, 'error' => $prepared['error']];
        }

        /** @var Transaction $transaction */
        $transaction = $prepared['transaction'];
        $reference = (string) $transaction->reference;

        try {
            $result = $gateway->initiate(new ChargeRequest(
                reference: $reference,
                amount: $quote['amount'],
                currency: $quote['currency'],
                email: $user->email,
                customerName: $user->first_name ?: $user->username,
                narration: 'Nextel guarantor form fee',
                callbackUrl: route('loan.guarantor.fee.callback', ['reference' => $reference]),
                notificationUrl: route('webhooks.payments', ['provider' => $gateway->name()]),
                metadata: [
                    'reference' => $reference,
                    'user_id' => $user->id,
                    'loan_id' => $transaction->meta['loan_id'],
                    'kind' => 'loan_guarantor_form_fee',
                ],
            ));

            if (! $result->ok) {
                $this->failPendingTransaction($transaction, $result->message ?? 'init failed');

                return ['ok' => false, 'error' => $result->message ?? 'Could not start the guarantor-form payment.'];
            }

            DB::transaction(function () use ($transaction, $result): void {
                $locked = Transaction::query()->whereKey($transaction->id)->lockForUpdate()->firstOrFail();
                $locked->update([
                    'gateway_reference' => $result->gatewayReference,
                    'meta' => array_merge((array) $locked->meta, ['checkout_url' => $result->checkoutUrl]),
                ]);
            });

            return ['ok' => true, 'checkout_url' => (string) $result->checkoutUrl, 'reference' => $reference];
        } catch (Throwable $exception) {
            $this->failPendingTransaction($transaction, $exception->getMessage());

            Log::warning('Could not initiate guarantor-form fee payment', [
                'reference' => $reference,
                'loan_id' => $transaction->meta['loan_id'] ?? null,
                'provider' => $gateway->name(),
                'message' => $exception->getMessage(),
            ]);

            return ['ok' => false, 'error' => 'Could not start the guarantor-form payment. Please try again.'];
        }
    }

    private function pendingPaymentForLoan(User $user, string $loanId): ?Transaction
    {
        return Transaction::query()
            ->where('user_id', $user->id)
            ->where('type', TransactionType::LOAN_GUARANTOR_FORM_FEE->value)
            ->where('status', 'pending')
            ->latest()
            ->get()
            ->first(fn (Transaction $transaction): bool => (string) ($transaction->meta['loan_id'] ?? '') === $loanId);
    }

    private function transactionForLoan(User $user, string $loanId, string $reference, array $statuses): ?Transaction
    {
        $transaction = Transaction::query()
            ->where('reference', $reference)
            ->where('user_id', $user->id)
            ->where('type', TransactionType::LOAN_GUARANTOR_FORM_FEE->value)
            ->whereIn('status', $statuses)
            ->first();

        return $transaction !== null && (string) ($transaction->meta['loan_id'] ?? '') === $loanId
            ? $transaction
            : null;
    }

    private function hasPaymentReviewHold(User $user, string $loanId): bool
    {
        return Transaction::query()
            ->where('user_id', $user->id)
            ->where('type', TransactionType::LOAN_GUARANTOR_FORM_FEE->value)
            ->whereIn('status', ['failed', 'completed'])
            ->latest()
            ->get()
            ->contains(function (Transaction $transaction) use ($loanId): bool {
                if ((string) ($transaction->meta['loan_id'] ?? '') !== $loanId) {
                    return false;
                }

                return $this->ledger->isSettled((string) $transaction->reference)
                    || isset($transaction->meta['reconcile_error'])
                    || isset($transaction->meta['manual_review']);
            });
    }

    /** @return array{amount: float, amount_usd: float, currency: string, fee_ngn: float}|null */
    private function chargeQuote(PaymentGateway $gateway): ?array
    {
        $currency = strtoupper($gateway->chargeCurrency());
        if (! in_array($currency, ['NGN', 'USD'], true)) {
            return null;
        }

        $feeNgn = $this->feeNgn();
        if ($feeNgn <= 0) {
            return null;
        }

        $currencyService = app(CurrencyService::class);
        if ($currency === 'USD' && $currencyService->rate('NG') <= 0) {
            return null;
        }

        $amount = $currency === 'NGN'
            ? $feeNgn
            : round($currencyService->toUsd($feeNgn, 'NG'), 2);
        if ($amount <= 0) {
            return null;
        }

        $amountUsd = $currency === 'USD'
            ? $amount
            : app(CurrencyService::class)->toUsd($amount, 'NG');

        return [
            'amount' => round($amount, 2),
            'amount_usd' => round($amountUsd, 6),
            'currency' => $currency,
            'fee_ngn' => $feeNgn,
        ];
    }

    private function failPendingTransaction(Transaction $transaction, string $error): void
    {
        DB::transaction(function () use ($transaction, $error): void {
            $locked = Transaction::query()->whereKey($transaction->id)->lockForUpdate()->first();
            if ($locked === null || $locked->status !== 'pending') {
                return;
            }

            $locked->update([
                'status' => 'failed',
                'meta' => array_merge((array) $locked->meta, ['error' => $error]),
            ]);
        });
    }
}
