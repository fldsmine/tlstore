<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Payment, validation and reward switches that used to be written by data
 * migrations:
 *
 *   2026_11_02_130001_expand_active_gateway_currency_settings
 *   2026_11_03_130001_add_bachs_pay_to_active_gateway_settings
 *   2026_11_04_090000_add_referral_reward_rules_and_bonus_wallet   (settings only)
 *   2026_11_04_120000_create_active_payment_currency_setting
 *   2026_11_05_090000_create_user_ranking_tables                   (setting only)
 *   2026_11_09_090000_seed_account_validation_provider_setting
 *
 * About-page copy is owned by AboutPageSettingsSeeder; values for the
 * payment settings are applied afterwards by DemoAdminSeeder (local only).
 *
 * Only DB is used (no models), so this runs on a fresh install. Every insert is
 * "insert or ignore", so re-running it never overwrites a value an admin has
 * edited in the dashboard.
 */
class SystemSettingSeeder extends Seeder
{
    public function run(): void
    {
        $now = now();
        $rows = [];

        foreach ($this->settings() as $key => $definition) {
            [$value, $isPublic] = $definition;

            $rows[] = [
                'setting_key' => $key,
                'value' => json_encode($value, JSON_THROW_ON_ERROR),
                'is_public' => $isPublic,
                'updated_by_admin_id' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        DB::table('system_settings')->insertOrIgnore($rows);
    }

    /**
     * @return array<string, array{0: mixed, 1: bool}> key => [value, is_public]
     */
    private function settings(): array
    {
        // Final shape of payment.active_gateways after 2026_11_02_130001
        // and 2026_11_03_130001 (a map of gateway => supported currencies).
        $gateways = [
            'korapay' => ['NGN', 'GHS', 'KES', 'UGX', 'USD'],
            'paystack' => ['NGN', 'GHS', 'ZAR', 'USD'],
            'flutterwave' => ['NGN', 'USD', 'GHS', 'KES', 'UGX', 'ZAR', 'GBP', 'EUR'],
            'bachs' => ['USD', 'NGN', 'GHS', 'KES', 'ZAR'],
            'manual' => [],
        ];

        // payment.active_payment_currency was derived from the gateway map:
        // { "gateway.CURRENCY": "CURRENCY" }.
        $activePaymentCurrency = [];
        foreach ($gateways as $gateway => $currencies) {
            foreach ($currencies as $currency) {
                $activePaymentCurrency[$gateway.'.'.$currency] = $currency;
            }
        }

        return [
            // --- payments ---------------------------------------------------
            'payment.active_gateways' => [$gateways, true],
            'payment.active_payment_currency' => [$activePaymentCurrency, true],
            'account_validation.provider' => [['provider' => 'paystack'], false],

            // VAS remains disabled until purchase and wallet settlement are deployed.
            'vas.enabled' => [true, true],
            'vas.services' => [[
                'airtime' => true,
                'data' => true,
                'tv' => true,
                'electricity' => true,
                'education' => true,
            ], false],
            'vas.catalog_cache_minutes' => [360, false],

            // --- rewards ----------------------------------------------------
            'rewards.referral_subscription_active' => [true, false],
            'rewards.referral_task_active' => [true, false],
            'rewards.subscription_bonus_active' => [true, false],
            'rewards.social_follow_active' => [true, false],
            'rewards.user_ranking_active' => [true, true],

            // Referral withdrawals are available every day by default;
            // bonus-wallet withdrawals remain closed until an admin enables them.
            'withdrawal.wallet.referral' => [['active' => true], false],
            'withdrawal.wallet.bonus' => [['active' => false], false],

            // --- app auto-approval delays (minutes) ----------------------
            'app.auto_approve_ads.delay_minutes' => [4, false],
            'app.auto_approve_reviews.delay_minutes' => [7, false],

            // --- user campaign order pricing (amounts are NGN per action) --
            'campaign.services' => [[
                'tiktok' => ['followers' => 40, 'min' => 50],
                'youtube' => ['subscribers' => 100, 'min' => 40],
                'twitter' => ['follower' => 70, 'min' => 30],
                'instagram' => [
                    'followers' => 50,
                    'likes' => 30,
                    'min' => 40,
                ],
                'facebook' => [
                    'page_like' => 40,
                    'post_share' => 20,
                    'min' => 50,
                ],
                'whatsapp' => ['channel' => 20, 'min' => 50],
                'telegram' => ['join' => 20, 'min' => 50],
            ], true],
            'campaign.service_fee_percent' => [5, true],
            'campaign.currency' => ['NGN', true],
        ];
    }
}
