<?php

namespace App\Services;

use App\Enums\CouponType;
use App\Enums\LoanStatus;
use App\Enums\LoanType;
use App\Enums\TransactionType;
use App\Models\Coupon;
use App\Models\Loan;
use App\Models\Transaction;
use App\Models\User;
use App\Support\CouponCountryPolicy;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Throwable;

/**
 * Short-term loan rules shared by the web and API. Loan amounts and vendor balances are USD;
 * fixed application fees are configured in USD and NGN, while percentage fees are quoted in the active payment currency.
 */
class LoanService
{
    public function isEnabled(): bool
    {
        return (bool) config('settings.loan.enabled', false);
    }

    public function isGuarantorEnabled(): bool
    {
        return (bool) config('settings.loan.guarantor_form_enabled', false);
    }

    public function maxAmount(): float
    {
        return (float) config('settings.loan.max_amount', 100);
    }

    public function minAmount(): float
    {
        return max(1.0, (float) config('settings.loan.min_amount', 50));
    }

    public function repaymentDays(): int
    {
        return (int) config('settings.loan.repayment_days', 30);
    }

    public function serviceFeePercent(): float
    {
        return (float) config('settings.loan.service_fee_percent', 0);
    }

    public function applicationFeeUsd(): float
    {
        return round((float) config('settings.loan.application_fee_usd', 1), 2);
    }

    public function applicationFeeNgn(): float
    {
        return round((float) config('settings.loan.application_fee_ngn', 1000), 2);
    }

    public function applicationFeeMode(): string
    {
        $mode = strtolower((string) config('settings.loan.application_fee_mode', 'fixed'));

        return in_array($mode, ['fixed', 'percentage'], true) ? $mode : 'fixed';
    }

    public function applicationFeePercentage(): float
    {
        return round((float) config('settings.loan.application_fee_percentage', 1), 4);
    }

    /**
     * Quote a fee in the selected payment currency. Percentage fees are calculated from the loan
     * principal, converting USD to NGN when needed, and never round down to a free payment.
     *
     * @return array{amount: float, amount_usd: float, currency: string, type: string, percentage: ?float}|null
     */
    public function applicationFeeForCurrency(float $amountUsd, string $currency): ?array
    {
        $currency = strtoupper($currency);
        if (! in_array($currency, ['USD', 'NGN'], true)) {
            return null;
        }

        $mode = $this->applicationFeeMode();
        $percentage = $mode === 'percentage' ? $this->applicationFeePercentage() : null;

        if ($mode === 'fixed') {
            $amount = $currency === 'NGN' ? $this->applicationFeeNgn() : $this->applicationFeeUsd();
        } else {
            if ($percentage === null || $percentage <= 0 || $amountUsd <= 0) {
                return null;
            }

            $principal = $currency === 'NGN'
                ? app(CurrencyService::class)->toLocal($amountUsd, 'NG', 6)
                : $amountUsd;
            $amount = max(0.01, round($principal * $percentage / 100, 2));
        }

        if ($amount <= 0) {
            return null;
        }

        $amountUsdEquivalent = $mode === 'fixed' && $currency === 'NGN'
            ? $this->applicationFeeUsd()
            : ($currency === 'NGN' ? app(CurrencyService::class)->toUsd($amount, 'NG') : $amount);

        return [
            'amount' => round($amount, 2),
            'amount_usd' => round($amountUsdEquivalent, 6),
            'currency' => $currency,
            'type' => $mode,
            'percentage' => $percentage,
        ];
    }

    /** @return array<string, string> Enabled stable loan-type keys and their labels. */
    public function availableTypes(): array
    {
        $configured = (array) config('settings.loan.types', []);

        return collect(LoanType::cases())
            ->filter(fn (LoanType $type): bool => (bool) ($configured[$type->value] ?? true))
            ->mapWithKeys(fn (LoanType $type): array => [$type->value => $type->label()])
            ->all();
    }

    public function vendorCouponPriceUsd(string $region): float
    {
        if ($region === Coupon::REGION_NIGERIA) {
            $customerNgn = $this->applicationFeeNgn();
            $agentNgn = round((float) config('settings.loan.application_fee_agent_ngn', 800), 2);

            // Coupon vendors have a USD-denominated float. Preserve the configured NGN margin by
            // converting against the configured fixed fee pair (₦1,000 / $1 by default).
            return $customerNgn > 0
                ? round($this->applicationFeeUsd() * $agentNgn / $customerNgn, 6)
                : 0.0;
        }

        return round((float) config('settings.loan.application_fee_agent_usd', 0.80), 6);
    }

    public function customerCouponPriceUsd(string $region): float
    {
        // Coupon prices are stored in the same USD-denominated format as the other agent products.
        // The country-specific retail fee remains separately snapshotted on the loan receipt.
        return $this->applicationFeeUsd();
    }

