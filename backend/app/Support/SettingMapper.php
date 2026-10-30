<?php

namespace App\Support;

use App\Models\User;

class SettingMapper
{
    public static function toConfigArray(array $settings): array
    {
        return self::withdrawalWalletConfig($settings) + self::vtuConfig($settings) + self::servicesConfig($settings) + self::cloudConfig($settings) + self::backupConfig($settings) + self::chatConfig($settings) + self::websiteConfig($settings) + [
            'app.name' => $settings['app_name'] ?? config('app.name'),
            'settings.app.name' => $settings['app_name'] ?? config('settings.app.name'),
            'settings.app.enable_registration' => self::getBooleanSetting($settings, 'enable_registration', 'settings.app.enable_registration'),
            'settings.app.enable_maintenance_mode' => self::getBooleanSetting($settings, 'enable_maintenance_mode', 'settings.app.enable_maintenance_mode'),
            'settings.app.enable_email_verification' => self::getBooleanSetting($settings, 'enable_email_verification', 'settings.app.enable_email_verification'),

            'settings.contact.email' => $settings['contact_email'] ?? config('settings.contact.email'),
            'settings.contact.phone' => $settings['contact_phone'] ?? config('settings.contact.phone'),
            'settings.contact.social.telegram' => $settings['telegram_link'] ?? config('settings.contact.social.telegram'),
            'settings.contact.telegram_general' => filled($settings['telegram_general_link'] ?? null) ? $settings['telegram_general_link'] : ($settings['telegram_link'] ?? config('settings.contact.telegram_general')),
            'settings.contact.telegram_activation' => filled($settings['telegram_activation_link'] ?? null) ? $settings['telegram_activation_link'] : ($settings['telegram_link'] ?? config('settings.contact.telegram_activation')),
            // Unlike its siblings above, a blank revenue desk keeps its OWN default instead of
            // inheriting the channel link — see the note in config/settings.php.
            'settings.contact.telegram_revenue' => filled($settings['telegram_revenue_link'] ?? null) ? $settings['telegram_revenue_link'] : config('settings.contact.telegram_revenue'),
            'settings.contact.address' => $settings['contact_address'] ?? config('settings.contact.address'),

            'settings.contact.social.facebook' => filled($settings['facebook_link'] ?? null) ? $settings['facebook_link'] : config('settings.contact.social.facebook'),
            'settings.contact.social.instagram' => filled($settings['instagram_link'] ?? null) ? $settings['instagram_link'] : config('settings.contact.social.instagram'),
            'settings.contact.social.x' => filled($settings['x_link'] ?? null) ? $settings['x_link'] : config('settings.contact.social.x'),
            'settings.contact.social.website' => filled($settings['website_link'] ?? null) ? $settings['website_link'] : config('settings.contact.social.website'),
            'settings.contact.social.tiktok' => filled($settings['tiktok_link'] ?? null) ? $settings['tiktok_link'] : config('settings.contact.social.tiktok'),

            'settings.referral.base_url' => $settings['referral_base_url'] ?? config('settings.referral.base_url'),
            'settings.referral.pay_on_upgrade' => self::getBooleanSetting($settings, 'referral_pay_on_upgrade', 'settings.referral.pay_on_upgrade'),

            'settings.analytics.header_code' => $settings['analytics_header_code'] ?? config('settings.analytics.header_code'),
            'settings.analytics.footer_code' => $settings['analytics_footer_code'] ?? config('settings.analytics.footer_code'),

            'settings.payment.active_manager' => $settings['active_payment_manager'] ?? config('settings.payment.active_manager'),
            'settings.payment.bank_verification_provider' => $settings['bank_verification_provider'] ?? config('settings.payment.bank_verification_provider'),
            'settings.payment.renewal_provider' => $settings['renewal_payment_provider'] ?? config('settings.payment.renewal_provider'),
            'settings.payment.bill_provider' => $settings['bill_payment_provider'] ?? config('settings.payment.bill_provider'),
            'settings.payment.storage_provider' => $settings['storage_payment_provider'] ?? config('settings.payment.storage_provider'),
            'settings.payment.package_provider' => $settings['package_payment_provider'] ?? config('settings.payment.package_provider'),
            'settings.payment.special_coupon_provider' => $settings['special_coupon_payment_provider'] ?? config('settings.payment.special_coupon_provider'),
            'settings.payment.loan_application_fee_provider' => $settings['loan_application_fee_payment_provider'] ?? config('settings.payment.loan_application_fee_provider'),
            'settings.payment.manual_bank_account_number' => $settings['manual_bank_account_number'] ?? config('settings.payment.manual_bank_account_number'),
            'settings.payment.manual_bank_name' => $settings['manual_bank_name'] ?? config('settings.payment.manual_bank_name'),
            'settings.payment.manual_bank_account_name' => $settings['manual_bank_account_name'] ?? config('settings.payment.manual_bank_account_name'),
            'settings.payment.manual_bank_swift_code' => $settings['manual_bank_swift_code'] ?? config('settings.payment.manual_bank_swift_code'),
            'settings.payment.narration' => $settings['payout_narration'] ?? config('settings.payment.narration'),

            'settings.paylater.enabled' => self::getBooleanSetting($settings, 'enable_paylater', 'settings.paylater.enabled'),
            'settings.paylater.disabled_title' => $settings['paylater_disabled_title'] ?? config('settings.paylater.disabled_title'),
            'settings.paylater.disabled_message' => $settings['paylater_disabled_message'] ?? config('settings.paylater.disabled_message'),
            'settings.paylater.audience' => $settings['paylater_audience'] ?? config('settings.paylater.audience'),
            'settings.paylater.limit' => $settings['paylater_limit'] ?? config('settings.paylater.limit'),
            'settings.paylater.repayment_days' => $settings['paylater_repayment_days'] ?? config('settings.paylater.repayment_days'),

            'settings.minutes.enabled' => self::getBooleanSetting($settings, 'enable_minutes', 'settings.minutes.enabled'),
            'settings.minutes.disabled_title' => $settings['minutes_disabled_title'] ?? config('settings.minutes.disabled_title'),
            'settings.minutes.disabled_message' => $settings['minutes_disabled_message'] ?? config('settings.minutes.disabled_message'),

            'settings.loan.enabled' => self::getBooleanSetting($settings, 'enable_loan', 'settings.loan.enabled'),
            'settings.loan.disabled_title' => $settings['loan_disabled_title'] ?? config('settings.loan.disabled_title'),
            'settings.loan.disabled_message' => $settings['loan_disabled_message'] ?? config('settings.loan.disabled_message'),
            'settings.loan.max_amount' => $settings['loan_max_amount'] ?? config('settings.loan.max_amount'),
            'settings.loan.min_amount' => $settings['loan_min_amount'] ?? config('settings.loan.min_amount', 50),
            'settings.loan.presets' => is_array($settings['loan_presets'] ?? null)
                ? $settings['loan_presets']
                : config('settings.loan.presets', [20, 50, 100, 250, 500]),
            'settings.loan.repayment_days' => $settings['loan_repayment_days'] ?? config('settings.loan.repayment_days'),
            'settings.loan.service_fee_percent' => $settings['loan_service_fee_percent'] ?? config('settings.loan.service_fee_percent'),
            'settings.loan.application_fee_mode' => $settings['loan_application_fee_mode'] ?? config('settings.loan.application_fee_mode', 'fixed'),
            'settings.loan.application_fee_percentage' => $settings['loan_application_fee_percentage'] ?? config('settings.loan.application_fee_percentage', 1),
            'settings.loan.application_fee_usd' => $settings['loan_application_fee_usd'] ?? config('settings.loan.application_fee_usd'),
            'settings.loan.application_fee_ngn' => $settings['loan_application_fee_ngn'] ?? config('settings.loan.application_fee_ngn'),
            'settings.loan.guarantor_form_fee_ngn' => $settings['loan_guarantor_form_fee_ngn'] ?? config('settings.loan.guarantor_form_fee_ngn', 1500),
            'settings.loan.guarantor_form_enabled' => self::getBooleanSetting($settings, 'guarantor_form_enabled', 'settings.loan.guarantor_form_enabled'),
            'settings.loan.application_fee_agent_usd' => $settings['loan_application_fee_agent_usd'] ?? config('settings.loan.application_fee_agent_usd'),
            'settings.loan.application_fee_agent_ngn' => $settings['loan_application_fee_agent_ngn'] ?? config('settings.loan.application_fee_agent_ngn'),
            'settings.loan.types' => is_array($settings['loan_types'] ?? null)
                ? array_replace((array) config('settings.loan.types', []), $settings['loan_types'])
                : config('settings.loan.types'),
            'settings.loan.rejection_reason' => $settings['loan_rejection_reason'] ?? config('settings.loan.rejection_reason'),

            'settings.ads.enabled' => self::getBooleanSetting($settings, 'enable_ads', 'settings.ads.enabled'),
            'settings.ads.disabled_title' => $settings['ads_disabled_title'] ?? config('settings.ads.disabled_title'),
            'settings.ads.disabled_message' => $settings['ads_disabled_message'] ?? config('settings.ads.disabled_message'),
            'settings.ads.website_slots' => $settings['ads_website_slots'] ?? config('settings.ads.website_slots'),

            'settings.rewards.enabled' => self::getBooleanSetting($settings, 'enable_rewards', 'settings.rewards.enabled'),
            'settings.rewards.disabled_title' => $settings['rewards_disabled_title'] ?? config('settings.rewards.disabled_title'),
            'settings.rewards.disabled_message' => $settings['rewards_disabled_message'] ?? config('settings.rewards.disabled_message'),
            'settings.rewards.target_percentage' => $settings['reward_target_percentage'] ?? config('settings.rewards.target_percentage'),
            'settings.rewards.min_target' => $settings['reward_min_target'] ?? config('settings.rewards.min_target'),
            'settings.rewards.max_target' => $settings['reward_max_target'] ?? config('settings.rewards.max_target'),

            'settings.withdrawal.require_authorization_code' => self::getBooleanSetting($settings, 'withdrawal_require_authorization_code', 'settings.withdrawal.require_authorization_code'),
        ];
    }

