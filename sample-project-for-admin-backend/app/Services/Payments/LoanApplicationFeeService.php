<?php

namespace App\Services\Payments;

use App\Contracts\PaymentGateway;
use App\Enums\ChargePurpose;
use App\Enums\LoanStatus;
use App\Enums\TransactionType;
use App\Models\Loan;
use App\Models\Transaction;
use App\Models\User;
use App\Services\LoanService;
use App\Services\Payments\Concerns\SettlesGatewayCharge;
use App\Services\Payments\DTO\ChargeRequest;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Throwable;

/**
 * Starts and settles the separate, insurance fee required before a loan enters staff review.
 * A pending Transaction reserves the user's application slot; the Loan row is created only after
 * the gateway settlement trait has independently verified the exact provider/reference/amount.
 */
class LoanApplicationFeeService
{
    use SettlesGatewayCharge;

    public function __construct(
        private PaymentGatewayManager $gateways,
        private LoanService $loans,
        private PaymentReferenceLedger $ledger,
    ) {}

    /** @return array{available: bool, amount?: ?float, currency?: string, provider?: string, fee_type?: string, fee_percentage?: ?float, reason?: string} */
    public function availability(?float $loanAmountUsd = null): array
    {
        try {
            $gateway = $this->gateways->for(ChargePurpose::LOAN_APPLICATION_FEE);
        } catch (InvalidArgumentException) {
            return ['available' => false, 'reason' => 'The loan application payment channel is not configured.'];
        }

        if (! $gateway->configured()) {
            return ['available' => false, 'reason' => 'The loan application payment channel is not active right now.'];
        }

        $fee = $this->feeForGateway($gateway, $loanAmountUsd);
        if ($fee === null) {
            return ['available' => false, 'reason' => 'The configured payment channel must use USD or NGN for loan application fees.'];
        }

        return [
            'available' => true,
            'amount' => $fee['amount'],
            'currency' => $fee['currency'],
            'provider' => $gateway->name(),
            'fee_type' => $fee['type'],
            'fee_percentage' => $fee['percentage'],
        ];
    }

    /**
     * Start or reuse a hosted/manual payment. No loan application is persisted at this point.
     *
     * @return array{ok: bool, error?: string, checkout_url?: string, reference?: string}
     */
    public function start(
        User $user,
        string $loanType,
        float $amountUsd,
        string $dueDate,
        bool $agreementAccepted,
        string $accountName,
        string $accountNumber,
        string $bankName,
    ): array {
        $application = $this->loans->prepareApplication(
            $user,
            $loanType,
            $amountUsd,
            $dueDate,
            $agreementAccepted,
            checkUnresolved: false,
            accountName: $accountName,
            accountNumber: $accountNumber,
            bankName: $bankName,
        );
        $availability = $this->availability($amountUsd);

        if (! $availability['available']) {
            return ['ok' => false, 'error' => $availability['reason'] ?? 'Loan application payment is unavailable.'];
        }

        try {
            return Cache::lock("charge-start:loan-application-fee:{$user->id}", 20)
                ->block(8, fn (): array => $this->createOrReuse($user, $application));
        } catch (LockTimeoutException) {
            return ['ok' => false, 'error' => 'A loan-fee payment is already being set up — please wait a moment and try again.'];
        }
    }

    /** @return array{url: string, reference: string}|null */
    public function openCheckout(User $user): ?array
    {
        $transaction = $this->loans->pendingFeePayment($user);
        $url = $transaction?->meta['checkout_url'] ?? null;

        return filled($url) && $transaction !== null
            ? ['url' => (string) $url, 'reference' => (string) $transaction->reference]
            : null;
    }

