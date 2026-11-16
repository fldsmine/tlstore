<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\Relation;

trait TrustLensMorphMap
{
    protected static function bootTrustLensMorphMap(): void
    {
        Relation::morphMap([
            'user' => User::class,
            'demo_fixture' => User::class,
            'admin' => Admin::class,
            'app' => App::class,
            'app_screenshot' => AppScreenshot::class,
            'country' => Country::class,
            'region' => Region::class,
            'system_setting' => SystemSetting::class,
            'withdrawal_option' => WithdrawalOption::class,
            'ad' => Ad::class,
            'subscription_plan' => SubscriptionPlan::class,
            'package_addon' => PackageAddon::class,
            'app_review_campaign' => AppReviewCampaign::class,
            'app_report' => AppReport::class,
            'user_campaign_order' => UserCampaignOrder::class,
            'app_store_listing_snapshot' => AppStoreListingSnapshot::class,
            'review_question' => ReviewQuestion::class,
            'review_template' => ReviewTemplate::class,
            'review_template_version' => ReviewTemplateVersion::class,
            'review_task' => ReviewTask::class,
            'review_submission' => ReviewSubmission::class,
            'ad_share_event' => AdShareEvent::class,
            'ad_share_task' => AdShareTask::class,
            'payment_intent' => PaymentIntent::class,
            'manual_deposit_submission' => ManualDepositSubmission::class,
            'user_subscription' => UserSubscription::class,
            'referral_attribution' => ReferralAttribution::class,
            'withdrawal_request' => WithdrawalRequest::class,
            'notification' => Notification::class,
            'user_social_follow' => UserSocialFollow::class,
            'vtu_order' => VtuOrder::class,
        ]);
    }
}
