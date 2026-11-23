<?php

namespace App\Services;

use App\Enums\CouponType;
use App\Enums\TransactionType;
use App\Enums\WithdrawalStatus;
use App\Models\Coupon;
use App\Models\Transaction;
use App\Models\User;
use App\Models\Withdrawal;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Withdrawal business rules shared by the web (Livewire) and API surfaces.
 * Extracted from App\Livewire\Dashboard\Withdrawals\Withdraw. All amounts are
 * USD — the canonical wallet currency; display-currency conversion stays a
 * client concern.
 */
class WithdrawalService
{
    /** Error shown when a withdrawal code isn't bound to the user — it must be activated
     *  (Special Coupon page) before it can authorize a withdrawal. Shared with the web
     *  component so it can offer the activation link alongside the message. */
    public const CODE_NOT_LINKED_MESSAGE = 'This code is not linked to your account. Activate it on the Special Coupon page first.';

    public function __construct(
        private CurrencyService $currency,
        private PayLaterService $payLater,
        private CloudInboxService $inbox,
    ) {}

    /** Wallets shown to this user (cloud wallets only when entitled or funded). */
    public function applicableWalletKeys(User $user): array
    {
        return collect(User::SPENDABLE_WALLETS)
            ->filter(fn (string $key): bool => match ($key) {
                'cloud_call' => $user->canCloudCall() || (float) $user->cloud_call_balance > 0,
                'cloud_sms' => $user->canCloudSms() || (float) $user->cloud_sms_balance > 0,
                default => true,
            })
            ->values()
            ->all();
    }

    /** Wallets currently open for withdrawal (enabled + country access). */
    public function withdrawableWalletKeys(User $user): array
    {
        $isNigerian = $user->isNigerian();

        return collect($this->applicableWalletKeys($user))
            ->filter(fn (string $key): bool => (bool) config("settings.withdrawal.wallets.{$key}.enabled", true)
                && $this->countryAccessAllows((string) config("settings.withdrawal.wallets.{$key}.country_access", 'all'), $isNigerian))
            ->values()
            ->all();
    }

    public function isOpen(User $user, string $wallet): bool
    {
        return in_array($wallet, $this->withdrawableWalletKeys($user), true);
    }

    public function minAmount(string $wallet): float
    {
        return (float) config("settings.withdrawal.wallets.{$wallet}.min", 5);
    }

    public function maxAmount(string $wallet): float
    {
        return max(0.0, (float) config("settings.withdrawal.wallets.{$wallet}.max", 0));
    }

    public function flatFee(string $wallet): float
    {
        return max(0.0, (float) config("settings.withdrawal.wallets.{$wallet}.fee", 0));
    }

    public function deductAt(string $wallet): string
    {
        return (string) config("settings.withdrawal.wallets.{$wallet}.deduct_at", 'request');
    }

    /**
     * Fee + net payout for an amount, applying the one-off N-Cloud SMS bonus
     * commission (settings.withdrawal.sms_bonus_rate; e.g. 0.5 = +50%, fee
     * waived). Mirrors the web preview exactly.
     *
     * @return array{0: float, 1: float} [fee, net]
     */
    public function payoutFor(string $wallet, float $amount): array
    {
        $fee = $amount > 0 ? $this->flatFee($wallet) : 0.0;
        $net = round($amount - $fee, 2);

        $bonus = $this->smsBonusRate($wallet);
        if ($amount > 0 && $bonus > 0) {
            $fee = 0.0;
            $net = round($amount * (1 + $bonus), 2);
        }

        return [$fee, max(0.0, $net)];
    }

    public function smsBonusRate(string $wallet): float
    {
        return $wallet === 'cloud_sms' ? (float) config('settings.withdrawal.sms_bonus_rate', 0) : 0.0;
    }

    /** N-Cloud wallets (cloud_call, cloud_sms) require a withdrawal authorization code —
     *  unless the feature is switched off in Backoffice → Settings → Features. */
    public function walletRequiresAuthorizationCode(string $wallet): bool
    {
        return (bool) config('settings.withdrawal.require_authorization_code', true)
            && in_array($wallet, ['cloud_call', 'cloud_sms'], true);
    }

    /**
     * Validate a withdrawal authorization code WITHOUT consuming it.
     * Gates the confirm screen on the web; the actual consumption happens
     * atomically inside request()'s transaction (which re-validates under a
     * row lock, so a code that slips past this check can't be double-spent).
     *
     * @throws ValidationException with the `withdrawal_code` key.
     */
    public function validateAuthorizationCode(User $user, string $code): Coupon
    {
        $codeHash = $this->hashWithdrawalCode($code);

        $coupon = Coupon::query()
            ->where('code_hash', $codeHash)
            ->where('type', CouponType::STORAGE_ACTIVATION->value)
            ->first();

        if (! $coupon) {
            throw ValidationException::withMessages([
                'withdrawal_code' => 'Invalid withdrawal authorization code.',
            ]);
        }

        if ($coupon->user_id !== $user->id) {
            throw ValidationException::withMessages([
                'withdrawal_code' => self::CODE_NOT_LINKED_MESSAGE,
            ]);
        }

        if ($coupon->used_at !== null) {
            throw ValidationException::withMessages([
                'withdrawal_code' => 'This code has already been used.',
            ]);
        }

        if ($coupon->expires_at !== null && $coupon->expires_at->isPast()) {
            throw ValidationException::withMessages([
                'withdrawal_code' => 'This code has expired.',
            ]);
        }

        return $coupon;
    }

