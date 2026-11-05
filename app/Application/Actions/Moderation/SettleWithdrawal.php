<?php

namespace App\Application\Actions\Moderation;

use App\Models\Admin;
use App\Models\AuditLog;
use App\Models\LedgerEntry;
use App\Models\Notification;
use App\Models\WithdrawalRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class SettleWithdrawal
{
    public function handle(Admin $admin, WithdrawalRequest $withdrawal, string $decision, ?string $providerReference, ?string $reason): WithdrawalRequest
    {
        return DB::transaction(function () use ($admin, $withdrawal, $decision, $providerReference, $reason): WithdrawalRequest {
            $withdrawal = WithdrawalRequest::query()->lockForUpdate()->findOrFail($withdrawal->id);
            if ($withdrawal->status->value !== 'pending') {
                throw ValidationException::withMessages(['withdrawal' => 'This withdrawal is no longer pending.']);
            }
            if (! in_array($decision, ['paid', 'rejected'], true)) {
                throw ValidationException::withMessages(['decision' => 'Decision must be paid or rejected.']);
            }

            $now = now();
            $reservation = LedgerEntry::query()->where('source_type', 'withdrawal_request')
                ->where('source_id', $withdrawal->id)->where('entry_type', 'withdrawal_reservation')->lockForUpdate()->firstOrFail();
            if ($decision === 'paid') {
                $reservation->update(['status' => 'posted', 'posted_at' => $now, 'available_at' => $now]);
            } else {
                $reservation->update(['status' => 'posted', 'posted_at' => $now, 'available_at' => $now]);
                LedgerEntry::query()->firstOrCreate(['idempotency_key' => 'withdrawal-release:'.$withdrawal->id], [
                    'wallet_id' => $withdrawal->wallet_id,
                    'entry_type' => 'withdrawal_release', 'direction' => 'credit',
                    'amount_minor' => $reservation->amount_minor, 'status' => 'posted',
                    'source_type' => 'withdrawal_request', 'source_id' => $withdrawal->id,
                    'description' => 'Release reserved funds for rejected withdrawal',
                    'available_at' => $now, 'posted_at' => $now,
                ]);
            }

            $withdrawal->update([
                'status' => $decision,
                'processed_by_admin_id' => $admin->id,
                'provider_reference' => $providerReference,
                'failure_reason' => $decision === 'rejected' ? $reason : null,
                'processed_at' => $now,
            ]);
            Notification::query()->firstOrCreate(
                ['notification_key' => 'withdrawal-decision-'.$withdrawal->id],
                [
                    'user_id' => $withdrawal->user_id,
                    'type' => 'withdrawal_decision',
                    'title' => $decision === 'paid' ? 'Withdrawal processed' : 'Withdrawal not approved',
                    'body' => $decision === 'paid'
                        ? 'Your withdrawal has been marked as paid.'
                        : 'Your withdrawal was not approved. '.($reason ?? 'Please contact support for more information.'),
                    'data' => ['withdrawal_id' => $withdrawal->id, 'status' => $decision],
                ],
            );
            AuditLog::query()->create([
                'actor_admin_id' => $admin->id, 'action' => 'withdrawal.'.$decision,
                'entity_type' => 'withdrawal_request', 'entity_id' => (string) $withdrawal->id,
                'after_values' => ['decision' => $decision, 'provider_reference' => $providerReference, 'reason' => $reason],
                'created_at' => $now,
            ]);

            return $withdrawal->refresh();
        });
    }
}
