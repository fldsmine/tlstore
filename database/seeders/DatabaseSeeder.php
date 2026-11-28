<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // Configuration and reference data. These rows used to be written by
        // data migrations (payment gateways, reward switches, about page copy,
        // package groups), so they belong in every environment - a fresh
        // production database needs them before the app can boot.
        $this->call([
            SystemSettingSeeder::class,
            NotificationTemplateSeeder::class,
            AboutPageSettingsSeeder::class,
            SubscriptionGroupSeeder::class,
            // Add-on defaults are feature configuration, not demo activity, so
            // they are seeded in every environment (existing production
            // subscription plan records need them too).
            PackageAddonSeeder::class,
        ]);

        // Everything below is demo content. It is only safe to insert outside
        // production: it creates demo administrators, demo users, synthetic
        // wallet balances and sample platform activity.
        if (! app()->environment(['local', 'testing', 'staging'])) {
            return;
        }

        $this->call([
            UserRankLevelSeeder::class,
            CountryAndRegionSeeder::class,
            DemoAdminSeeder::class,
            AppListingSeeder::class,
            AppScreenshotSeeder::class,
            AdListingSeeder::class,
            ReviewQuestionSeeder::class,
            WithdrawalOptionSeeder::class,
            ExchangeRateSnapshotSeeder::class,
            SubscriptionPlanSeeder::class,
            DemoUserSeeder::class,
            SocialHandleSeeder::class,
            SocialFollowSeeder::class,
            TestimonialSeeder::class,
            FaqSeeder::class,
            BankSeeder::class,
            LegalDocumentSeeder::class,
            AnnouncementSeeder::class,
            DemoPlatformActivitySeeder::class,
            PaymentOptionSeeder::class,
            WalletCreditSeeder::class,
            WalletBalanceSyncSeeder::class,
        ]);
    }
}