    public function hasPendingWithdrawal(User $user, string $wallet): bool
    {
        return Withdrawal::query()
            ->where('user_id', $user->id)
            ->where('wallet', $wallet)
            ->whereIn('status', [WithdrawalStatus::PENDING, WithdrawalStatus::PROCESSING])
            ->exists();
    }

    /** All pre-flight guards; throws the same friendly errors the web shows. */
    public function guardWithdrawable(User $user, string $wallet, float $amountUsd): void
    {
        if ($user->withdrawals_disabled) {
            throw ValidationException::withMessages([
                'amount' => 'Withdrawals are disabled on your account. Please contact support.',
            ]);
        }

        if ($wallet === 'shareholder' && $this->payLater->hasOutstanding($user)) {
            throw ValidationException::withMessages([
                'amount' => 'You have an outstanding Pay Later — repay it before withdrawing your call earnings.',
            ]);
        }

        if (! in_array($wallet, $this->applicableWalletKeys($user), true)) {
            throw ValidationException::withMessages([
                'wallet' => (User::WALLET_LABELS[$wallet] ?? 'This wallet').' is not available.',
            ]);
        }

        if (! $this->isOpen($user, $wallet)) {
            throw ValidationException::withMessages([
                'amount' => (User::WALLET_LABELS[$wallet] ?? 'This wallet').' withdrawals are currently closed.',
            ]);
        }

        if (! $user->bankAccount) {
            throw ValidationException::withMessages([
                'amount' => 'Add a payout bank account before withdrawing.',
            ]);
        }

        if ($amountUsd < $this->minAmount($wallet)) {
            throw ValidationException::withMessages([
                'amount' => 'The minimum withdrawal is '.$this->minAmount($wallet).' USD.',
            ]);
        }

        if ($this->maxAmount($wallet) > 0 && $amountUsd > $this->maxAmount($wallet)) {
            throw ValidationException::withMessages([
                'amount' => 'The maximum withdrawal from this wallet is '.$this->maxAmount($wallet).' USD.',
            ]);
        }

        if ($this->hasPendingWithdrawal($user, $wallet)) {
            throw ValidationException::withMessages([
                'amount' => 'You already have a withdrawal being processed from this wallet. Please wait for it to complete.',
            ]);
        }
    }

