<?php

// Gateway credentials are resolved once, at cache time, and everything else in
// this file (including `implemented_payment_gateways`) derives from them.
$paymentGateways = [
    'korapay' => [
        'secret_key' => env('KORAPAY_SECRET_KEY'),
        'public_key' => env('KORAPAY_PUBLIC_KEY'),
        'base_url' => env('KORAPAY_BASE_URL', 'https://api.korapay.com/merchant/api/v1'),
        'balance_path' => env('KORAPAY_BALANCE_PATH', '/balances'),
    ],
    'paystack' => [
        'secret_key' => env('PAYSTACK_SECRET_KEY'),
        'base_url' => env('PAYSTACK_BASE_URL', 'https://api.paystack.co'),
        'balance_path' => env('PAYSTACK_BALANCE_PATH', '/balance'),
    ],
    'flutterwave' => [
        'secret_key' => env('FLUTTERWAVE_SECRET_KEY'),
        'webhook_secret' => env('FLUTTERWAVE_WEBHOOK_SECRET'),
        'base_url' => env('FLUTTERWAVE_BASE_URL', 'https://api.flutterwave.com/v3'),
        'balance_path' => env('FLUTTERWAVE_BALANCE_PATH', '/balances/{currency}'),
    ],
    'bachs' => [
        'secret_key' => env('BACHS_API_KEY'),
        'webhook_secret' => env('BACHS_WEBHOOK_SECRET'),
        'base_url' => env('BACHS_BASE_URL', 'https://api.bachs.io/v1'),
        'balance_path' => env('BACHS_BALANCE_PATH', '/balances'),
    ],
    // Fincra authenticates with an `api-key` header (not a bearer token) and
    // signs webhooks with HMAC-SHA512 over the raw body using the portal's
    // webhook secret. `business_id` is needed for the wallets (balance) API.
    'fincra' => [
        'secret_key' => env('FINCRA_SECRET_KEY'),
        'public_key' => env('FINCRA_PUBLIC_KEY'),
        'business_id' => env('FINCRA_BUSINESS_ID'),
        'webhook_secret' => env('FINCRA_WEBHOOK_SECRET'),
        'base_url' => env('FINCRA_BASE_URL', 'https://api.fincra.com'),
        'balance_path' => env('FINCRA_BALANCE_PATH', '/wallets'),
        'fee_bearer' => env('FINCRA_FEE_BEARER', 'business'),
    ],
];

return [
	'default_currency' => env('DEFAULT_CURRENCY', 'NGN'),
    'support_email' => env('SUPPORT_EMAIL', 'support@example.com'),
    'payment_return_url' => env('PAYMENT_RETURN_URL', rtrim(env('APP_URL', 'http://localhost'), '/').'/payments/return'),
    'payment_webhook_base_url' => env('PAYMENT_WEBHOOK_BASE_URL', env('APP_URL', 'http://localhost')),
    'payment_gateways' => $paymentGateways,
    // Exactly one place decides whether a gateway is usable. `env()` is only
    // ever read inside this config file, which keeps `php artisan config:cache`
    // safe in production.
    'implemented_payment_gateways' => array_values(array_filter([
        'manual',
        filled($paymentGateways['korapay']['secret_key'] ?? null) ? 'korapay' : null,
        filled($paymentGateways['paystack']['secret_key'] ?? null) ? 'paystack' : null,
        filled($paymentGateways['flutterwave']['secret_key'] ?? null) && filled($paymentGateways['flutterwave']['webhook_secret'] ?? null) ? 'flutterwave' : null,
        filled($paymentGateways['bachs']['secret_key'] ?? null) && filled($paymentGateways['bachs']['webhook_secret'] ?? null) ? 'bachs' : null,
        filled($paymentGateways['fincra']['secret_key'] ?? null) && filled($paymentGateways['fincra']['webhook_secret'] ?? null) ? 'fincra' : null,
    ])),
    // Verified deep links, served at /.well-known/assetlinks.json and
    // /.well-known/apple-app-site-association. Each endpoint answers 404 until
    // its identifiers are configured, so a half-configured deployment never
    // publishes a file that would fail app-link verification.
    'deep_links' => [
        'android_package' => env('ANDROID_APP_PACKAGE', 'pynith.tlstore.review'),
        'android_sha256_fingerprints' => array_values(array_filter(array_map(
            static fn ($fingerprint): string => strtoupper(trim((string) $fingerprint)),
            explode(',', (string) env('ANDROID_SHA256_CERT_FINGERPRINTS', '')),
        ))),
        // Full `TEAMID.bundle.id`, e.g. A1B2C3D4E5.pynith.tlstore.review
        'ios_app_id' => env('IOS_APP_ID'),
        'ios_paths' => ['/register', '/register/*', '/login', '/ref/*'],
    ],
    'currency_minor_units' => [
        'BIF' => 0, 'CLP' => 0, 'DJF' => 0, 'GNF' => 0, 'ISK' => 0, 'JPY' => 0,
        'KMF' => 0, 'KRW' => 0, 'PYG' => 0, 'RWF' => 0, 'UGX' => 0, 'VND' => 0,
        'VUV' => 0, 'XAF' => 0, 'XOF' => 0, 'XPF' => 0,
        'BHD' => 3, 'IQD' => 3, 'JOD' => 3, 'KWD' => 3, 'LYD' => 3,
        'OMR' => 3, 'TND' => 3,
    ],
];
