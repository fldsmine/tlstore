<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $now = now();
        $values = [
            'about.brand_name' => 'TrustLens',
            'about.hero_tagline' => 'Discover trusted apps. Review. Earn.',
            'about.hero_image_url' => '',
            'about.mission_title' => 'Our Mission',
            'about.mission_body' => 'TrustLens connects app creators with people who can provide thoughtful feedback. Members complete review and public ad-sharing tasks, and receive rewards after approval.',
            'about.what_we_do_title' => 'What We Do',
            'about.what_we_do_body' => 'A community marketplace for app feedback and sponsored sharing. Members can:',
            'about.what_we_do_bullets' => [
                'Review apps and answer guided questions',
                'Share sponsor ads publicly and submit evidence',
                'Earn rewards after human approval',
                'Invite friends and earn referral rewards',
                'Choose a subscription and eligible locations',
            ],
            'about.stats_members_label' => 'Community Members',
            'about.stats_approved_label' => 'Approved Tasks',
            'about.stats_rewards_label' => 'Rewards Paid',
            'about.stats_testimonials_label' => 'Testimonials',
            'about.features' => ['App Reviews', 'Sponsored Sharing', 'Earn Rewards', 'Referrals', 'Location Access', 'Human Review'],
            'about.contact_email' => env('SUPPORT_EMAIL', 'support@example.com'),
            'about.contact_phone' => '',
            'about.contact_address' => '',
            'about.social_links' => [],
            'about.privacy_label' => 'Privacy Policy',
            'about.terms_label' => 'Terms of Participation',
            'about.rate_app_label' => 'Rate us on the store',
            'about.store_url' => '',
            'about.footer_copyright' => '© TrustLens. All rights reserved.',
            'about.footer_note' => 'Built for people who make apps better.',
            'about.version' => '1.0.0',
        ];

        DB::table('system_settings')->insertOrIgnore(array_map(
            fn (string $key, mixed $value): array => [
                'setting_key' => $key,
                'value' => json_encode($value, JSON_THROW_ON_ERROR),
                'is_public' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            array_keys($values),
            array_values($values),
        ));
    }

    public function down(): void
    {
        DB::table('system_settings')->where('setting_key', 'like', 'about.%')->delete();
    }
};