    /** @return array{amount: ?float, currency: string, type: string, percentage: ?float} */
    public function feeForUser(User $user, ?float $amountUsd = null): array
    {
        $currency = $user->isNigerian() ? 'NGN' : 'USD';
        $quote = $this->applicationFeeForCurrency($amountUsd ?? 0, $currency);

        return [
            'amount' => $quote['amount'] ?? null,
            'currency' => $currency,
            'type' => $this->applicationFeeMode(),
            'percentage' => $this->applicationFeeMode() === 'percentage' ? $this->applicationFeePercentage() : null,
        ];
    }

    public function hasProcessingLoan(User $user): bool
    {
        return $user->loans()->where('status', LoanStatus::PROCESSING)->exists();
    }

    /** A pending card/manual payment also reserves the user's single unresolved application slot. */
    public function hasUnresolvedApplication(User $user, ?string $exceptPaymentReference = null): bool
    {
        if ($this->hasProcessingLoan($user)) {
            return true;
        }

        return Transaction::query()
            ->where('user_id', $user->id)
            ->where('type', TransactionType::LOAN_APPLICATION_FEE->value)
            ->where('status', 'pending')
            ->when($exceptPaymentReference !== null, fn ($query) => $query->where('reference', '!=', $exceptPaymentReference))
            ->exists();
    }

    public function pendingFeePayment(User $user): ?Transaction
    {
        return Transaction::query()
            ->where('user_id', $user->id)
            ->where('type', TransactionType::LOAN_APPLICATION_FEE->value)
            ->where('status', 'pending')
            ->latest()
            ->first();
    }

    /**
     * Validate an application without persisting it. This is the form-summary boundary: no Loan row
     * exists until a coupon is claimed or a gateway charge is verified.
     *
     * @return array{loan_type: string, amount_usd: float, due_date: string, agreement_accepted_at: string, min_amount_usd: float, max_amount_usd: float, service_fee_percent: float, disbursement_account_name: string, disbursement_account_number: string, disbursement_bank_name: string}
     */
    public function prepareApplication(
        User $user,
        string $loanType,
        float $amountUsd,
        string $dueDate,
        bool $agreementAccepted,
        bool $checkUnresolved = true,
        string $accountName = '',
        string $accountNumber = '',
        string $bankName = '',
    ): array {
        if (! $this->isEnabled()) {
            throw ValidationException::withMessages(['amount' => 'Loans aren’t available right now.']);
        }

        $type = LoanType::tryFrom($loanType);
        if ($type === null || ! array_key_exists($type->value, $this->availableTypes())) {
            throw ValidationException::withMessages(['loanType' => 'Choose an available loan type.']);
        }

        if (! $agreementAccepted) {
            throw ValidationException::withMessages(['agreementAccepted' => 'Accept the loan agreement before continuing.']);
        }

        $accountName = trim($accountName);
        $accountNumber = trim($accountNumber);
        $bankName = trim($bankName);

        if ($accountName === '' || strlen($accountName) > 120) {
            throw ValidationException::withMessages(['accountName' => 'Enter the account name that should receive the loan funds.']);
        }
        if (! preg_match('/^[A-Za-z0-9][A-Za-z0-9 -]{3,33}$/D', $accountNumber)) {
            throw ValidationException::withMessages(['accountNumber' => 'Enter a valid bank account number.']);
        }
        if ($bankName === '' || strlen($bankName) > 120) {
            throw ValidationException::withMessages(['bankName' => 'Enter the bank name for the receiving account.']);
        }

        /*if ($checkUnresolved && $this->hasUnresolvedApplication($user)) {
            throw ValidationException::withMessages(['amount' => 'You already have an unresolved loan application or fee payment. Wait for it to be processed before applying again.']);
        }*/

        $amountUsd = round($amountUsd, 2);
        $minAmount = $this->minAmount();
        if ($amountUsd < $minAmount) {
            throw ValidationException::withMessages(['amount' => 'The least you can borrow is $'.number_format($minAmount, 2).'.']);
        }

        $maxAmount = $this->maxAmount();
        if ($amountUsd > $maxAmount) {
            throw ValidationException::withMessages(['amount' => 'The most you can borrow is $'.number_format($maxAmount, 2).'.']);
        }

        try {
            $date = Carbon::parse($dueDate)->startOfDay();
        } catch (Throwable) {
            throw ValidationException::withMessages(['dueDate' => 'Choose a valid repayment date.']);
        }

        $dateString = $date->toDateString();
        $maxDate = now()->addDays($this->repaymentDays())->toDateString();

        if ($dateString <= now()->toDateString() || $dateString > $maxDate) {
            throw ValidationException::withMessages(['dueDate' => 'Repayment must be in the future and within '.$this->repaymentDays().' days.']);
        }

        return [
            'loan_type' => $type->value,
            'amount_usd' => $amountUsd,
            'due_date' => $dateString,
            'agreement_accepted_at' => now()->toIso8601String(),
            'min_amount_usd' => $minAmount,
            'max_amount_usd' => $maxAmount,
            'service_fee_percent' => $this->serviceFeePercent(),
            'disbursement_account_name' => $accountName,
            'disbursement_account_number' => $accountNumber,
            'disbursement_bank_name' => $bankName,
        ];
    }

