<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Product pages (product.n-calls.com)
    |--------------------------------------------------------------------------
    |
    | A guest-facing storefront for the eSIM products, sold WITHOUT an account:
    | the buyer gives a name, phone number and email, pays through the gateway
    | below, and the product is delivered to that email.
    |
    | The flow is registered at /products on EVERY host, so it works before the
    | DNS record or the web-server vhost for the dedicated domain exists; when
    | `host` is set, that host also serves the product index at its root.
    |
    */

    // The dedicated product domain (null/false = /products only).
    'host' => env('PRODUCT_HOST', 'product.n-calls.com'),

    // Where the product pages link back to (the main site). Defaults to APP_URL so a
    // local install never points at the live site by accident.
    'main_url' => env('PRODUCT_MAIN_URL', env('APP_URL', 'http://localhost')),

    // The payment provider this page checks out with. Paystack is the default; any
    // driver registered in PaymentGatewayManager works, because the charge goes
    // through that manager rather than through a hard-coded API call.
    'provider' => env('PRODUCT_PAYMENT_PROVIDER', 'paystack'),

    // Charge currency. Prices are derived from each package's USD price (see `region`).
    'currency' => env('PRODUCT_CURRENCY', 'NGN'),

    // Which region's customer price the storefront sells at. A guest has no account,
    // so there is no per-visitor region to read — this page is priced for the
    // Nigerian market and shows the NGN amount the buyer is actually charged.
    'region' => env('PRODUCT_REGION', 'nigeria'),

    // Which packages are on sale here, in display order, by slug (Str::slug of the
    // package name — "Diamond eSIM" → diamond-esim). A slug with no ACTIVE package
    // behind it is silently dropped rather than rendered broken.
    'products' => array_values(array_filter(array_map('trim', explode(
        ',',
        (string) env('PRODUCT_PACKAGES', 'diamond-esim,royal-esim'),
    )))),

    // Where a paid order is announced so a human can send the product. Falls back to
    // the support inbox in Settings when blank.
    'ops_email' => env('PRODUCT_OPS_EMAIL'),

    // Throttle for starting a checkout (attempts per minute per IP).
    'checkout_throttle' => env('PRODUCT_CHECKOUT_THROTTLE', '10,1'),
];
