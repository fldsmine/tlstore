<?php

namespace Database\Seeders;

use App\Models\Admin;
use App\Models\SystemSetting;
use Illuminate\Database\Seeder;

class DemoAdminSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment(['local', 'testing', 'staging'])) {
            return;
        }
        $password = (string) config('app.system_account_password');

        $admin = Admin::updateOrCreate(
            ['email' => 'admin@tlstore.test'],
            [
                'full_name' => 'TrustLens Demo Admin',
                'password' => $password,
                'role' => 'super_admin',
                'status' => 'active',
            ],
        );

        SystemSetting::updateOrCreate(
            ['setting_key' => 'payment.active_gateways'],
            ['value' => [
                'korapay' => ['NGN', 'GHS', 'KES', 'UGX', 'USD'],
                'paystack' => ['NGN', 'GHS', 'ZAR', 'USD'],
                'flutterwave' => ['NGN', 'USD', 'GHS', 'KES', 'UGX', 'ZAR', 'GBP', 'EUR'],
                'bachs' => ['USD', 'NGN', 'GHS', 'KES', 'ZAR'],
                'manual' => [],
            ], 'is_public' => true, 'updated_by_admin_id' => $admin->id],
        );

        SystemSetting::updateOrCreate(
            ['setting_key' => 'payment.active_payment_currency'],
            ['value' => [
                'bachs.NGN' => 'NGN',
                'paystack.NGN' => 'NGN-2',
                'flutterwave.USD' => 'USD',
                'bachs.USD' => 'USD',
                'paystack.GHS' => 'GHS',
                'korapay.UGX' => 'UGX',
            ], 'is_public' => true, 'updated_by_admin_id' => $admin->id],
        );

        foreach ([
            'rewards.referral_subscription_active',
            'rewards.referral_task_active',
            'rewards.subscription_bonus_active',
            'rewards.social_follow_active',
        ] as $settingKey) {
            SystemSetting::updateOrCreate(
                ['setting_key' => $settingKey],
                ['value' => true, 'is_public' => false, 'updated_by_admin_id' => $admin->id],
            );
        }
    }
}
