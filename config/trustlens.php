<?php

return [
    'support_email' => env('SUPPORT_EMAIL', 'support@example.com'),
    'payment_return_url' => env('PAYMENT_RETURN_URL', rtrim(env('APP_URL', 'http://localhost'), '/').'/payments/return'),
    'payment_webhook_base_url' => env('PAYMENT_WEBHOOK_BASE_URL', env('APP_URL', 'http://localhost')),
    'payment_gateways' => [
        'korapay' => [
            'secret_key' => env('KORAPAY_SECRET_KEY'),
            'public_key' => env('KORAPAY_PUBLIC_KEY'),
            'base_url' => env('KORAPAY_BASE_URL', 'https://api.korapay.com/merchant/api/v1'),
        ],
        'paystack' => [
            'secret_key' => env('PAYSTACK_SECRET_KEY'),
            'base_url' => env('PAYSTACK_BASE_URL', 'https://api.paystack.co'),
        ],
        'flutterwave' => [
            'secret_key' => env('FLUTTERWAVE_SECRET_KEY'),
            'webhook_secret' => env('FLUTTERWAVE_WEBHOOK_SECRET'),
            'base_url' => env('FLUTTERWAVE_BASE_URL', 'https://api.flutterwave.com/v3'),
        ],
        'bachs' => [
            'secret_key' => env('BACHS_API_KEY'),
            'webhook_secret' => env('BACHS_WEBHOOK_SECRET'),
            'base_url' => env('BACHS_BASE_URL', 'https://api.bachs.io/v1'),
        ],
    ],
    'implemented_payment_gateways' => array_values(array_filter([
        'manual',
        env('KORAPAY_SECRET_KEY') ? 'korapay' : null,
        env('PAYSTACK_SECRET_KEY') ? 'paystack' : null,
        env('FLUTTERWAVE_SECRET_KEY') && env('FLUTTERWAVE_WEBHOOK_SECRET') ? 'flutterwave' : null,
        env('BACHS_API_KEY') && env('BACHS_WEBHOOK_SECRET') ? 'bachs' : null,
    ])),
    'currency_minor_units' => [
        'BIF' => 0, 'CLP' => 0, 'DJF' => 0, 'GNF' => 0, 'ISK' => 0, 'JPY' => 0,
        'KMF' => 0, 'KRW' => 0, 'PYG' => 0, 'RWF' => 0, 'UGX' => 0, 'VND' => 0,
        'VUV' => 0, 'XAF' => 0, 'XOF' => 0, 'XPF' => 0,
        'BHD' => 3, 'IQD' => 3, 'JOD' => 3, 'KWD' => 3, 'LYD' => 3,
        'OMR' => 3, 'TND' => 3,
    ],
];
