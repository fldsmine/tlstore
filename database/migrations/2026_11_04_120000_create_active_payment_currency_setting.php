<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::table('system_settings')->where('setting_key', 'payment.active_payment_currency')->exists()) {
            return;
        }

        $legacy = DB::table('system_settings')->where('setting_key', 'payment.active_gateways')->first();
        $legacyValue = $legacy
            ? (is_array($legacy->value) ? $legacy->value : json_decode((string) $legacy->value, true))
            : [];
        $options = [];
        if (is_array($legacyValue)) {
            if (array_is_list($legacyValue)) {
                $defaults = [
                    'korapay' => ['NGN', 'GHS', 'KES', 'UGX', 'USD'],
                    'paystack' => ['NGN', 'GHS', 'ZAR', 'USD'],
                    'flutterwave' => ['NGN', 'USD', 'GHS', 'KES', 'UGX', 'ZAR', 'GBP', 'EUR'],
                    'bachs' => ['USD', 'NGN', 'GHS', 'KES', 'ZAR'],
                ];
                foreach ($legacyValue as $gateway) {
                    foreach ($defaults[$gateway] ?? [] as $currency) {
                        $options[$gateway.'.'.$currency] = $currency;
                    }
                }
            } else {
                foreach ($legacyValue as $gateway => $currencies) {
                    if (! is_array($currencies)) continue;
                    foreach ($currencies as $currency) {
                        if (is_string($currency) && preg_match('/^[A-Za-z]{3}$/', $currency) === 1) {
                            $currency = strtoupper($currency);
                            $options[strtolower((string) $gateway).'.'.$currency] = $currency;
                        }
                    }
                }
            }
        }

        DB::table('system_settings')->insertOrIgnore([
            'setting_key' => 'payment.active_payment_currency',
            'value' => json_encode($options, JSON_THROW_ON_ERROR),
            'is_public' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        DB::table('system_settings')->where('setting_key', 'payment.active_payment_currency')->delete();
    }
};