    /**
     * Cancel only this user's still-pending application fee. Verify with the pinned provider first,
     * so a payment that completed before its webhook is settled instead of being discarded.
     */
    public function cancelPendingPayment(User $user, string $reference): string
    {
        $pending = Transaction::query()
            ->where('reference', $reference)
            ->where('user_id', $user->id)
            ->where('type', TransactionType::LOAN_APPLICATION_FEE->value)
            ->where('status', 'pending')
            ->exists();

        if (! $pending) {
            return 'not_pending';
        }

        $verificationStatus = $this->finalize($reference);
        if ($verificationStatus !== 'pending') {
            return $verificationStatus;
        }

        return DB::transaction(function () use ($user, $reference): string {
            $payment = Transaction::query()
                ->where('reference', $reference)
                ->where('user_id', $user->id)
                ->where('type', TransactionType::LOAN_APPLICATION_FEE->value)
                ->lockForUpdate()
                ->first();

            if ($payment === null || $payment->status !== 'pending') {
                return 'not_pending';
            }

            if ($this->ledger->isSettled($reference)) {
                return 'completed';
            }

            // A loan normally does not exist until the fee is verified. If an older or partially
            // migrated flow left a linked loan awaiting review, remove only this user's processing
            // loan; never touch a settled, approved, or unrelated application.
            $linkedLoans = Loan::query()
                ->where('user_id', $user->id)
                ->where('application_fee_reference', $reference)
                ->where('status', LoanStatus::PROCESSING->value)
                ->lockForUpdate()
                ->get();

            foreach ($linkedLoans as $loan) {
                $loan->delete();
            }

            $payment->delete();

            return 'cancelled';
        }, attempts: 3);
    }

