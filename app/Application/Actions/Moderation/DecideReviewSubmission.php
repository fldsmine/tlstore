<?php

namespace App\Application\Actions\Moderation;

use App\Application\Actions\Referrals\CreditReferralReward;
use App\Application\Actions\Wallets\CreditUserWallet;
use App\Enums\WalletType;
use App\Models\Admin;
use App\Models\AppReviewCampaign;
use App\Models\AuditLog;
use App\Models\App as StoreApp;
use App\Models\ModerationDecision;
use App\Models\Notification;
use App\Models\ReviewSubmission;
use App\Models\SubscriptionPlan;
use App\Models\Transaction;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class DecideReviewSubmission
{
    public function handle(Admin $admin, ReviewSubmission $submission, string $decision, ?string $reasonCode, ?string $reason, ?SubscriptionPlan $referralPlan = null): ReviewSubmission
    {
        return DB::transaction(function () use ($admin, $submission, $decision, $reasonCode, $reason, $referralPlan): ReviewSubmission {
            $submission = ReviewSubmission::query()->with('task.campaign.app')->lockForUpdate()->findOrFail($submission->id);
            if ($submission->status->value !== 'pending_review') {
                throw ValidationException::withMessages(['submission' => 'This review has already been decided.']);
            }
            if (! in_array($decision, ['approved', 'rejected'], true)) {
                throw ValidationException::withMessages(['decision' => 'Decision must be approved or rejected.']);
            }

            /** @var AppReviewCampaign $campaign */
            $campaign = AppReviewCampaign::query()->lockForUpdate()->findOrFail($submission->task->app_review_campaign_id);
            $app = $submission->task->campaign?->app;
            $rewardCurrency = strtoupper((string) ($submission->task->reward_currency ?: $campaign->currency));
            $appName = trim((string) $app?->name) !== '' ? trim((string) $app->name) : 'the app';
            $now = now();
            ModerationDecision::query()->create([
                'decision_key' => 'review-'.$submission->id.'-'.\Illuminate\Support\Str::uuid(),
                'review_submission_id' => $submission->id,
                'ad_share_event_id' => null,
                'decided_by' => null,
                'decided_by_admin_id' => $admin->id,
                'decision' => $decision,
                'reason_code' => $reasonCode,
                'reason' => $reason,
                'decided_at' => $now,
            ]);

            $submission->update(['status' => $decision, 'decided_at' => $now]);
            $submission->task->update(['status' => $decision === 'approved' ? 'completed' : 'cancelled']);
            Transaction::query()->where('reference', 'review-submission:'.$submission->id)->update([
                'status' => $decision,
                'updated_at' => $now,
            ]);
            Notification::query()->firstOrCreate(
                ['notification_key' => 'review-decision-'.$submission->id],
                [
                    'user_id' => $submission->reviewer_id,
                    'type' => 'review_decision',
                    'title' => $decision === 'approved'
                        ? 'Review approved: '.$appName
                        : 'Review rejected: '.$appName,
                    'body' => $decision === 'approved'
                        ? 'Your review of '.$appName.' was approved. The reward was added to your Review wallet.'
                        : 'Your review of '.$appName.' was rejected. '.($reason ?? 'Please review the task requirements.'),
                    'data' => [
                        'review_submission_id' => $submission->id,
                        'app_id' => $app?->id,
                        'app_name' => $appName,
                        'status' => $decision,
                        'reason' => $decision === 'rejected' ? $reason : null,
                    ],
                ],
            );
            $campaign->reserved_reviews = max(0, $campaign->reserved_reviews - 1);

            if ($decision === 'approved') {
                $referralPlan = $submission->task->subscriptionPlan ?? $referralPlan;
                $campaign->completed_reviews++;
                $app = StoreApp::query()->lockForUpdate()->findOrFail($campaign->app_id);
                $app->total_reviews = (int) ($app->total_reviews ?? 0) + 1;
                $app->save();
                app(CreditUserWallet::class)->handle(
                    $submission->reviewer,
                    WalletType::forReviewStore($app->store),
                    (int) $submission->task->reserved_reward_minor,
                    $rewardCurrency,
                    'review_reward',
                    'review_submission',
                    $submission->id,
                    'review-approved-'.$submission->id,
                    'Approved app review reward',
                    $now,
                );
                app(CreditReferralReward::class)->handle(
                    $submission->reviewer, (int) $submission->task->reserved_reward_minor, $rewardCurrency, 'task',
                    'review_submission', $submission->id, 'referral-review:'.$submission->id,
                    'Referral reward for approved app review', $referralPlan, $submission->task->claimed_at,
                );
            }

            $spentMinor = $campaign->completed_reviews * $campaign->review_reward_minor;
            if (($campaign->maximum_review !== null && $campaign->completed_reviews >= $campaign->maximum_review)
                || $spentMinor >= $campaign->paid_amount_minor) {
                $campaign->status = 'exhausted';
            }
            $campaign->save();

            AuditLog::query()->create([
                'actor_admin_id' => $admin->id,
                'action' => 'review_submission.'.$decision,
                'entity_type' => 'review_submission',
                'entity_id' => (string) $submission->id,
                'after_values' => ['decision' => $decision, 'reason_code' => $reasonCode, 'reason' => $reason],
                'created_at' => $now,
            ]);

            return $submission->refresh();
        });
    }
}
