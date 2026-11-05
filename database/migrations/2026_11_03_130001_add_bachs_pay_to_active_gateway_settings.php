<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const CURRENCIES = ['USD', 'NGN', 'GHS', 'KES', 'ZAR'];

    public function up(): void
    {
        $setting = DB::table('system_settings')->where('setting_key', 'payment.active_gateways')->first();
        if (! $setting) {
            return;
        }

        $gateways = is_array($setting->value) ? $setting->value : json_decode((string) $setting->value, true);
        if (! is_array($gateways)) {
            return;
        }
        if (array_is_list($gateways)) {
            $expanded = [];
            foreach ($gateways as $gateway) {
                if (! is_string($gateway)) {
                    continue;
                }
                $expanded[$gateway] = match ($gateway) {
                    'korapay' => ['NGN', 'GHS', 'KES', 'UGX', 'USD'],
                    'paystack' => ['NGN', 'GHS', 'ZAR', 'USD'],
                    'flutterwave' => ['NGN', 'USD', 'GHS', 'KES', 'UGX', 'ZAR', 'GBP', 'EUR'],
                    'manual' => [],
                    default => [],
                };
            }
            $gateways = $expanded;
        }
        $gateways['bachs'] ??= self::CURRENCIES;

        DB::table('system_settings')->where('id', $setting->id)->update([
            'value' => json_encode($gateways, JSON_THROW_ON_ERROR),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        $setting = DB::table('system_settings')->where('setting_key', 'payment.active_gateways')->first();
        if (! $setting) {
            return;
        }
        $gateways = is_array($setting->value) ? $setting->value : json_decode((string) $setting->value, true);
        if (! is_array($gateways) || array_is_list($gateways)) {
            return;
        }
        unset($gateways['bachs']);
        DB::table('system_settings')->where('id', $setting->id)->update([
            'value' => json_encode($gateways, JSON_THROW_ON_ERROR),
            'updated_at' => now(),
        ]);
    }
};
