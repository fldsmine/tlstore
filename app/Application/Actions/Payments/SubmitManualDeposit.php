<?php

namespace App\Application\Actions\Payments;

use App\Application\Support\PaymentGatewaySettings;
use App\Models\ManualDepositSubmission;
use App\Models\PaymentIntent;
use App\Models\Notification;
use App\Models\User;
use App\Models\Transaction;
use App\Application\Money\CurrencyMinorUnits;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class SubmitManualDeposit
{
    public function handle(User $user, int $amountMinor, string $currency, string $idempotencyKey, string $proofPath, ?string $bankReference): ManualDepositSubmission
    {
        return DB::transaction(function () use ($user, $amountMinor, $currency, $idempotencyKey, $proofPath, $bankReference): ManualDepositSubmission {
            if (! array_key_exists('manual', PaymentGatewaySettings::all())) {
                throw ValidationException::withMessages(['gateway' => 'Manual bank deposits are not currently active.']);
            }

            $key = 'manual-topup:'.$user->id.':'.hash('sha256', $idempotencyKey);
            $intent = PaymentIntent::query()->where('idempotency_key', $key)->first();
            if ($intent) {
                if ($intent->amount_minor !== $amountMinor || $intent->currency !== strtoupper($currency)) {
                    throw ValidationException::withMessages(['idempotency_key' => 'This idempotency key was already used for a different deposit.']);
                }
                return ManualDepositSubmission::query()->where('payment_intent_id', $intent->id)->firstOrFail();
            }

            $intent = PaymentIntent::query()->create([
                'user_id' => $user->id, 'purpose' => 'topup', 'amount_minor' => $amountMinor,
                'currency' => strtoupper($currency), 'provider' => 'manual', 'idempotency_key' => $key,
                'status' => 'pending',
            ]);

            $deposit = ManualDepositSubmission::query()->create([
                'payment_intent_id' => $intent->id, 'user_id' => $user->id,
                'amount_minor' => $amountMinor, 'currency' => strtoupper($currency),
                'bank_reference' => $bankReference, 'proof_storage_path' => $proofPath,
                'status' => 'submitted', 'submitted_at' => now(),
            ]);

            Transaction::query()->updateOrCreate(['reference' => 'payment-intent:'.$intent->id], [
                'user_id' => $user->id,
                'type' => 'top_up_fund',
                'direction' => 'credit',
                'amount' => CurrencyMinorUnits::fromMinor($amountMinor, strtoupper($currency)),
                'fee' => 0,
                'wallet' => 'topup_fund',
                'status' => 'pending',
                'description' => 'Manual bank deposit top-up',
                'meta' => [
                    'payment_intent_id' => $intent->id,
                    'manual_deposit_id' => $deposit->id,
                    'currency' => strtoupper($currency),
                    'currency_minor_units' => CurrencyMinorUnits::precision(strtoupper($currency)),
                    'amount_minor' => $amountMinor,
                    'provider' => 'manual',
                ],
            ]);

            Notification::query()->firstOrCreate(
                ['notification_key' => 'manual-deposit-submitted-'.$deposit->id],
                [
                    'user_id' => $user->id,
                    'type' => 'manual_deposit_submitted',
                    'title' => 'Top-up proof received',
                    'body' => 'Your manual top-up proof was received and is awaiting admin approval.',
                    'data' => ['manual_deposit_id' => $deposit->id, 'payment_intent_id' => $intent->id, 'amount_minor' => $amountMinor, 'amount' => number_format(CurrencyMinorUnits::fromMinor($amountMinor, strtoupper($currency)), CurrencyMinorUnits::precision(strtoupper($currency))), 'currency' => strtoupper($currency), 'reference' => (string) ($bankReference ?: $intent->id), 'status' => 'submitted'],
                ],
            );

            return $deposit;
        });
    }
}
