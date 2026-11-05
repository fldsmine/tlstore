<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class CountryAndRegionSeeder extends Seeder
{
    public function run(): void
    {
        $now = now();
        $countries = [
            ['code' => 'NG', 'name' => 'Nigeria', 'default_currency' => 'NGN'],
            ['code' => 'GH', 'name' => 'Ghana', 'default_currency' => 'GHS'],
            ['code' => 'KE', 'name' => 'Kenya', 'default_currency' => 'KES'],
            ['code' => 'ZA', 'name' => 'South Africa', 'default_currency' => 'ZAR'],
            ['code' => 'ZM', 'name' => 'Zambia', 'default_currency' => 'ZMW'],
            ['code' => 'UG', 'name' => 'Uganda', 'default_currency' => 'UGX'],
            ['code' => 'TZ', 'name' => 'Tanzania', 'default_currency' => 'TZS'],
            ['code' => 'RW', 'name' => 'Rwanda', 'default_currency' => 'RWF'],
            ['code' => 'GB', 'name' => 'United Kingdom', 'default_currency' => 'GBP'],
            ['code' => 'US', 'name' => 'United States', 'default_currency' => 'USD'],
            ['code' => 'CA', 'name' => 'Canada', 'default_currency' => 'CAD'],
            ['code' => 'AU', 'name' => 'Australia', 'default_currency' => 'AUD'],
            ['code' => 'NZ', 'name' => 'New Zealand', 'default_currency' => 'NZD'],
            ['code' => 'IN', 'name' => 'India', 'default_currency' => 'INR'],
            ['code' => 'AE', 'name' => 'United Arab Emirates', 'default_currency' => 'AED'],
            ['code' => 'SA', 'name' => 'Saudi Arabia', 'default_currency' => 'SAR'],
            ['code' => 'JP', 'name' => 'Japan', 'default_currency' => 'JPY'],
            ['code' => 'CN', 'name' => 'China', 'default_currency' => 'CNY'],
            ['code' => 'BR', 'name' => 'Brazil', 'default_currency' => 'BRL'],
        ];

        $countryRows = array_map(static fn (array $country): array => $country + [
            'is_available' => true,
            'created_at' => $now,
            'updated_at' => $now,
        ], $countries);

        DB::table('countries')->upsert(
            $countryRows,
            ['code'],
            ['name', 'default_currency', 'is_available', 'updated_at']
        );

        $regions = [
            ['code' => 'WEST_AFRICA', 'name' => 'West Africa', 'countries' => ['NG', 'GH']],
            ['code' => 'EAST_AFRICA', 'name' => 'East Africa', 'countries' => ['KE', 'UG', 'TZ', 'RW']],
            ['code' => 'SOUTHERN_AFRICA', 'name' => 'Southern Africa', 'countries' => ['ZA', 'ZM']],
            ['code' => 'GLOBAL', 'name' => 'Global', 'countries' => array_column($countries, 'code')],
        ];

        foreach ($regions as $region) {
            DB::table('regions')->upsert([[
                'code' => $region['code'],
                'name' => $region['name'],
                'is_available' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]], ['code'], ['name', 'is_available', 'updated_at']);

            $regionId = DB::table('regions')->where('code', $region['code'])->value('id');
            $memberships = array_map(static fn (string $countryCode): array => [
                'region_id' => $regionId,
                'country_code' => $countryCode,
                'created_at' => $now,
                'updated_at' => $now,
            ], $region['countries']);

            DB::table('region_countries')->upsert(
                $memberships,
                ['region_id', 'country_code'],
                ['updated_at']
            );
        }
    }
}
