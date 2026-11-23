<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            SettingsSeeder::class,
            CurrencySeeder::class,
            CountrySeeder::class,
            AdminSeeder::class,
            CallTypeSeeder::class,
            PackageSeeder::class,
            SystemAccountSeeder::class,
            BankSeeder::class,
        ]);

        // Demo data — local & dev only.
        if (! app()->environment('production')) {
            $this->call([
                AdditionalMinutePlanSeeder::class,
                CloudSeeder::class,
                TestDataSeeder::class,
                // The storefront's catalog: demo products so the product pages have a grid to show.
                ProductDemoSeeder::class,
            ]);
        }
    }
}