    /** @return array{ok: bool, error?: string, checkout_url?: string, reference?: string} */
    private function createOrReuse(User $user, array $application): array
    {
        try {
            $gateway = $this->gateways->for(ChargePurpose::LOAN_APPLICATION_FEE);
        } catch (InvalidArgumentException) {
            return ['ok' => false, 'error' => 'The loan application payment channel is not configured.'];
        }

        if (! $gateway->configured()) {
            return ['ok' => false, 'error' => 'The loan application payment channel is not active right now.'];
        }

        $fee = $this->feeForGateway($gateway, (float) $application['amount_usd']);
        if ($fee === null) {
            return ['ok' => false, 'error' => 'The configured payment channel must use USD or NGN for loan application fees.'];
        }

        // Coupon redemption locks this same user row. Reserving the payment slot under that lock
        // closes the race where a coupon and a card checkout could otherwise both be started at once.
        $prepared = DB::transaction(function () use ($user, $application, $gateway, $fee): array {
            $lockedUser = User::query()->whereKey($user->id)->lockForUpdate()->firstOrFail();
            $pending = $this->loans->pendingFeePayment($lockedUser);

            if ($pending !== null) {
                if ($this->sameApplication((array) $pending->meta, $application)
                    && filled($pending->meta['checkout_url'] ?? null)) {
                    return ['reuse' => [
                        'ok' => true,
                        'checkout_url' => (string) $pending->meta['checkout_url'],
                        'reference' => (string) $pending->reference,
                    ]];
                }

                return ['error' => 'A loan-fee payment is already awaiting verification. Complete it before starting another application.'];
            }

            if ($this->loans->hasUnresolvedApplication($lockedUser)) {
                return ['error' => 'You already have an unresolved loan application. Wait for it to be processed before applying again.'];
            }

            $reference = 'LAF-'.Str::upper(Str::random(16));
            $transaction = Transaction::query()->create([
                'user_id' => $lockedUser->id,
                'type' => TransactionType::LOAN_APPLICATION_FEE,
                'direction' => 'debit',
                'amount' => $fee['amount_usd'],
                'wallet' => null,
                'status' => 'pending',
                'reference' => $reference,
                'provider' => $gateway->name(),
                'description' => 'Non-refundable loan application fee',
                'meta' => array_merge($application, [
                    'gateway' => $gateway->name(),
                    'loan_amount_usd' => $application['amount_usd'],
                    'application_fee_amount' => $fee['amount'],
                    'application_fee_currency' => $fee['currency'],
                    'application_fee_type' => $fee['type'],
                    'application_fee_percentage' => $fee['percentage'],
                    // The shared settlement guard uses this expected major-unit amount for every
                    // configured currency, despite the historical key name.
                    'amount_ngn' => $fee['amount'],
                    'currency' => $fee['currency'],
                ]),
            ]);

            return ['transaction' => $transaction, 'user' => $lockedUser];
        });

        if (isset($prepared['reuse'])) {
            return $prepared['reuse'];
        }

        if (isset($prepared['error'])) {
            return ['ok' => false, 'error' => $prepared['error']];
        }

        /** @var Transaction $transaction */
        $transaction = $prepared['transaction'];
        /** @var User $user */
        $user = $prepared['user'];
        $reference = (string) $transaction->reference;

        try {
            $result = $gateway->initiate(new ChargeRequest(
                reference: $reference,
                amount: $fee['amount'],
                currency: $fee['currency'],
                email: $user->email,
                customerName: $user->first_name ?: $user->username,
                narration: 'Nextel loan application fee',
                callbackUrl: route('loan.fee.callback', ['reference' => $reference]),
                notificationUrl: route('webhooks.payments', ['provider' => $gateway->name()]),
                metadata: [
                    'reference' => $reference,
                    'user_id' => $user->id,
                    'kind' => 'loan_application_fee',
                ],
            ));

            if (! $result->ok) {
                $this->failPendingTransaction($transaction, $result->message ?? 'init failed');

                return ['ok' => false, 'error' => $result->message ?? 'Could not start the loan-fee payment.'];
            }

            // Merge checkout details under the transaction lock so a very fast settlement's loan_id
            // cannot be overwritten by a stale copy of the metadata.
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

            Log::warning('Could not initiate loan application fee payment', [
                'reference' => $reference,
                'user_id' => $user->id,
                'provider' => $gateway->name(),
                'message' => $exception->getMessage(),
            ]);

            return ['ok' => false, 'error' => 'Could not start the loan-fee payment. Please try again.'];
        }
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

    private function sameApplication(array $pending, array $application): bool
    {
        return (string) ($pending['loan_type'] ?? '') === (string) $application['loan_type']
            && round((float) ($pending['loan_amount_usd'] ?? 0), 2) === round((float) $application['amount_usd'], 2)
            && (string) ($pending['due_date'] ?? '') === (string) $application['due_date']
            && (string) ($pending['disbursement_account_name'] ?? '') === (string) $application['disbursement_account_name']
            && (string) ($pending['disbursement_account_number'] ?? '') === (string) $application['disbursement_account_number']
            && (string) ($pending['disbursement_bank_name'] ?? '') === (string) $application['disbursement_bank_name'];
    }

    /** @return array{amount: ?float, amount_usd: ?float, currency: string, type: string, percentage: ?float}|null */
    private function feeForGateway(PaymentGateway $gateway, ?float $loanAmountUsd): ?array
    {
        $currency = strtoupper($gateway->chargeCurrency());
        if (! in_array($currency, ['NGN', 'USD'], true)) {
            return null;
        }

        if ($loanAmountUsd === null && $this->loans->applicationFeeMode() === 'percentage') {
            return [
                'amount' => null,
                'amount_usd' => null,
                'currency' => $currency,
                'type' => 'percentage',
                'percentage' => $this->loans->applicationFeePercentage(),
            ];
        }

        return $this->loans->applicationFeeForCurrency($loanAmountUsd ?? 0, $currency);
    }

    protected function transactionType(): TransactionType
    {
        return TransactionType::LOAN_APPLICATION_FEE;
    }

    /** Called only after SettlesGatewayCharge reconciles the pinned provider's successful response. */
    protected function applyCompletion(Transaction $transaction): void
    {
        $loan = $this->loans->createFromPaidTransaction($transaction);

        Log::info('Loan application submitted after verified application-fee payment', [
            'reference' => $transaction->reference,
            'loan_id' => $loan->id,
            'user_id' => $transaction->user_id,
        ]);
    }
}
