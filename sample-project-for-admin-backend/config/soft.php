<?php

return [
    'host' => env('SOFT_HOST', env('DIGITAL_HOST', 'soft.n-calls.com')),
    'main_url' => env('SOFT_MAIN_URL', env('DIGITAL_MAIN_URL', env('APP_URL', 'http://localhost'))),
    'provider' => env('SOFT_PAYMENT_PROVIDER', env('DIGITAL_PAYMENT_PROVIDER', 'paystack')),
    'currency' => env('SOFT_CURRENCY', env('DIGITAL_CURRENCY', 'NGN')),
    'region' => env('SOFT_REGION', env('DIGITAL_REGION', 'nigeria')),
    'ops_email' => env('SOFT_OPS_EMAIL', env('DIGITAL_OPS_EMAIL')),
    'checkout_throttle' => env('SOFT_CHECKOUT_THROTTLE', env('DIGITAL_CHECKOUT_THROTTLE', '10,1')),
];