    /** Validate and atomically claim a single-use loan-fee coupon before creating the application. */
    public function applyWithCoupon(
        User $user,
        string $loanType,
        float $amountUsd,
        string $dueDate,
        bool $agreementAccepted,
        string $couponCode,
        string $couponField = 'feeCouponCode',
        string $accountName = '',
        string $accountNumber = '',
        string $bankName = '',
    ): Loan {
        if ($this->applicationFeeMode() !== 'fixed') {
            throw ValidationException::withMessages([$couponField => 'Vendor coupons are only available for a fixed application fee. Pay the percentage fee through the active payment channel.']);
        }

        $application = $this->prepareApplication(
            $user,
            $loanType,
            $amountUsd,
            $dueDate,
            $agreementAccepted,
            accountName: $accountName,
            accountNumber: $accountNumber,
            bankName: $bankName,
        );
        $code = strtoupper(trim($couponCode));
        $coupon = Coupon::query()
            ->where('code', $code)
            ->where('type', CouponType::LOAN_FEE->value)
            ->first();

        if ($coupon === null || $coupon->user_id !== null || $coupon->used_at !== null || $coupon->is_expired) {
            throw ValidationException::withMessages([$couponField => 'Enter a valid, unused loan application fee coupon.']);
        }

        CouponCountryPolicy::assertUsable($user, $coupon, $couponField);

        return DB::transaction(function () use ($user, $application, $coupon, $couponField): Loan {
            $lockedUser = User::query()->whereKey($user->id)->lockForUpdate()->firstOrFail();
            $application = $this->prepareApplication(
                $lockedUser,
                $application['loan_type'],
                $application['amount_usd'],
                $application['due_date'],
                true,
                accountName: $application['disbursement_account_name'],
                accountNumber: $application['disbursement_account_number'],
                bankName: $application['disbursement_bank_name'],
            );

            $lockedCoupon = Coupon::query()->whereKey($coupon->id)->lockForUpdate()->first();
            if ($lockedCoupon === null
                || $lockedCoupon->type !== CouponType::LOAN_FEE
                || $lockedCoupon->user_id !== null
                || $lockedCoupon->used_at !== null
                || $lockedCoupon->is_expired) {
                throw ValidationException::withMessages([$couponField => 'That loan-fee coupon is no longer available.']);
            }

            CouponCountryPolicy::assertUsable($lockedUser, $lockedCoupon, $couponField);

            $claimed = Coupon::query()
                ->whereKey($lockedCoupon->id)
                ->where('type', CouponType::LOAN_FEE->value)
                ->whereNull('user_id')
                ->whereNull('used_at')
                ->where(fn ($query) => $query->whereNull('expires_at')->orWhere('expires_at', '>', now()))
                ->update(['user_id' => $lockedUser->id, 'used_at' => now()]);

            if ($claimed !== 1) {
                throw ValidationException::withMessages([$couponField => 'That loan-fee coupon has already been used or expired.']);
            }

            $fee = $this->feeForUser($lockedUser);

            return $this->createLoan($lockedUser, $application, [
                'application_fee_amount' => $fee['amount'],
                'application_fee_currency' => $fee['currency'],
                'application_fee_type' => $fee['type'],
                'application_fee_percentage' => $fee['percentage'],
                'application_fee_coupon_id' => $lockedCoupon->id,
                'application_fee_reference' => null,
            ]);
        });
    }

