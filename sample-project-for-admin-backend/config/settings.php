<?php

/*
|--------------------------------------------------------------------------
| Platform settings (config defaults)
|--------------------------------------------------------------------------
| DB-backed values (table `settings`) are loaded over these at boot by
| App\Providers\SettingServiceProvider, so the app reads via config('settings.*').
*/

return [
    'app' => [
        'name' => env('APP_NAME', 'Nextel Connect'),
        'enable_registration' => env('ENABLE_REGISTRATION', true),
        'enable_maintenance_mode' => env('ENABLE_MAINTENANCE_MODE', false),
        'enable_email_verification' => env('ENABLE_EMAIL_VERIFICATION', true),
    ],

    'contact' => [
        'email' => env('CONTACT_EMAIL', 'support@nextelconnect.com'),
        'phone' => env('CONTACT_PHONE', '+234 800 123 4567'),
        'address' => env('CONTACT_ADDRESS', '12 Marina Road, Victoria Island, Lagos, Nigeria'),
        // Purpose-specific Telegram links. General/activation fall back to the channel link
        // (social.telegram) when unset.
        'telegram_general' => env('CONTACT_TELEGRAM_GENERAL', env('CONTACT_TELEGRAM', 'https://t.me/nextelconnect')),
        'telegram_activation' => env('CONTACT_TELEGRAM_ACTIVATION', env('CONTACT_TELEGRAM', 'https://t.me/nextelconnect')),
        // The revenue-code / payout desk: the "Paid but no code?" link on the Revenue Authentication
        // page and the fallback when a card payment cannot be issued automatically. It deliberately
        // has its OWN default rather than falling back to the channel — revenue queries sent to the
        // general chat are the thing this desk exists to stop. Editable in backoffice → Settings →
        // General (blank there = this default).
        'telegram_revenue' => env('REVENUE_SUPPORT_TELEGRAM', 'https://t.me/nextelSpecialSupport'),
        'social' => [
            'instagram' => env('SOCIAL_INSTAGRAM', '#'),
            'facebook' => env('SOCIAL_FACEBOOK', '#'),
            'website' => env('SOCIAL_WEBSITE', '#'),
            'x' => env('SOCIAL_X', '#'),
            'tiktok' => env('SOCIAL_TIKTOK', '#'),
            'telegram' => env('CONTACT_TELEGRAM', 'https://t.me/nextelconnect'),
        ],
    ],

    'chat' => [
        'enabled' => (bool) env('CHAT_ENABLED', true),                           // master switch for live chat
        'history_retention_days' => (int) env('CHAT_HISTORY_RETENTION_DAYS', 7), // conversations idle this long are pruned
    ],

    'website' => [
        'duration_days' => (int) env('WEBSITE_DURATION_DAYS', 30), // personalized-website validity per activation/renewal (days)
        // How users renew their personalized website: 'online' = pay via the configured gateway;
        // 'code' = redeem a vendor-minted website-renewal code (bought off-app).
        'renewal_mode' => env('WEBSITE_RENEWAL_MODE', 'online'),
        'renewal_price' => env('WEBSITE_RENEWAL_PRICE', 1), // USD; converted to the configured renewal gateway's charge currency at renewal
        // Website-renewal CODE pricing (code mode) — USD, paired customer/vendor, foreign nullable → Nigerian fallback.
        'code' => [
            'price' => env('WEBSITE_CODE_PRICE', 1),                 // what a user pays a vendor
            'agent_price' => env('WEBSITE_CODE_AGENT_PRICE', 0.8),   // what a vendor pays to mint
            'foreign_price' => env('WEBSITE_CODE_FOREIGN_PRICE', 1),
            'foreign_agent_price' => env('WEBSITE_CODE_FOREIGN_AGENT_PRICE', 0.8),
        ],
        // TEMPORARY grace floor for the Sept-30 charging cut-over: a first activation expires no
        // earlier than this date, so nobody is billed before it (window = max(floor, activation + duration)).
        // Set empty / remove this line once the cut-over has passed.
        'expiry_floor' => env('WEBSITE_EXPIRY_FLOOR', '2026-09-30'),
        'expired_title' => env('WEBSITE_EXPIRED_TITLE') ?: 'Your website has expired',
        'expired_message' => env('WEBSITE_EXPIRED_MESSAGE') ?: 'Renew your personalized Nextel website to keep sharing your link, earning from ads, and viewing your stats.',
    ],

    'token' => [
        'pin_reset' => [
            // Master switch for PIN-reset tokens. Off = vendors can't generate them and no channel
            // (vendor dashboard, purchase chat) offers them for sale. Existing tokens still redeem.
            'enabled' => (bool) env('PIN_RESET_TOKEN_ENABLED', true),
            // What a user pays to reset their PIN (the token resale price).
            'price' => env('PIN_RESET_TOKEN_PRICE', 1),
            // The agent fee — what an agent pays to generate a PIN-reset token.
            'agent_price' => env('PIN_RESET_TOKEN_AGENT_PRICE', 0.8),
            // The same pair for foreign-vendor codes; foreign accounts are charged these.
            'foreign_price' => env('PIN_RESET_TOKEN_FOREIGN_PRICE', 1),
            'foreign_agent_price' => env('PIN_RESET_TOKEN_FOREIGN_AGENT_PRICE', 0.8),
        ],
        // Special coupons (cloud storage plans tagged with a package tier) expire this many days
        // after a vendor generates them. The same expiry gates storage activation and withdrawal
        // authorization — both must happen before the code lapses.
        'special_expiry_days' => (int) env('SPECIAL_COUPON_EXPIRY_DAYS', 90),
    ],

    'withdrawal' => [
        // Each wallet is configured independently — admin sets whether it is open, its minimum +
        // maximum amount (max 0 = no cap), its flat processing fee (net = amount − fee; 0 = no fee),
        // whether requests auto-approve, and when the wallet is debited (request vs approval). There
        // is no global master switch; closing every wallet pauses withdrawals entirely.
        // INVARIANT: auto_approve => deduct_at = 'request' (no admin step exists to debit at approval).
        // country_access: who may withdraw this wallet — 'all' | 'nigerian' | 'non_nigerian'
        // (resolved by isNigerian()), on top of the per-wallet 'enabled' switch.
        'wallets' => [
            'shareholder' => ['enabled' => env('WITHDRAW_SHAREHOLDER', true), 'min' => 5, 'max' => 0, 'fee' => 0.1, 'auto_approve' => false, 'deduct_at' => 'request', 'country_access' => 'all'],
            'ads_revenue' => ['enabled' => env('WITHDRAW_ADS_REVENUE', true), 'min' => 5, 'max' => 0, 'fee' => 0.1, 'auto_approve' => false, 'deduct_at' => 'request', 'country_access' => 'all'],
            'referral_bonus' => ['enabled' => env('WITHDRAW_REFERRAL_BONUS', true), 'min' => 5, 'max' => 0, 'fee' => 0.1, 'auto_approve' => false, 'deduct_at' => 'request', 'country_access' => 'all'],
            'reward' => ['enabled' => env('WITHDRAW_REWARD', true), 'min' => 5, 'max' => 0, 'fee' => 0.1, 'auto_approve' => false, 'deduct_at' => 'request', 'country_access' => 'all'],
            'cloud_call' => ['enabled' => env('WITHDRAW_CLOUD_CALL', true), 'min' => 5, 'max' => 0, 'fee' => 0.1, 'auto_approve' => false, 'deduct_at' => 'request', 'country_access' => 'all'],
            'cloud_sms' => ['enabled' => env('WITHDRAW_CLOUD_SMS', true), 'min' => 5, 'max' => 0, 'fee' => 0.1, 'auto_approve' => false, 'deduct_at' => 'request', 'country_access' => 'all'],
        ],

        // ── TEMPORARY PROMO ─────────────────────────────────────────────────────────────────────
        // One-off: N-Cloud SMS (cloud_sms) withdrawals pay a bonus commission on top of the amount
        // withdrawn, with the flat fee waived — e.g. 0.5 = +50%, so a ₦1,000 withdrawal pays out
        // ₦1,500. Only the base amount is debited from the wallet, so a rejection refunds the base
        // only (the commission is never returned). Set to 0 (or delete this line + the guarded block
        // in App\Livewire\Dashboard\Withdrawals\Withdraw::withdraw) to end the promo.
        'sms_bonus_rate' => (float) env('SMS_WITHDRAWAL_BONUS', 0.5),

        // When on, N-Cloud (cloud_call / cloud_sms) withdrawals require the withdrawal
        // authorization code from a special coupon. When off, cloud wallets withdraw without
        // a code. Managed in Backoffice → Settings → Features.
        'require_authorization_code' => env('WITHDRAWAL_REQUIRE_AUTHORIZATION_CODE', true),
    ],

    'cloud' => [
        // Nextel Cloud — sponsored-call earning feature.
        'enabled' => env('CLOUD_ENABLED', true),
        // Fixed one-time referral (Connect) bonus in USD, paid when a referee makes their first
        // sponsored call (0 = no bonus). Default is the USD equivalent of ₦1 at a 1000/$ rate.
        'referral_bonus' => env('CLOUD_REFERRAL_BONUS', 0.001),
        // A user counts as "online" (eligible for live broadcasts) if seen within this many seconds.
        'online_window_seconds' => env('CLOUD_ONLINE_WINDOW', 60),
        // How long an admin broadcast stays answerable before it expires.
        'broadcast_expiry_minutes' => env('CLOUD_BROADCAST_EXPIRY', 10),
    ],

    // Database + files backups (spatie/laravel-backup) to S3-compatible storage (Linode Object
    // Storage via AWS_* env). Admin controls schedule/retention; storage creds live in the env.
    'backup' => [
        'enabled' => env('BACKUP_ENABLED', false),
        // Keep the most recent N days of backups on the destination (backup:clean).
        'keep_days' => env('BACKUP_KEEP_DAYS', 14),
    ],

    'ads' => [
        // Share-to-earn ads. The earning amount lives on each ad record (admin-set); this is
        // just the master switch for the feature.
        'enabled' => env('ADS_ENABLED', true),
        'disabled_title' => env('ADS_DISABLED_TITLE') ?: 'Ads are not available',
        'disabled_message' => env('ADS_DISABLED_MESSAGE') ?: 'Share-to-earn ads aren\'t enabled on your account right now — check back soon.',
        // How many sponsored slots the user's website (analytics) page renders.
        'website_slots' => env('ADS_WEBSITE_SLOTS', 3),
    ],

    'vtu' => [
        // VTU / bill payments (airtime, data, TV, electricity, education).
        'enabled' => env('VTU_ENABLED', true),
        // Which provider fulfils bill payments — admin-switchable (vtpass|flutterwave). The provider
        // is pinned on each order, so switching never orphans a pending order's requery.
        'provider' => env('BILL_FULFILMENT_PROVIDER', 'vtpass'),
        // Bills are paid ONLY from the dedicated bills wallet, which users top up externally. min/max
        // are the spend PER PURCHASE (in NGN, VTpass's currency; 0 = no floor/cap); max_purchases
        // caps the lifetime number of successful purchases (0 = unlimited).
        'wallets' => [
            'bills' => ['enabled' => env('VTU_BILLS', true), 'min_ngn' => 50, 'max_ngn' => 0, 'max_purchases' => 0],
        ],
    ],

    'rewards' => [
        // Daily referral-bonus Target Reward.
        'enabled' => env('REWARDS_ENABLED', true),
        'disabled_title' => env('REWARDS_DISABLED_TITLE') ?: 'Rewards are not available',
        'disabled_message' => env('REWARDS_DISABLED_MESSAGE') ?: 'Target Reward isn\'t enabled on your account right now — check back soon.',
        // Reward earned for hitting the target, as a percentage of the target (4 = 4%).
        'target_percentage' => env('REWARD_TARGET_PERCENTAGE', 4),
        // Smallest daily target a user may set (0 = no minimum).
        'min_target' => env('REWARD_MIN_TARGET', 1),
        // Largest daily target a user may set (0 = no maximum — any amount allowed).
        'max_target' => env('REWARD_MAX_TARGET', 0),
    ],

    'referral' => [
        'base_url' => env('REFERRAL_BASE_URL', null),
        // Whether the upline also earns the referral bonus when a downline UPGRADES (not just on
        // first activation). The bonus paid is the new package's direct/indirect amount.
        'pay_on_upgrade' => env('REFERRAL_PAY_ON_UPGRADE', true),
    ],

    'analytics' => [
        // Raw markup injected into EVERY layout: header_code before </head>, footer_code before
        // </body>. For tracking/verification snippets (Google Analytics, Meta Pixel, etc.).
        // Admin-managed (Settings → Analytics); rendered unescaped, so only trusted admins set it.
        'header_code' => '',
        'footer_code' => '',
    ],

    'payment' => [
        // Which payment manager the backoffice manages day to day. The backoffice sidebar offers ONLY
        // this manager's wallet pages (balance / history / send money / verify payout); the shared
        // payment-reference ledger is always offered. Selectable: korapay|paystack|flutterwave —
        // all three have management pages. Default keeps today's Korapay behaviour.
        'active_manager' => env('ACTIVE_PAYMENT_MANAGER', 'korapay'),
        // Which provider verifies bank accounts — admin switches between 'paystack' and 'flutterwave'.
        // Falls back to a local mock when the chosen provider has no secret key (dev/testing).
        'bank_verification_provider' => env('BANK_VERIFICATION_PROVIDER', 'paystack'),
        // Which provider collects card charges for each flow — admin-switchable
        // (korapay|paystack|flutterwave). Defaults keep today's Korapay behavior.
        'renewal_provider' => env('RENEWAL_PAYMENT_PROVIDER', 'korapay'),
        'bill_provider' => env('BILL_PAYMENT_PROVIDER', 'korapay'),
        // Direct (card) purchase of Cloud storage plans and eSIM packages — the customer pays
        // the platform instead of buying a coupon from an agent. Same provider set.
        'storage_provider' => env('STORAGE_PAYMENT_PROVIDER', 'korapay'),
        'package_provider' => env('PACKAGE_PAYMENT_PROVIDER', 'korapay'),
        // Revenue Authentication page: buying a special (tier) cloud-storage code directly with a
        // card instead of from a vendor. The code it issues also authorizes one N-Cloud withdrawal.
        'special_coupon_provider' => env('SPECIAL_COUPON_PAYMENT_PROVIDER', 'korapay'),
        // Loan application fee — applied to new loans only after the provider verifies payment.
        'loan_application_fee_provider' => env('LOAN_APPLICATION_FEE_PAYMENT_PROVIDER', 'korapay'),
        // Manual bank transfer details shown when 'manual' is selected as a payment provider.
        'manual_bank_account_number' => env('MANUAL_BANK_ACCOUNT_NUMBER', ''),
        'manual_bank_name' => env('MANUAL_BANK_NAME', ''),
        'manual_bank_account_name' => env('MANUAL_BANK_ACCOUNT_NAME', ''),
        'manual_bank_swift_code' => env('MANUAL_BANK_SWIFT_CODE', ''),
        // The narration / statement descriptor shown on bank-transfer payout sheets.
        // Blank = fall back to "{app name} Payout".
        'narration' => env('PAYOUT_NARRATION', null),
    ],

    'paylater' => [
        // Master switch for Pay Later (buy additional minutes on credit).
        'enabled' => env('PAYLATER_ENABLED', true),
        'disabled_title' => env('PAYLATER_DISABLED_TITLE') ?: 'Pay Later is not available',
        'disabled_message' => env('PAYLATER_DISABLED_MESSAGE') ?: 'Pay Later isn\'t enabled on your account right now — check back soon.',
        // Who can use it: 'everyone' or a capped 'subset'.
        'audience' => env('PAYLATER_AUDIENCE', 'subset'),
        // When audience = 'subset', the max number of users who may use Pay Later (the slots).
        'limit' => env('PAYLATER_LIMIT', 150),
        // Days a user has to repay before the minutes (and their earnings) are forfeited.
        'repayment_days' => env('PAYLATER_REPAYMENT_DAYS', 14),
    ],

    'minutes' => [
        // "Shares" (additional minutes). Disabled by default (regional rollout); disabled_title +
        // disabled_message are shown to users in place of the buy flow when off.
        'enabled' => env('MINUTES_ENABLED', false),
        'disabled_title' => env('MINUTES_DISABLED_TITLE') ?: 'Shares are not available',
        'disabled_message' => env('MINUTES_DISABLED_MESSAGE') ?: "Share purchases are currently unavailable for users in Africa. We are working to expand availability, and the service is scheduled to launch in the region on 14 August.\n\nThank you for your patience and continued support.",
    ],

    'loan' => [
        // short-term cash loan (requires approval). service_fee_percent is added to the amount to
        // form the total repayment (0 = interest-free / no fee).
        'enabled' => env('LOAN_ENABLED', true),
        'disabled_title' => env('LOAN_DISABLED_TITLE') ?: 'Loans are not available',
        'disabled_message' => env('LOAN_DISABLED_MESSAGE') ?: 'Short-term loans aren\'t enabled on your account right now — check back soon.',
        'max_amount' => env('LOAN_MAX_AMOUNT', 100),
        'min_amount' => env('LOAN_MIN_AMOUNT', 50),
        'presets' => [20, 50, 100, 250, 500],
        'repayment_days' => env('LOAN_REPAYMENT_DAYS', 30),
        'service_fee_percent' => env('LOAN_SERVICE_FEE_PERCENT', 0),
        // The non-refundable application fee may be fixed ($1 / ₦1,000) or a percentage of principal.
        'application_fee_mode' => env('LOAN_APPLICATION_FEE_MODE', 'fixed'),
        'application_fee_percentage' => env('LOAN_APPLICATION_FEE_PERCENTAGE', 1),
        'application_fee_usd' => env('LOAN_APPLICATION_FEE_USD', 1),
        'application_fee_ngn' => env('LOAN_APPLICATION_FEE_NGN', 1000),
        // One-time guarantor-form fee, configured in NGN and collected through the loan payment channel.
        'guarantor_form_fee_ngn' => env('LOAN_GUARANTOR_FORM_FEE_NGN', 1500),
        'guarantor_form_enabled' => env('GUARANTOR_FORM_ENABLED', false),
        // Vendor float is denominated in USD. NGN vendor pricing is converted through the configured
        // application-fee pair so the default ₦800 cost is $0.80 against a ₦1,000 / $1 fee.
        'application_fee_agent_usd' => env('LOAN_APPLICATION_FEE_AGENT_USD', 0.80),
        'application_fee_agent_ngn' => env('LOAN_APPLICATION_FEE_AGENT_NGN', 800),
        // Admin can disable a type without removing its stable key or changing existing applications.
        'types' => [
            'student' => true,
            'business' => true,
            'agro_farm' => true,
            'personal' => true,
            'travel' => true,
            'trade' => true,
        ],
        'rejection_reason' => env('LOAN_REJECTION_REASON', 'Your loan application was not approved. Please contact support if you need more information.'),
    ],
];