    protected static function servicesConfig(array $settings): array
    {
        return [
            'services.flutterwave.base_url' => $settings['flutterwave_base_url'] ?? config('services.flutterwave.base_url'),
            'services.flutterwave.public_key' => $settings['flutterwave_public_key'] ?? config('services.flutterwave.public_key'),
            'services.flutterwave.secret_key' => $settings['flutterwave_secret_key'] ?? config('services.flutterwave.secret_key'),
            'services.flutterwave.currency' => $settings['flutterwave_currency'] ?? config('services.flutterwave.currency'),
            'services.flutterwave.secret_hash' => $settings['flutterwave_secret_hash'] ?? config('services.flutterwave.secret_hash'),
            'services.paystack.base_url' => $settings['paystack_base_url'] ?? config('services.paystack.base_url'),
            'services.paystack.public_key' => $settings['paystack_public_key'] ?? config('services.paystack.public_key'),
            'services.paystack.secret_key' => $settings['paystack_secret_key'] ?? config('services.paystack.secret_key'),
            'services.paystack.currency' => $settings['paystack_currency'] ?? config('services.paystack.currency'),

            'services.bachs.base_url' => $settings['bachs_base_url'] ?? config('services.bachs.base_url'),
            'services.bachs.public_key' => $settings['bachs_public_key'] ?? config('services.bachs.public_key'),
            'services.bachs.secret_key' => $settings['bachs_secret_key'] ?? config('services.bachs.secret_key'),
            'services.bachs.currency' => $settings['bachs_currency'] ?? config('services.bachs.currency'),
            'services.bachs.webhook_secret' => $settings['bachs_webhook_secret'] ?? config('services.bachs.webhook_secret'),
            'services.korapay.base_url' => $settings['korapay_base_url'] ?? config('services.korapay.base_url'),
            'services.korapay.public_key' => $settings['korapay_public_key'] ?? config('services.korapay.public_key'),
            'services.korapay.secret_key' => $settings['korapay_secret_key'] ?? config('services.korapay.secret_key'),
            'services.korapay.currency' => $settings['korapay_currency'] ?? config('services.korapay.currency'),

            'services.vtpass.base_url' => $settings['vtpass_base_url'] ?? config('services.vtpass.base_url'),
            'services.vtpass.api_key' => $settings['vtpass_api_key'] ?? config('services.vtpass.api_key'),
            'services.vtpass.secret_key' => $settings['vtpass_secret_key'] ?? config('services.vtpass.secret_key'),
            'services.vtpass.public_key' => $settings['vtpass_public_key'] ?? config('services.vtpass.public_key'),

            // ElevenLabs (Nextel Cloud realistic voices). The one saved key feeds both the
            // voices REST call (services.*) and the AI-SDK TTS driver (ai.providers.eleven.*).
            'services.elevenlabs.base_url' => $settings['elevenlabs_base_url'] ?? config('services.elevenlabs.base_url'),
            'services.elevenlabs.key' => $settings['elevenlabs_api_key'] ?? config('services.elevenlabs.key'),
            'ai.providers.eleven.key' => $settings['elevenlabs_api_key'] ?? config('ai.providers.eleven.key'),

            'mail.default' => $settings['mail_driver'] ?? config('mail.default'),
            'services.resend.key' => $settings['resend_key'] ?? config('services.resend.key'),
            'mail.mailers.smtp.host' => $settings['mail_host'] ?? config('mail.mailers.smtp.host'),
            'mail.mailers.smtp.port' => $settings['mail_port'] ?? config('mail.mailers.smtp.port'),
            'mail.mailers.smtp.username' => $settings['mail_username'] ?? config('mail.mailers.smtp.username'),
            'mail.mailers.smtp.password' => $settings['mail_password'] ?? config('mail.mailers.smtp.password'),
            'mail.from.address' => $settings['mail_from_address'] ?? config('mail.from.address'),
            'mail.from.name' => $settings['mail_from_name'] ?? config('mail.from.name'),

            'settings.token.pin_reset.enabled' => self::getBooleanSetting($settings, 'enable_pin_reset_token', 'settings.token.pin_reset.enabled'),
            'settings.token.pin_reset.price' => $settings['pin_reset_token_price'] ?? config('settings.token.pin_reset.price'),
            'settings.token.pin_reset.agent_price' => $settings['pin_reset_token_agent_price'] ?? config('settings.token.pin_reset.agent_price'),
            'settings.token.pin_reset.foreign_price' => $settings['pin_reset_token_foreign_price'] ?? config('settings.token.pin_reset.foreign_price'),
            'settings.token.pin_reset.foreign_agent_price' => $settings['pin_reset_token_foreign_agent_price'] ?? config('settings.token.pin_reset.foreign_agent_price'),
        ];
    }