    /**
     * Called only from the verified gateway settlement pipeline. Application data is taken from the
     * server-created pending transaction, not from the browser callback or a client-supplied amount.
     */
    public function createFromPaidTransaction(Transaction $transaction): Loan
    {
        if ($transaction->type !== TransactionType::LOAN_APPLICATION_FEE || $transaction->user_id === null) {
            throw new RuntimeException('Loan application fee charge has no valid transaction owner.');
        }

        $user = User::query()->whereKey($transaction->user_id)->lockForUpdate()->first();
        if ($user === null) {
            throw new RuntimeException('Loan application fee charge has no resolvable user.');
        }

        if ($this->hasUnresolvedApplication($user, (string) $transaction->reference)) {
            throw new RuntimeException('The user already has another unresolved loan application or fee payment.');
        }

        $meta = (array) $transaction->meta;
        $type = LoanType::tryFrom((string) ($meta['loan_type'] ?? ''));
        $amountUsd = round((float) ($meta['loan_amount_usd'] ?? 0), 2);
        $minAmount = max(1.0, (float) ($meta['min_amount_usd'] ?? 1));
        $maxAmount = (float) ($meta['max_amount_usd'] ?? 0);
        $dueDate = (string) ($meta['due_date'] ?? '');
        $agreementAcceptedAt = (string) ($meta['agreement_accepted_at'] ?? '');
        $accountName = trim((string) ($meta['disbursement_account_name'] ?? ''));
        $accountNumber = trim((string) ($meta['disbursement_account_number'] ?? ''));
        $bankName = trim((string) ($meta['disbursement_bank_name'] ?? ''));

        if ($type === null || $amountUsd < $minAmount || $maxAmount < $minAmount || $maxAmount < $amountUsd || blank($dueDate) || blank($agreementAcceptedAt)
            || blank($accountName) || strlen($accountName) > 120
            || ! preg_match('/^[A-Za-z0-9][A-Za-z0-9 -]{3,33}$/D', $accountNumber)
            || blank($bankName) || strlen($bankName) > 120) {
            throw new RuntimeException('The verified loan-fee charge does not contain a valid application snapshot.');
        }

        try {
            $dueDate = Carbon::parse($dueDate)->toDateString();
            $acceptedAt = Carbon::parse($agreementAcceptedAt)->toIso8601String();
        } catch (Throwable) {
            throw new RuntimeException('The verified loan-fee charge contains an invalid application date.');
        }

        $currency = strtoupper((string) ($meta['currency'] ?? ''));
        $feeAmount = round((float) ($meta['application_fee_amount'] ?? 0), 2);
        $feeType = (string) ($meta['application_fee_type'] ?? 'fixed');
        $feePercentage = isset($meta['application_fee_percentage']) ? (float) $meta['application_fee_percentage'] : null;
        if (! in_array($currency, ['NGN', 'USD'], true)
            || $feeAmount <= 0
            || ! in_array($feeType, ['fixed', 'percentage'], true)
            || ($feeType === 'percentage' && ($feePercentage === null || $feePercentage <= 0 || $feePercentage > 100))) {
            throw new RuntimeException('The verified loan-fee charge contains an invalid fee snapshot.');
        }

        $loan = $this->createLoan($user, [
            'disbursement_account_name' => $accountName,
            'disbursement_account_number' => $accountNumber,
            'disbursement_bank_name' => $bankName,
            'loan_type' => $type->value,
            'amount_usd' => $amountUsd,
            'due_date' => $dueDate,
            'agreement_accepted_at' => $acceptedAt,
            'service_fee_percent' => (float) ($meta['service_fee_percent'] ?? 0),
        ], [
            'application_fee_amount' => $feeAmount,
            'application_fee_currency' => $currency,
            'application_fee_type' => $feeType,
            'application_fee_percentage' => $feePercentage,
            'application_fee_coupon_id' => null,
            'application_fee_reference' => (string) $transaction->reference,
        ]);

        $transaction->update([
            'meta' => array_merge($meta, ['loan_id' => $loan->id]),
        ]);

        return $loan;
    }

    /**
     * @param  array<string, mixed>  $application
     * @param  array<string, mixed>  $fee
     */
    private function createLoan(User $user, array $application, array $fee): Loan
    {
        $amount = round((float) $application['amount_usd'], 2);
        $serviceFee = round($amount * (float) ($application['service_fee_percent'] ?? 0) / 100, 2);

        return Loan::query()->create([
            'user_id' => $user->id,
            'loan_type' => $application['loan_type'],
            'amount' => $amount,
            'total_repayment' => round($amount + $serviceFee, 2),
            'due_date' => $application['due_date'],
            'agreement_accepted_at' => Carbon::parse($application['agreement_accepted_at']),
            'application_fee_amount' => $fee['application_fee_amount'],
            'application_fee_currency' => $fee['application_fee_currency'],
            'application_fee_type' => $fee['application_fee_type'],
            'application_fee_percentage' => $fee['application_fee_percentage'],
            'application_fee_coupon_id' => $fee['application_fee_coupon_id'],
            'application_fee_reference' => $fee['application_fee_reference'],
            'disbursement_account_name' => $application['disbursement_account_name'],
            'disbursement_account_number' => $application['disbursement_account_number'],
            'disbursement_bank_name' => $application['disbursement_bank_name'],
            'status' => LoanStatus::PROCESSING,
        ]);
    }
}
