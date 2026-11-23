<?php

namespace App\Application\Actions\Wallets;

use App\Application\Money\CurrencyMinorUnits;
use App\Application\Money\CurrencyConverter;
use App\Models\LedgerEntry;
use App\Models\User;
use App\Models\Wallet;
use App\Models\UserSubscription;
use App\Models\WithdrawalOption;
use App\Models\WithdrawalRequest as Withdrawal;
use App\Models\SystemSetting;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class RequestWithdrawal
{
    public function __construct(private readonly CurrencyConverter $currencyConverter) {}

    public function handle(User $user, int $walletId, int $optionId, int $amountMinor, array $payoutDetails, string $idempotencyKey): Withdrawal
    {
        return DB::transaction(function () use ($user, $walletId, $optionId, $amountMinor, $payoutDetails, $idempotencyKey): Withdrawal {
            $existing = Withdrawal::query()->where('idempotency_key', $idempotencyKey)->first();
            if ($existing) {
                if ($existing->user_id !== $user->id) {
                    throw ValidationException::withMessages(['idempotency_key' => 'This idempotency key has already been used.']);
                }
                if ($existing->wallet_id !== $walletId || $existing->withdrawal_option_id !== $optionId || $existing->amount_minor !== $amountMinor) {
                    throw ValidationException::withMessages(['idempotency_key' => 'This idempotency key was already used for a different withdrawal.']);
                }
                return $existing;
            }

            $wallet = Wallet::query()->where('user_id', $user->id)->lockForUpdate()->findOrFail($walletId);
            $walletType = $wallet->wallet_type->value;
            if (! in_array($walletType, ['review', 'review_group_b', 'review_group_c', 'share', 'referral', 'bonus'], true)) {
                throw ValidationException::withMessages(['wallet_id' => 'Top-up funds cannot be withdrawn.']);
            }
            $isReferralOrBonusWallet = in_array($walletType, ['referral', 'bonus'], true);
            if ($isReferralOrBonusWallet && ! $this->walletWithdrawalsEnabled($walletType)) {
                throw ValidationException::withMessages(['wallet_id' => 'Withdrawals from this wallet are currently disabled.']);
            }
            $option = WithdrawalOption::query()->where('is_available', true)->findOrFail($optionId);
            $requiredGroup = match ($walletType) {
                'share' => 'group_a',
                'review_group_c' => 'group_c',
                'review_group_b', 'review' => 'group_b',
                default => null,
            };
            $subscriptionQuery = UserSubscription::query()->with('plan.group')
                ->where('user_id', $user->id)->where('product_type', 'marketplace_access')
                ->where('status', 'active')->where('starts_at', '<=', now())->where('ends_at', '>', now());
            if ($requiredGroup !== null) {
                $subscriptionQuery->whereHas('plan.group', fn ($query) => $query->where('group_key', $requiredGroup));
            }
            $subscription = $isReferralOrBonusWallet ? null : $subscriptionQuery->latest('starts_at')->first();
            if (! $isReferralOrBonusWallet && ! $subscription?->plan) {
                throw ValidationException::withMessages(['subscription' => $requiredGroup === null
                    ? 'An active package is required to request a withdrawal.'
                    : 'An active package in the matching group is required to withdraw from this wallet.']);
            }
            $plan = $subscription?->plan;
            $withdrawalDays = $plan?->withdrawal_days ?? [];
            if (! $isReferralOrBonusWallet && ! $this->isWithdrawalDay($withdrawalDays, now())) {
                throw ValidationException::withMessages(['withdrawal' => 'Your package allows withdrawals on: '.implode(' and ', $withdrawalDays).'.']);
            }
            $requiredFields = $option->fields ?? [];
            $payoutDetails = array_intersect_key($payoutDetails, array_flip($requiredFields));
            $savedPayoutProfile = $user->payoutProfile()->where('withdrawal_option_id', $option->id)->first();
            if ($savedPayoutProfile) {
                $payoutDetails = array_merge($payoutDetails, array_intersect_key(
                    $savedPayoutProfile->details ?? [],
                    array_flip(['bank_id', 'bank_code']),
                ));
            }
            $missing = array_values(array_filter($requiredFields, fn ($field) => ! isset($payoutDetails[$field]) || trim((string) $payoutDetails[$field]) === ''));
            if ($missing !== []) {
                throw ValidationException::withMessages(['payout_details' => 'Required payout fields are missing: '.implode(', ', $missing)]);
            }

            $minimumMinor = $this->currencyConverter->convertMinor(
                CurrencyMinorUnits::toMinor($option->min_withdrawal, $option->currency ?: 'USD'),
                $option->currency ?: 'USD',
                $wallet->currency,
            )['amount_minor'];
            $packageMinimumMinor = $plan ? $this->currencyConverter->convertMinor(
                (int) ($plan->minimum_withdrawal_minor ?? 0),
                $plan->currency,
                $wallet->currency,
            )['amount_minor'] : 0;
            $minimumMinor = max($minimumMinor, $packageMinimumMinor);
            if ($amountMinor < $minimumMinor) {
                throw ValidationException::withMessages(['amount_minor' => 'The requested amount is below the package or payout method minimum.']);
            }
            $feeMinor = (int) round($amountMinor * (float) $option->tax / 100);
            $payoutMinor = $amountMinor - $feeMinor;
            if ($payoutMinor < 1) {
                throw ValidationException::withMessages(['amount_minor' => 'The withdrawal amount must be greater than its percentage fee.']);
            }
            if ((int) $wallet->balance_minor < $amountMinor) {
                throw ValidationException::withMessages(['amount_minor' => 'The selected wallet has insufficient available funds for the requested withdrawal amount.']);
            }

            $withdrawal = Withdrawal::query()->create([
                'user_id' => $user->id, 'wallet_id' => $wallet->id, 'user_subscription_id' => $subscription?->id,
                'package_withdrawal_terms_snapshot' => $plan ? [
                    'plan_key' => $plan->plan_key, 'plan_name' => $plan->name,
                    'currency' => $wallet->currency, 'source_currency' => $plan->currency,
                    'minimum_withdrawal_minor' => $packageMinimumMinor, 'withdrawal_days' => $withdrawalDays,
                ] : null,
                'withdrawal_option_id' => $option->id,
                'amount_minor' => $amountMinor, 'fee_minor' => $feeMinor, 'currency' => $wallet->currency,
                'payout_details' => $payoutDetails, 'status' => 'pending', 'idempotency_key' => $idempotencyKey,
                'requested_at' => now(),
            ]);
            LedgerEntry::query()->create([
                'wallet_id' => $wallet->id, 'entry_type' => 'withdrawal_reservation', 'direction' => 'debit',
                'amount_minor' => $amountMinor, 'status' => 'reserved', 'source_type' => 'withdrawal_request',
                'source_id' => $withdrawal->id, 'idempotency_key' => 'withdrawal-reservation:'.hash('sha256', $idempotencyKey),
                'description' => 'Withdrawal request',
                'metadata' => [
                    'withdrawal_amount_minor' => $amountMinor,
                    'fee_minor' => $feeMinor,
                    'payout_minor' => $payoutMinor,
                    'fee_percent' => (float) $option->tax,
                ],
            ]);

            return $withdrawal->load('option');
        });
    }

    private function isWithdrawalDay(array $days, \Illuminate\Support\Carbon $date): bool
    {
        if ($days === []) return true;
        foreach ($days as $day) {
            $day = strtolower(trim((string) $day));
            if (preg_match('/^monthly\s+(\d{1,2})(?:st|nd|rd|th)?$/', $day, $matches) && $date->day === (int) $matches[1]) return true;
            if (strtolower($date->englishDayOfWeek) === $day) return true;
        }

        return false;
    }

    private function walletWithdrawalsEnabled(string $walletType): bool
    {
        $value = SystemSetting::query()->where('setting_key', 'withdrawal.wallet.'.$walletType)->value('value');
        if ($value === null) {
            return $walletType === 'referral';
        }
        if (is_array($value)) {
            $value = $value['active'] ?? false;
        }

        return filter_var($value, FILTER_VALIDATE_BOOLEAN);
    }
}