    /**
     * Create the withdrawal under row locks. The amount is capped at the
     * available balance; the wallet is debited per the configured deduct_at.
     * For cloud wallets (cloud_call, cloud_sms), a withdrawal authorization code is required.
     */
    public function request(User $user, string $wallet, float $amountUsd, ?string $withdrawalCode = null): Withdrawal
    {
        $user->loadMissing('bankAccount.bank');
        $amountUsd = round(min($amountUsd, $user->walletBalance($wallet)), 6);

        [$fee, $net] = $this->payoutFor($wallet, $amountUsd);
        $smsBonusRate = $this->smsBonusRate($wallet);
        $column = User::WALLET_COLUMNS[$wallet];
        $bank = $user->bankAccount;
        $reference = Withdrawal::generateReference();

        $deductAt = $this->deductAt($wallet);
        $status = $deductAt === 'request' ? WithdrawalStatus::PROCESSING : WithdrawalStatus::PENDING;

        // Determine if this is a cloud wallet requiring authorization code
        $isCloudWallet = $this->walletRequiresAuthorizationCode($wallet);

        $bankSnapshot = [
            'bank_name' => $bank->bank->name,
            'bank_code' => $bank->bank->code,
            'bank_tag' => $bank->bank->tag,
            'account_number' => $bank->account_number,
            'account_name' => $bank->account_name,
        ];

        $country = $user->country;
        $rate = $this->currency->rate($country);

        $localCurrency = $rate > 0 ? $this->currency->code($country) : 'USD';
        $localSymbol = $rate > 0 ? $this->currency->symbol($country) : '$';
        $localAmount = $this->currency->toLocal($amountUsd, $country);
        $localNet = $this->currency->toLocal($net, $country);
        $currencyMeta = [
            'exchange_rate' => $rate,
            'local_currency' => $localCurrency,
            'local_symbol' => $localSymbol,
            'narration' => (string) (config('settings.payment.narration') ?: config('app.name').' Payout'),
            'country' => $country,
        ];

        $withdrawal = DB::transaction(function () use ($user, $wallet, $amountUsd, $fee, $net, $column, $bank, $reference, $status, $deductAt, $bankSnapshot, $localCurrency, $localAmount, $localNet, $currencyMeta, $smsBonusRate, $isCloudWallet, $withdrawalCode): Withdrawal {
            // Lock user for balance operations
            $locked = User::whereKey($user->id)->lockForUpdate()->first();

            // For cloud wallets: lock and validate coupon INSIDE transaction
            $lockedCoupon = null;
            if ($isCloudWallet) {
                if (empty($withdrawalCode)) {
                    throw ValidationException::withMessages([
                        'withdrawal_code' => 'A withdrawal authorization code is required for N-Cloud withdrawals.',
                    ]);
                }

                $codeHash = $this->hashWithdrawalCode($withdrawalCode);
                $lockedCoupon = $this->lockAndValidateCoupon($locked, $codeHash);
            }

            if ($this->hasPendingWithdrawal($locked, $wallet)) {
                throw ValidationException::withMessages([
                    'amount' => 'You already have a withdrawal being processed from this wallet. Please wait for it to complete.',
                ]);
            }

            if ((float) $locked->{$column} < $amountUsd) {
                throw ValidationException::withMessages([
                    'amount' => 'Insufficient balance in this wallet.',
                ]);
            }

            $transactionId = null;
            if ($deductAt === 'request') {
                $locked->decrement($column, $amountUsd);

                $transactionId = Transaction::create([
                    'user_id' => $user->id,
                    'type' => TransactionType::WITHDRAWAL,
                    'direction' => 'debit',
                    'amount' => $amountUsd,
                    'fee' => $fee,
                    'wallet' => $wallet,
                    'status' => 'completed',
                    'reference' => $reference,
                    'description' => "Withdrawal to {$bank->bank->name} ({$bank->account_number})",
                    'meta' => array_merge($bankSnapshot, $currencyMeta, [
                        'wallet_label' => User::WALLET_LABELS[$wallet] ?? null,
                        'net' => $net,
                    ]),
                ])->id;
            }

            // Create withdrawal record
            $withdrawal = Withdrawal::create([
                'user_id' => $user->id,
                'reference' => $reference,
                'wallet' => $wallet,
                'amount' => $amountUsd,
                'fee' => $fee,
                'amount_paid' => $net,
                'local_currency' => $localCurrency,
                'local_amount' => $localAmount,
                'local_amount_paid' => $localNet,
                'status' => $status,
                'transaction_id' => $transactionId,
                'meta' => array_merge(
                    $bankSnapshot,
                    $currencyMeta,
                    ['deduct_at' => $deductAt],
                    // Store the coupon row id, never the raw authorization code, so a rejected
                    // withdrawal can release exactly the code it consumed.
                    $lockedCoupon !== null ? ['authorization_coupon_id' => $lockedCoupon->id] : [],
                    $smsBonusRate > 0 ? ['sms_bonus_rate' => $smsBonusRate, 'sms_bonus' => round($amountUsd * $smsBonusRate, 2)] : [],
                ),
            ]);

            // Mark coupon as used for withdrawal authorization (LAST STEP)
            if ($lockedCoupon) {
                $lockedCoupon->update(['used_at' => now()]);
            }

            return $withdrawal;
        });

        $this->inbox->notify(
            $user,
            'Withdrawal received',
            'We received your withdrawal request of '.money($net).' to '.$bank->bank->name.' ('.$bank->masked_number.'). It is being processed.',
            ['reference' => $reference],
        );

        return $withdrawal;
    }

    private function countryAccessAllows(string $access, bool $isNigerian): bool
    {
        return match ($access) {
            'nigerian' => $isNigerian,
            'non_nigerian' => ! $isNigerian,
            default => true,
        };
    }

    /**
     * Generate HMAC-SHA256 hash of withdrawal code for secure lookup.
     */
    protected function hashWithdrawalCode(string $code): string
    {
        $normalized = strtoupper(trim($code));
        return hash_hmac('sha256', $normalized, config('app.key'));
    }

    /**
     * Lock and validate the withdrawal authorization coupon.
     * Returns the locked coupon or throws ValidationException.
     */
    protected function lockAndValidateCoupon(User $user, string $codeHash): ?Coupon
    {
        $coupon = Coupon::query()
            ->where('code_hash', $codeHash)
            ->where('type', CouponType::STORAGE_ACTIVATION->value)
            ->where('user_id', $user->id)
            ->whereNull('used_at')
            ->lockForUpdate()
            ->first();

        if (!$coupon) {
            throw ValidationException::withMessages([
                'withdrawal_code' => 'Invalid withdrawal authorization code.',
            ]);
        }

        // Re-check after lock (explicit validation)
        if ($coupon->used_at !== null) {
            throw ValidationException::withMessages([
                'withdrawal_code' => 'This code has already been used.',
            ]);
        }

        if ($coupon->expires_at && $coupon->expires_at->isPast()) {
            throw ValidationException::withMessages([
                'withdrawal_code' => 'This code has expired.',
            ]);
        }

        return $coupon;
    }
}