    protected static function vtuConfig(array $settings): array
    {
        $config = [
            'settings.vtu.enabled' => self::getBooleanSetting($settings, 'enable_vtu', 'settings.vtu.enabled'),
            'settings.vtu.provider' => $settings['bill_fulfilment_provider'] ?? config('settings.vtu.provider'),
        ];

        foreach (array_keys(config('settings.vtu.wallets', [])) as $key) {
            $base = "settings.vtu.wallets.{$key}";
            $config["{$base}.enabled"] = self::getBooleanSetting($settings, "enable_{$key}_vtu", "{$base}.enabled");
            $config["{$base}.min_ngn"] = $settings["{$key}_vtu_min_ngn"] ?? config("{$base}.min_ngn");
            $config["{$base}.max_ngn"] = $settings["{$key}_vtu_max_ngn"] ?? config("{$base}.max_ngn");
            $config["{$base}.max_purchases"] = $settings["{$key}_vtu_max_purchases"] ?? config("{$base}.max_purchases");
        }

        return $config;
    }

    protected static function cloudConfig(array $settings): array
    {
        return [
            'settings.cloud.enabled' => self::getBooleanSetting($settings, 'enable_cloud', 'settings.cloud.enabled'),

            'settings.cloud.referral_bonus' => $settings['cloud_referral_bonus'] ?? config('settings.cloud.referral_bonus'),
            'settings.cloud.online_window_seconds' => $settings['cloud_online_window_seconds'] ?? config('settings.cloud.online_window_seconds'),
            'settings.cloud.broadcast_expiry_minutes' => $settings['cloud_broadcast_expiry_minutes'] ?? config('settings.cloud.broadcast_expiry_minutes'),
        ];
    }

