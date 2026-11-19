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
            AboutPageSettingsSeeder::class,
            SubscriptionGroupSeeder::class,
        ]);

        // Everything below is demo content. It is only safe to insert in
        // non-production environments.
        //if (! app()->environment(['local', 'testing', 'staging'])) {
            // Add-on defaults are feature configuration, not demo activity.
            // Seed them for existing production subscription plan records too.
            $this->call(PackageAddonSeeder::class);
        //    return;
        //}

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
            PackageAddonSeeder::class,
            DemoUserSeeder::class,
            SocialHandleSeeder::class,
            SocialFollowSeeder::class,
            TestimonialSeeder::class,
            FaqSeeder::class,
            BankSeeder::class,
            LegalDocumentSeeder::class,
            AnnouncementSeeder::class,
            //DemoPlatformActivitySeeder::class,
            PaymentOptionSeeder::class,
			//WalletCreditSeeder::class,
            //WalletBalanceSyncSeeder::class,
        ]);
    }
}
