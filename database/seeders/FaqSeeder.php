<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class FaqSeeder extends Seeder
{
    public function run(): void
    {
        $faqs = [
            ['What is TrustLens?', 'TrustLens connects people with sponsored app-feedback and advertising campaigns. Users can browse listings, complete eligible tasks, and track submissions and earnings in their account.', 'general'],
            ['How do app review tasks work?', 'Open an app listing, review its details and screenshots, then follow the task instructions and answer its review questions. Submitted answers wait for human review before any reward is approved.', 'app_reviews'],
            ['Does TrustLens require a positive app-store rating?', 'Demo campaign content should request honest feedback only. Rewards are tied to completing the campaign task and passing its review, not to giving an app a positive rating.', 'app_reviews'],
            ['How do I share a sponsored ad?', 'Open an ad listing, use its share instructions, publish the post publicly on a supported channel, and submit the public post link or requested evidence for manual verification.', 'ad_shares'],
            ['When are task rewards added to my wallet?', 'Review and ad-share submissions remain pending until an administrator approves them. Approved rewards are credited to the corresponding earned wallet.', 'earnings'],
            ['Why was my submission rejected?', 'A submission can be rejected if it does not follow the campaign instructions or the evidence cannot be verified. Check the status details and any moderator feedback, then contact support if you need help.', 'moderation'],
            ['Do I need a subscription to browse listings?', 'You can browse app and ad listings. An active marketplace subscription is required before claiming and completing tasks.', 'subscriptions'],
            ['What does a marketplace subscription include?', 'Marketplace plans define access limits and other features. Available plan details are set by administrators and shown on the subscription screen.', 'subscriptions'],
            ['What is a location add-on?', 'A location add-on expands the countries or regions in which your account can qualify for location-targeted campaigns. It grants listing eligibility; it does not use device location tracking.', 'locations'],
            ['Can I change my registration country?', 'No. Your profile country is selected during registration and cannot be changed from the account profile. It determines your initial country-based campaign eligibility.', 'locations'],
            ['How do I know if a campaign is available in my location?', 'Listings show their location targeting. Global listings are open to all eligible users; country or region listings require the matching profile country or an active location entitlement.', 'locations'],
            ['What wallets are available?', 'The account can have Review, Share, Referral, and Top-up Fund wallets. Earned rewards are held in the applicable earned wallet, while deposits are held separately in the Top-up Fund wallet.', 'wallets'],
            ['Can I withdraw money from my Top-up Fund wallet?', 'No. Top-up funds cannot be withdrawn directly. They can be used for eligible purchases or transferred without a transfer fee to an earned wallet that supports withdrawals.', 'wallets'],
            ['How do I add funds?', 'Choose an active payment option in the app. If manual bank deposit is available, submit the payment details and proof; administrators review it before funds are credited. Online checkout is available only when a gateway is configured.', 'payments'],
            ['How are subscription and location add-ons paid for?', 'Subscriptions and location add-ons are purchased from the Top-up Fund wallet. Add funds first if your available balance is not enough.', 'subscriptions'],
            ['How do I request a withdrawal?', 'Choose an available withdrawal method and an eligible earned wallet, then enter the requested amount and required payout details. The request is subject to the configured minimum, percentage tax, and administrator processing.', 'withdrawals'],
            ['Which currency will I receive for a campaign reward?', 'Campaign currency determines the reward currency. The app may show converted display values using available exchange-rate snapshots; displayed conversions do not change the campaign currency.', 'payments'],
            ['How do referrals work?', 'Your referral screen shows your referral code and attributed sign-ups. Any referral reward is subject to the active referral rules and appears in the Referral wallet when credited.', 'referrals'],
            ['Where can I check a task or payment status?', 'Use History for task submissions, ad-share verification, wallet activity, and withdrawal requests. Top-up records show payment status and any available manual-deposit review status.', 'account'],
            ['Will I receive notifications about account activity?', 'TrustLens provides an in-app notifications inbox. Push notifications also require the mobile app and server push-notification services to be configured for the environment.', 'account'],
        ];

        $now = now();
        foreach ($faqs as $index => [$question, $answer, $category]) {
            DB::table('faqs')->updateOrInsert(
                ['question' => $question],
                [
                    'answer' => $answer,
                    'category' => $category,
                    'order' => $index + 1,
                    'is_active' => true,
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
            );
        }
    }
}
