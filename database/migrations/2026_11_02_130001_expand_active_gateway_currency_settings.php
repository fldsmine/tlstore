<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const LEGACY_DEFAULTS = [
        'korapay' => ['NGN', 'GHS', 'KES', 'UGX', 'USD'],
        'paystack' => ['NGN', 'GHS', 'ZAR', 'USD'],
        'flutterwave' => ['NGN', 'USD', 'GHS', 'KES', 'UGX', 'ZAR', 'GBP', 'EUR'],
        'manual' => [],
    ];

    public function up(): void
    {
        $setting = DB::table('system_settings')->where('setting_key', 'payment.active_gateways')->first();
        if (! $setting) {
            return;
        }

        $value = is_array($setting->value) ? $setting->value : json_decode((string) $setting->value, true);
        if (! is_array($value) || ! array_is_list($value)) {
            return;
        }

        $expanded = [];
        foreach ($value as $gateway) {
            if (is_string($gateway) && isset(self::LEGACY_DEFAULTS[$gateway])) {
                $expanded[$gateway] = self::LEGACY_DEFAULTS[$gateway];
            }
        }

        DB::table('system_settings')->where('id', $setting->id)->update([
            'value' => json_encode($expanded, JSON_THROW_ON_ERROR),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        $setting = DB::table('system_settings')->where('setting_key', 'payment.active_gateways')->first();
        if (! $setting) {
            return;
        }

        $value = is_array($setting->value) ? $setting->value : json_decode((string) $setting->value, true);
        if (! is_array($value) || array_is_list($value)) {
            return;
        }

        DB::table('system_settings')->where('id', $setting->id)->update([
            'value' => json_encode(array_keys($value), JSON_THROW_ON_ERROR),
            'updated_at' => now(),
        ]);
    }
};
