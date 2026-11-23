<?php

namespace Database\Seeders;

use App\Models\Currency;
use Illuminate\Database\Seeder;

class CurrencySeeder extends Seeder
{
    public function run(): void
    {
        $currencies = [
            ['code' => 'USD', 'name' => 'US Dollar', 'symbol' => '$', 'usd_exchange_rate' => 1],
            ['code' => 'NGN', 'name' => 'Nigerian Naira', 'symbol' => '₦', 'usd_exchange_rate' => 1000],
            ['code' => 'GHS', 'name' => 'Ghanaian Cedi', 'symbol' => '₵', 'usd_exchange_rate' => 12],
            ['code' => 'XAF', 'name' => 'Central African CFA franc', 'symbol' => 'FCFA', 'usd_exchange_rate' => 600],
            ['code' => 'XOF', 'name' => 'West African CFA franc', 'symbol' => 'CFA', 'usd_exchange_rate' => 600],
            ['code' => 'ZAR', 'name' => 'South African Rand', 'symbol' => 'R', 'usd_exchange_rate' => 18],
            ['code' => 'KES', 'name' => 'Kenyan Shilling', 'symbol' => 'KSh', 'usd_exchange_rate' => 130],
            ['code' => 'UGX', 'name' => 'Ugandan Shilling', 'symbol' => 'USh', 'usd_exchange_rate' => 3700],
            ['code' => 'SLE', 'name' => 'Sierra Leonean Leone', 'symbol' => 'Le', 'usd_exchange_rate' => 22],
            ['code' => 'MWK', 'name' => 'Malawian Kwacha', 'symbol' => 'MK', 'usd_exchange_rate' => 1700],
            ['code' => 'ZMW', 'name' => 'Zambian Kwacha', 'symbol' => 'ZK', 'usd_exchange_rate' => 26],
            ['code' => 'LRD', 'name' => 'Liberian Dollar', 'symbol' => 'L$', 'usd_exchange_rate' => 190],
            ['code' => 'GMD', 'name' => 'Gambian Dalasi', 'symbol' => 'D', 'usd_exchange_rate' => 68],
            ['code' => 'TZS', 'name' => 'Tanzanian Shilling', 'symbol' => 'TSh', 'usd_exchange_rate' => 2600],
            ['code' => 'RWF', 'name' => 'Rwandan Franc', 'symbol' => 'FRw', 'usd_exchange_rate' => 1350],
            ['code' => 'BWP', 'name' => 'Botswana Pula', 'symbol' => 'P', 'usd_exchange_rate' => 13.5],
            ['code' => 'ZWG', 'name' => 'Zimbabwe Gold', 'symbol' => 'ZiG', 'usd_exchange_rate' => 13.5],
        ];

        Currency::upsert($currencies, ['code'], ['name', 'symbol', 'usd_exchange_rate']);
    }
}
