<?php

namespace App\Application\Actions\Wallets;

use App\Application\Money\CurrencyMinorUnits;
use App\Models\LedgerEntry;
use App\Models\User;
use App\Models\Wallet;
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
            $requiredFields = $option->fields ?? [];
            $payoutDetails = array_intersect_key($payoutDetails, array_flip($requiredFields));
            $missing = array_values(array_filter($requiredFields, fn ($field) => ! isset($payoutDetails[$field]) || trim((string) $payoutDetails[$field]) === ''));
            if ($missing !== []) {
                throw ValidationException::withMessages(['payout_details' => 'Required payout fields are missing: '.implode(', ', $missing)]);
            }

            $minimumMinor = CurrencyMinorUnits::toMinor($option->min_withdrawal, $wallet->currency);
            if ($amountMinor < $minimumMinor) {
                throw ValidationException::withMessages(['amount_minor' => 'The requested amount is below this method minimum.']);
            }
            $feeMinor = (int) round($amountMinor * (float) $option->tax / 100);
            $debitMinor = $amountMinor + $feeMinor;
            $credits = (int) $wallet->ledgerEntries()->where('direction', 'credit')->where('status', 'posted')->sum('amount_minor');
            $debits = (int) $wallet->ledgerEntries()->where('direction', 'debit')->whereIn('status', ['posted', 'reserved'])->sum('amount_minor');
            if ($credits - $debits < $debitMinor) {
                throw ValidationException::withMessages(['amount_minor' => 'The selected wallet has insufficient available funds for amount and percentage fee.']);
            }

            $withdrawal = Withdrawal::query()->create([
                'user_id' => $user->id, 'wallet_id' => $wallet->id, 'withdrawal_option_id' => $option->id,
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
}