    protected static function chatConfig(array $settings): array
    {
        return [
            'settings.chat.enabled' => self::getBooleanSetting($settings, 'enable_chat', 'settings.chat.enabled'),
            'settings.chat.history_retention_days' => $settings['chat_history_retention_days'] ?? config('settings.chat.history_retention_days'),
        ];
    }

    protected static function websiteConfig(array $settings): array
    {
        return [
            'settings.website.duration_days' => (int) ($settings['website_duration_days'] ?? config('settings.website.duration_days')),
            'settings.website.renewal_mode' => $settings['website_renewal_mode'] ?? config('settings.website.renewal_mode'),
            'settings.website.renewal_price' => (float) ($settings['website_renewal_price'] ?? config('settings.website.renewal_price')),
            'settings.website.code.price' => $settings['website_code_price'] ?? config('settings.website.code.price'),
            'settings.website.code.agent_price' => $settings['website_code_agent_price'] ?? config('settings.website.code.agent_price'),
            'settings.website.code.foreign_price' => $settings['website_code_foreign_price'] ?? config('settings.website.code.foreign_price'),
            'settings.website.code.foreign_agent_price' => $settings['website_code_foreign_agent_price'] ?? config('settings.website.code.foreign_agent_price'),
            'settings.website.expired_title' => $settings['website_expired_title'] ?? config('settings.website.expired_title'),
            'settings.website.expired_message' => $settings['website_expired_message'] ?? config('settings.website.expired_message'),
        ];
    }

