<?php

namespace Database\Seeders;

use App\Models\Setting;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Cache;

class SettingsSeeder extends Seeder
{
    public function run(): void
    {
        $settings = [
            // Application
            'app_name' => 'Nextel Connect',
            'enable_registration' => true,
            'enable_maintenance_mode' => false,
            'enable_email_verification' => true,

            // Contact
            'contact_email' => 'support@nextelconnect.com',
            'contact_phone' => '',
            'contact_address' => '12 Marina Road, Lagos Island, Lagos, Nigeria',

            // Social links
            'telegram_link' => 'https://t.me/nextelconnect',
            'facebook_link' => 'https://facebook.com/nextelconnect',
            'instagram_link' => 'https://instagram.com/nextelconnect',
            'x_link' => 'https://x.com/nextelconnect',
            'website_link' => 'https://nextelconnect.com',
            'tiktok_link' => 'https://tiktok.com/@nextelconnect',
        ];

        Setting::upsert(parse_setting($settings), ['key'], ['value']);

        // Bust the 24h settings cache so config('settings.*') reflects this seed immediately.
        Cache::forget('settings');
    }
}
