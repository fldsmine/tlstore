<?php

namespace App\Application\Actions\Wallets;

use App\Application\Money\CurrencyMinorUnits;
use App\Models\LedgerEntry;
use App\Models\User;
use App\Models\Wallet;
use App\Models\UserSubscription;
use App\Models\WithdrawalOption;
use App\Models\WithdrawalRequest as Withdrawal;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class RequestWithdrawal
{
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
            if (! in_array($wallet->wallet_type->value, ['review', 'share', 'referral', 'bonus'], true)) {
                throw ValidationException::withMessages(['wallet_id' => 'Top-up funds cannot be withdrawn.']);
            }
            $option = WithdrawalOption::query()->where('is_available', true)->findOrFail($optionId);
            $subscription = UserSubscription::query()->with('plan.group')
                ->where('user_id', $user->id)->where('product_type', 'marketplace_access')
                ->where('status', 'active')->where('starts_at', '<=', now())->where('ends_at', '>', now())
                ->latest('ends_at')->first();
            if (! $subscription?->plan) {
                throw ValidationException::withMessages(['subscription' => 'An active package is required to request a withdrawal.']);
            }
            $plan = $subscription->plan;
            if (strtoupper($wallet->currency) !== strtoupper($plan->currency)) {
                throw ValidationException::withMessages(['wallet_id' => 'Withdrawals must use a wallet in your active package currency ('.$plan->currency.').']);
            }
            $withdrawalDays = $plan->withdrawal_days ?? [];
            if (! $this->isWithdrawalDay($withdrawalDays, now())) {
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

            $minimumMinor = CurrencyMinorUnits::toMinor($option->min_withdrawal, $wallet->currency);
            $packageMinimumMinor = (int) ($plan->minimum_withdrawal_minor ?? 0);
            $minimumMinor = max($minimumMinor, $packageMinimumMinor);
            if ($amountMinor < $minimumMinor) {
                throw ValidationException::withMessages(['amount_minor' => 'The requested amount is below the package or payout method minimum.']);
            }
            $feeMinor = (int) round($amountMinor * (float) $option->tax / 100);
            $debitMinor = $amountMinor + $feeMinor;
            if ((int) $wallet->balance_minor < $debitMinor) {
                throw ValidationException::withMessages(['amount_minor' => 'The selected wallet has insufficient available funds for amount and percentage fee.']);
            }

            $withdrawal = Withdrawal::query()->create([
                'user_id' => $user->id, 'wallet_id' => $wallet->id, 'user_subscription_id' => $subscription->id,
                'package_withdrawal_terms_snapshot' => [
                    'plan_key' => $plan->plan_key, 'plan_name' => $plan->name, 'currency' => $plan->currency,
                    'minimum_withdrawal_minor' => $packageMinimumMinor, 'withdrawal_days' => $withdrawalDays,
                ],
                'withdrawal_option_id' => $option->id,
                'amount_minor' => $amountMinor, 'fee_minor' => $feeMinor, 'currency' => $wallet->currency,
                'payout_details' => $payoutDetails, 'status' => 'pending', 'idempotency_key' => $idempotencyKey,
                'requested_at' => now(),
            ]);
            LedgerEntry::query()->create([
                'wallet_id' => $wallet->id, 'entry_type' => 'withdrawal_reservation', 'direction' => 'debit',
                'amount_minor' => $debitMinor, 'status' => 'reserved', 'source_type' => 'withdrawal_request',
                'source_id' => $withdrawal->id, 'idempotency_key' => 'withdrawal-reservation:'.hash('sha256', $idempotencyKey),
                'description' => 'Withdrawal amount plus percentage fee',
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
}