    protected static function backupConfig(array $settings): array
    {
        $keepDays = (int) ($settings['backup_keep_days'] ?? config('settings.backup.keep_days'));

        return [
            'settings.backup.enabled' => self::getBooleanSetting($settings, 'enable_backup', 'settings.backup.enabled'),
            'settings.backup.keep_days' => $keepDays,

            'backup.cleanup.default_strategy.keep_all_backups_for_days' => $keepDays,
            'backup.cleanup.default_strategy.keep_daily_backups_for_days' => 0,
            'backup.cleanup.default_strategy.keep_weekly_backups_for_weeks' => 0,
            'backup.cleanup.default_strategy.keep_monthly_backups_for_months' => 0,
            'backup.cleanup.default_strategy.keep_yearly_backups_for_years' => 0,
        ];
    }

    protected static function withdrawalWalletConfig(array $settings): array
    {
        $config = [];

        foreach (User::SPENDABLE_WALLETS as $key) {
            $base = "settings.withdrawal.wallets.{$key}";
            $config["{$base}.enabled"] = self::getBooleanSetting($settings, "enable_{$key}_withdrawal", "{$base}.enabled");
            $config["{$base}.min"] = $settings["{$key}_withdrawal_min"] ?? config("{$base}.min");
            $config["{$base}.max"] = $settings["{$key}_withdrawal_max"] ?? config("{$base}.max");
            $config["{$base}.fee"] = $settings["{$key}_withdrawal_fee"] ?? config("{$base}.fee");
            $config["{$base}.auto_approve"] = self::getBooleanSetting($settings, "enable_{$key}_withdrawal_auto_approve", "{$base}.auto_approve");

            $config["{$base}.deduct_at"] = self::normalizeDeductAt(
                $settings["{$key}_withdrawal_deduct_at"] ?? config("{$base}.deduct_at"),
                (bool) $config["{$base}.auto_approve"]
            );

            $access = $settings["{$key}_withdrawal_country_access"] ?? config("{$base}.country_access");
            $config["{$base}.country_access"] = in_array($access, ['all', 'nigerian', 'non_nigerian'], true) ? $access : 'all';
        }

        return $config;
    }

    protected static function normalizeDeductAt(?string $value, bool $autoApprove): string
    {
        $value = in_array($value, ['request', 'approval'], true) ? $value : 'request';

        return $autoApprove ? 'request' : $value;
    }

    public static function getBooleanSetting(array $settings, string $key, string $configKey): bool
    {
        if (! array_key_exists($key, $settings)) {
            return (bool) config($configKey, false);
        }

        return filter_var($settings[$key], FILTER_VALIDATE_BOOLEAN);
    }
}
