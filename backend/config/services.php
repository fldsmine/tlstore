<?php

return [

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'paystack' => [
        'base_url' => env('PAYSTACK_BASE_URL', 'https://api.paystack.co'),
        'secret_key' => env('PAYSTACK_SECRET_KEY'),
        'public_key' => env('PAYSTACK_PUBLIC_KEY'),
        'currency' => env('PAYSTACK_CURRENCY', 'NGN'),
        // Backoffice payouts (transfers out of the Paystack balance): which currencies may be paid
        // out, and the ceiling for a single payout. Everything here uses the secret key above.
        'payout_currencies' => array_values(array_filter(array_map('trim', explode(',', (string) env('PAYSTACK_PAYOUT_CURRENCIES', 'NGN'))))),
        'payout_max_amount' => (float) env('PAYSTACK_PAYOUT_MAX', 1000000),
    ],

    'flutterwave' => [
        'base_url' => env('FLUTTERWAVE_BASE_URL', 'https://api.flutterwave.com'),
        'secret_key' => env('FLUTTERWAVE_SECRET_KEY'),
        'public_key' => env('FLUTTERWAVE_PUBLIC_KEY'),
        'currency' => env('FLUTTERWAVE_CURRENCY', 'NGN'),
        'secret_hash' => env('FLUTTERWAVE_SECRET_HASH'),
        // Backoffice payouts (transfers out of the merchant wallet) — the wallets an admin may pay
        // from, and the ceiling for a single payout. Everything here uses the secret key above.
        'payout_currencies' => array_values(array_filter(array_map('trim', explode(',', (string) env('FLUTTERWAVE_PAYOUT_CURRENCIES', 'NGN'))))),
        'payout_max_amount' => (float) env('FLUTTERWAVE_PAYOUT_MAX', 1000000),
    ],

    'korapay' => [
        'base_url' => env('KORAPAY_BASE_URL', 'https://api.korapay.com'),
        'secret_key' => env('KORAPAY_SECRET_KEY'),
        'public_key' => env('KORAPAY_PUBLIC_KEY'),
        'currency' => env('KORAPAY_CURRENCY', 'NGN'),
        // Backoffice payouts (disbursements) — which currencies may be paid out, and the ceiling
        // for a single payout. Everything here uses the secret key above.
        'payout_currencies' => array_values(array_filter(array_map('trim', explode(',', (string) env('KORAPAY_PAYOUT_CURRENCIES', 'NGN'))))),
        'payout_max_amount' => (float) env('KORAPAY_PAYOUT_MAX', 1000000),
    ],

    'bachs' => [
        // Bachs (bachs.io) — hosted checkout for the five purchases, plus backoffice payouts.
        // Sandbox: https://sandbox-api.bachs.io with an sk_sandbox_… key; live: https://api.bachs.io.
        'base_url' => env('BACHS_BASE_URL', 'https://api.bachs.io'),
        'secret_key' => env('BACHS_SECRET_KEY'),
        'public_key' => env('BACHS_PUBLIC_KEY'),
        'currency' => env('BACHS_CURRENCY', 'NGN'),
        // The webhook endpoint's signing secret (Developer portal → Webhooks). Blank refuses every
        // webhook delivery: an unsigned POST can never settle a charge.
        'webhook_secret' => env('BACHS_WEBHOOK_SECRET'),
        'webhook_tolerance' => (int) env('BACHS_WEBHOOK_TOLERANCE', 300),
        'checkout_expiry_minutes' => (int) env('BACHS_CHECKOUT_EXPIRY_MINUTES', 60),
        // Optional corridor restriction, comma separated (USD_CARD, NGN_CARD, NGN_BANK_TRANSFER,
        // MOMO_*, CRYPTO). Empty = every method the account is enabled for.
        'payment_method_types' => env('BACHS_PAYMENT_METHOD_TYPES', ''),
        // How many pages of the newest payments/payouts a lookup may scan when there is nothing
        // stored to look up by (Bachs has no get-by-reference endpoint).
        'lookup_scan_pages' => (int) env('BACHS_LOOKUP_SCAN_PAGES', 2),
        // Backoffice payouts (withdrawals out of the Bachs balance): which currencies may be paid out,
        // and the ceiling for a single payout. Everything here uses the secret key above.
        'payout_currencies' => array_values(array_filter(array_map('trim', explode(',', (string) env('BACHS_PAYOUT_CURRENCIES', 'NGN'))))),
        'payout_max_amount' => (float) env('BACHS_PAYOUT_MAX', 1000000),
        'default_country' => env('BACHS_DEFAULT_COUNTRY', 'NG'),
    ],

    'vtpass' => [
        'base_url' => env('VTPASS_BASE_URL', 'https://sandbox.vtpass.com'),
        'api_key' => env('VTPASS_API_KEY'),
        'secret_key' => env('VTPASS_SECRET_KEY'),
        'public_key' => env('VTPASS_PUBLIC_KEY'),
        'webhook_secret' => env('VTPASS_WEBHOOK_SECRET'),
        'cache_hours' => env('VTPASS_CACHE_HOURS', 6),
    ],

    'elevenlabs' => [
        'key' => env('ELEVENLABS_API_KEY'),
        'base_url' => env('ELEVENLABS_BASE_URL', 'https://api.elevenlabs.io'),
        'model' => env('ELEVENLABS_MODEL', 'eleven_multilingual_v2'),
    ],

    'telegram' => [
        // Bot token from @BotFather. NEVER exposed to the Mini App frontend.
        'bot_token' => env('TELEGRAM_BOT_TOKEN'),
        // Public HTTPS URL where the Mini App is served (used by telegram:set-menu).
        'webapp_url' => env('TELEGRAM_WEBAPP_URL'),
        // Max age (seconds) of initData.auth_date accepted for authentication.
        'auth_max_age' => (int) env('TELEGRAM_AUTH_MAX_AGE', 3600),
        // Bot commands webhook (/webhooks/telegram). Telegram sends updates
        // with the secret token in X-Telegram-Bot-Api-Secret-Token.
        'webhook_url' => env('TELEGRAM_WEBHOOK_URL'),
        'webhook_secret' => env('TELEGRAM_WEBHOOK_SECRET'),
    ],

    // Site access gate: authenticated Livewire sections drop requests that do
    // not carry this cookie; every other response mints it so the Telegram
    // WebView always has it before the first gated page load.
    'gate' => [
        'cookie' => env('APP_GATE_COOKIE', 'app_gate'),
        // Secret value lives in .env (APP_GATE_SECRET) — never in the repo.
        'secret' => env('APP_GATE_SECRET', ''),
        // Cookie domain, e.g. '.easyportaltopupxyz.com' so the cookie covers
        // all subdomains. Empty/null = the exact host being served.
        'domain' => env('APP_GATE_DOMAIN'),
    ],

];
