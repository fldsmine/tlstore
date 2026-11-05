<?php

namespace App\Application\Actions\Moderation;

use App\Models\Admin;
use App\Models\AuditLog;
use App\Models\LedgerEntry;
use App\Models\ManualDepositSubmission;
use App\Models\Notification;
use App\Models\PaymentTransaction;
use App\Models\Wallet;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class DecideManualDeposit
{
    public function handle(Admin $admin, ManualDepositSubmission $deposit, string $decision, ?string $reason): ManualDepositSubmission
    {
        return DB::transaction(function () use ($admin, $deposit, $decision, $reason): ManualDepositSubmission {
            $deposit = ManualDepositSubmission::query()->with('paymentIntent')->lockForUpdate()->findOrFail($deposit->id);
            if ($deposit->status !== 'submitted') {
                throw ValidationException::withMessages(['deposit' => 'This deposit has already been reviewed.']);
            }
            if (! in_array($decision, ['approved', 'rejected'], true)) {
                throw ValidationException::withMessages(['decision' => 'Decision must be approved or rejected.']);
            }

            $now = now();
            $deposit->update([
                'status' => $decision,
                'reviewed_by_admin_id' => $admin->id,
                'review_note' => $reason,
                'reviewed_at' => $now,
            ]);
            $deposit->paymentIntent->update([
                'status' => $decision === 'approved' ? 'paid' : 'failed',
                'paid_at' => $decision === 'approved' ? $now : null,
            ]);

            Notification::query()->firstOrCreate(
                ['notification_key' => 'manual-deposit-decision-'.$deposit->id],
                [
                    'user_id' => $deposit->user_id,
                    'type' => 'manual_deposit_decision',
                    'title' => $decision === 'approved' ? 'Top-up approved' : 'Top-up not approved',
                    'body' => $decision === 'approved'
                        ? 'Your bank deposit was approved and your top-up funds are available.'
                        : 'Your bank deposit was not approved. '.($reason ?? 'Please review the payment details.'),
                    'data' => ['manual_deposit_id' => $deposit->id, 'status' => $decision],
                ],
            );

            if ($decision === 'approved') {
                $wallet = Wallet::query()->firstOrCreate([
                    'user_id' => $deposit->user_id, 'wallet_type' => 'topup_fund', 'currency' => $deposit->currency,
                ], ['status' => 'active']);
                PaymentTransaction::query()->create([
                    'payment_intent_id' => $deposit->payment_intent_id,
                    'provider_transaction_id' => 'manual-deposit-'.$deposit->payment_intent_id,
                    'transaction_type' => 'topup', 'amount_minor' => $deposit->amount_minor,
                    'currency' => $deposit->currency, 'status' => 'succeeded', 'occurred_at' => $now,
                ]);
                LedgerEntry::query()->create([
                    'wallet_id' => $wallet->id, 'entry_type' => 'manual_topup', 'direction' => 'credit',
                    'amount_minor' => $deposit->amount_minor, 'status' => 'posted', 'source_type' => 'payment_intent',
                    'source_id' => $deposit->payment_intent_id,
                    'idempotency_key' => 'manual-deposit-approved:'.$deposit->payment_intent_id,
                    'description' => 'Manually verified bank deposit', 'available_at' => $now, 'posted_at' => $now,
                ]);
            }

            AuditLog::query()->create([
                'actor_admin_id' => $admin->id, 'action' => 'manual_deposit.'.$decision,
                'entity_type' => 'manual_deposit_submission', 'entity_id' => (string) $deposit->id,
                'after_values' => ['decision' => $decision, 'reason' => $reason], 'created_at' => $now,
            ]);

            return $deposit->refresh();
        });
    }
}
