<?php

namespace Database\Seeders;

use App\Models\Country;
use Illuminate\Database\Seeder;

class CountrySeeder extends Seeder
{
    public function run(): void
    {
        $countries = [
            ['name' => 'Nigeria', 'code' => 'NG', 'currency_code' => 'NGN', 'flag' => '🇳🇬'],
            ['name' => 'Ghana', 'code' => 'GH', 'currency_code' => 'GHS', 'flag' => '🇬🇭'],
            ['name' => 'Cameroon', 'code' => 'CM', 'currency_code' => 'XAF', 'flag' => '🇨🇲'],
            ['name' => 'South Africa', 'code' => 'ZA', 'currency_code' => 'ZAR', 'flag' => '🇿🇦'],
            ['name' => 'Kenya', 'code' => 'KE', 'currency_code' => 'KES', 'flag' => '🇰🇪'],
            ['name' => 'Uganda', 'code' => 'UG', 'currency_code' => 'UGX', 'flag' => '🇺🇬'],
            ['name' => 'Sierra Leone', 'code' => 'SL', 'currency_code' => 'SLE', 'flag' => '🇸🇱'],
            ['name' => 'Malawi', 'code' => 'MW', 'currency_code' => 'MWK', 'flag' => '🇲🇼'],
            ['name' => 'Zambia', 'code' => 'ZM', 'currency_code' => 'ZMW', 'flag' => '🇿🇲'],
            ['name' => 'Liberia', 'code' => 'LR', 'currency_code' => 'LRD', 'flag' => '🇱🇷'],
            ['name' => 'Gambia', 'code' => 'GM', 'currency_code' => 'GMD', 'flag' => '🇬🇲'],
            ['name' => 'Tanzania', 'code' => 'TZ', 'currency_code' => 'TZS', 'flag' => '🇹🇿'],
            ['name' => 'Rwanda', 'code' => 'RW', 'currency_code' => 'RWF', 'flag' => '🇷🇼'],
            ['name' => 'Botswana', 'code' => 'BW', 'currency_code' => 'BWP', 'flag' => '🇧🇼'],
            ['name' => 'Zimbabwe', 'code' => 'ZW', 'currency_code' => 'ZWG', 'flag' => '🇿🇼'],
            // West-African CFA bloc — all share the West African CFA franc (XOF), one rate for all 7.
            ['name' => 'Ivory Coast', 'code' => 'CI', 'currency_code' => 'XOF', 'flag' => '🇨🇮'],
            ['name' => 'Burkina Faso', 'code' => 'BF', 'currency_code' => 'XOF', 'flag' => '🇧🇫'],
            ['name' => 'Togo', 'code' => 'TG', 'currency_code' => 'XOF', 'flag' => '🇹🇬'],
            ['name' => 'Senegal', 'code' => 'SN', 'currency_code' => 'XOF', 'flag' => '🇸🇳'],
            ['name' => 'Benin', 'code' => 'BJ', 'currency_code' => 'XOF', 'flag' => '🇧🇯'],
            ['name' => 'Mali', 'code' => 'ML', 'currency_code' => 'XOF', 'flag' => '🇲🇱'],
            ['name' => 'Niger', 'code' => 'NE', 'currency_code' => 'XOF', 'flag' => '🇳🇪'],
        ];

        Country::upsert($countries, ['code'], ['name', 'currency_code', 'flag']);
    }
}
