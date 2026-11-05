<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
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
            LegalDocumentSeeder::class,
            AboutPageSettingsSeeder::class,
            AnnouncementSeeder::class,
            DemoPlatformActivitySeeder::class,
            PaymentOptionSeeder::class,
        ]);
    }
}
