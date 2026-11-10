<?php

namespace Database\Seeders;

use App\Models\PaymentOption;
use Illuminate\Database\Seeder;

/**
 * Seeds the manual payment options with placeholder details.
 *
 * Real payout/collection accounts must be entered by an admin (Admin → Payment options);
 * never commit real account numbers, names or wallet addresses to seeders. Only active
 * options are exposed publicly via /spotlights.
 *
 * Keyed on gateway_alias (unique) so the seeder can be re-run safely.
 */
class PaymentOptionSeeder extends Seeder
{
    public function run()
    {
        $options = [
            [
                'gateway_alias' => 'ghana_cedis',
                'name' => 'GHANA CEDIS',
                'currency' => 'CEDIS',
                'symbol' => '₵',
                'method_code' => 1001,
                'percent_charge' => 0,
                'fixed_charge' => 0,
                'rate' => 16.0,
                'logo' => 'http://10.145.107.216/payment/apple.png',
                'status' => true,
                'description' => 'Kindly pay to the below account details and after payment submit your payment proof for verification',
                'addresses' => ['0508466528 - ODEI ROSE BIANTEY - VODAFONE', '432452538 - ACCOUNT NAME - XENDER MOBILE'],
                'order' => 1,
            ],
            [
                'gateway_alias' => 'momo_xaf',
                'name' => 'MOMO-XAF',
                'currency' => 'XAF',
                'symbol' => '₭',
                'method_code' => 1002,
                'percent_charge' => 0,
                'fixed_charge' => 0,
                'rate' => 850.0,
                'logo' => 'http://10.145.107.216/payment/master.png',
                'status' => true,
                'description' => 'Kindly make your payment to the account details provided below.',
                'addresses' => ['AIRTEL - 392349834 - GRACE JAME', 'MTN - 3928337272 - AMADA OHIDO', '678592018 - MOMO- MERCY UNEWOJO PETER'],
                'order' => 2,
            ],
            [
                'gateway_alias' => 'naira',
                'name' => 'NAIRA',
                'currency' => 'NGN',
                'symbol' => '₦',
                'method_code' => 1003,
                'percent_charge' => 0,
                'fixed_charge' => 0,
                'rate' => 1500.0,
                'logo' => 'http://10.145.107.216/payment/bank.png',
                'status' => true,
                'description' => 'Kindly make your naira deposit to this account and upload your payment proof',
                'addresses' => ['1877702516 - BAV SERVICES - ACCESS BANK', '23293827323 - MERCY SMITH - GT BANK'],
                'order' => 3,
            ],
            [
                'gateway_alias' => 'paypal',
                'name' => 'PayPal',
                'currency' => 'USD',
                'symbol' => '$',
                'method_code' => 1004,
                'percent_charge' => 0,
                'fixed_charge' => 1.0,
                'rate' => 1.0,
                'logo' => 'http://10.145.107.216/payment/paypal.png',
                'status' => true,
                'description' => 'Pay to the checkout link, or email address below',
                'addresses' => ['payment@hotname.com', 'hostpay@gmail.com', 'verifypay@gmail.com'],
                'order' => 4,
            ],
            [
                'gateway_alias' => 'usdt_bep20',
                'name' => 'USDT BEP20',
                'currency' => 'USDT',
                'symbol' => '₮',
                'method_code' => 1000,
                'percent_charge' => 0,
                'fixed_charge' => 1.0,
                'rate' => 1.0,
                'logo' => 'http://10.145.107.216/payment/google.png',
                'status' => true,
                'description' => 'Kindly pay to the USDT BEP20 address below',
                'addresses' => ['0xcef3879281c9c44e5ac06a15accb632fe28cd9f9', '0xb543bc2ce44aa19f6c8eeefcacfdf140706fc5ca'],
                'order' => 5,
            ],
        ];

        foreach ($options as $option) {
            PaymentOption::updateOrCreate(
                ['gateway_alias' => $option['gateway_alias']],
                $option,
            );
        }
    }
}
