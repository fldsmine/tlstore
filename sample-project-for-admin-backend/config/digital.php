<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Digital product pages (soft.n-calls.com)
    |--------------------------------------------------------------------------
    |
    | A guest-facing storefront for the digital products (catalog products only,
    | not the eSIM plans): the buyer gives a name, phone number and email, pays
    | through Paystack by default, and the product is delivered to that email.
    |
    */

    'host' => env('DIGITAL_HOST', 'app.n-calls.com'),

    'main_url' => env('DIGITAL_MAIN_URL', env('APP_URL', 'http://localhost')),

    'provider' => env('DIGITAL_PAYMENT_PROVIDER', 'paystack'),

    'currency' => env('DIGITAL_CURRENCY', 'NGN'),

    'region' => env('DIGITAL_REGION', 'nigeria'),

    'ops_email' => env('DIGITAL_OPS_EMAIL'),

    'checkout_throttle' => env('DIGITAL_CHECKOUT_THROTTLE', '10,1'),
];
