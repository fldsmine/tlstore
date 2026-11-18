<?php

namespace App\Application\Actions\Moderation;

use App\Application\Actions\Referrals\CreditReferralReward;
use App\Application\Actions\Wallets\CreditUserWallet;
use App\Enums\WalletType;
use App\Application\Money\CurrencyMinorUnits;
use App\Models\Ad;
use App\Models\AdShareEvent;
use App\Models\AdShareTask;
use App\Models\Admin;
use App\Models\AuditLog;
use App\Models\ModerationDecision;
use App\Models\Notification;
use App\Models\SubscriptionPlan;
use App\Models\Transaction;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class DecideAdShare
{
    public function handle(Admin $admin, AdShareEvent $event, string $decision, ?string $reasonCode, ?string $reason, ?SubscriptionPlan $referralPlan = null): AdShareEvent
    {
        return DB::transaction(function () use ($admin, $event, $decision, $reasonCode, $reason, $referralPlan): AdShareEvent {
            $event = AdShareEvent::query()->with('task')->lockForUpdate()->findOrFail($event->id);
            if ($event->status->value !== 'pending_validation') {
                throw ValidationException::withMessages(['share' => 'This share has already been decided.']);
            }
            if (! in_array($decision, ['approved', 'rejected'], true)) {
                throw ValidationException::withMessages(['decision' => 'Decision must be approved or rejected.']);
            }

            /** @var Ad $ad */
            $ad = Ad::query()->lockForUpdate()->findOrFail($event->task->ad_id);
            $now = now();
            ModerationDecision::query()->create([
                'decision_key' => 'share-'.$event->id.'-'.\Illuminate\Support\Str::uuid(),
                'review_submission_id' => null,
                'ad_share_event_id' => $event->id,
                'decided_by' => null,
                'decided_by_admin_id' => $admin->id,
                'decision' => $decision,
                'reason_code' => $reasonCode,
                'reason' => $reason,
                'decided_at' => $now,
            ]);

            $event->update([
                'status' => $decision === 'approved' ? 'validated' : 'rejected',
                'validated_at' => $decision === 'approved' ? $now : null,
            ]);
            Transaction::query()->where('reference', 'ad-share-event:'.$event->id)->update([
                'status' => $decision,
                'updated_at' => $now,
            ]);
            $event->task->update(['status' => $decision === 'approved' ? 'completed' : 'cancelled']);
            Notification::query()->firstOrCreate(
                ['notification_key' => 'ad-share-decision-'.$event->id],
                [
                    'user_id' => $event->task->user_id,
                    'type' => 'ad_share_decision',
                    'title' => $decision === 'approved' ? 'Ad share approved' : 'Ad share not approved',
                    'body' => $decision === 'approved'
                        ? 'Your share for "'.$ad->title.'" was approved. The reward was added to your Share wallet.'
                        : 'Your share for "'.$ad->title.'" was not approved. '.($reason ?? 'Please review the campaign requirements.'),
                    'data' => [
                        'ad_share_event_id' => $event->id,
                        'ad_share_task_id' => $event->task->id,
                        'status' => $decision === 'approved' ? 'validated' : 'rejected',
                    ],
                ],
            );
            $ad->reserved_share = max(0, $ad->reserved_share - 1);

            if ($decision === 'approved') {
                $ad->total_share++;
                app(CreditUserWallet::class)->handle(
                    $event->task->user,
                    WalletType::Share,
                    (int) $event->task->reserved_reward_minor,
                    $ad->currency,
                    'ad_share_reward',
                    'ad_share_event',
                    $event->id,
                    'ad-share-approved-'.$event->id,
                    'Approved public ad share reward',
                    $now,
                );
                app(CreditReferralReward::class)->handle(
                    $event->task->user, (int) $event->task->reserved_reward_minor, $ad->currency, 'task',
                    'ad_share_event', $event->id, 'referral-ad-share:'.$event->id,
                    'Referral reward for approved ad share', $referralPlan, $event->shared_at,
                );
            }

            $usedMinor = CurrencyMinorUnits::toMinor($ad->total_share * (float) $ad->share_reward, $ad->currency);
            $budgetMinor = CurrencyMinorUnits::toMinor($ad->paid_amount, $ad->currency);
            if (($ad->maximum_share !== null && $ad->total_share >= $ad->maximum_share)
                || $usedMinor >= $budgetMinor) {
                $ad->status = 'exhausted';
            }
            $ad->save();

            AuditLog::query()->create([
                'actor_admin_id' => $admin->id,
                'action' => 'ad_share.'.$decision,
                'entity_type' => 'ad_share_event',
                'entity_id' => (string) $event->id,
                'after_values' => ['decision' => $decision, 'reason_code' => $reasonCode, 'reason' => $reason],
                'created_at' => $now,
            ]);

            return $event->refresh();
        });
    }
}
