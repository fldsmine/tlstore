<?php

namespace App\Application\Settings;

/**
 * Human-friendly definition of every system setting the console edits.
 *
 * The generic module editor exposed raw JSON, which needed a developer. This
 * schema gives each setting a label, a help text, an editor type (toggle,
 * number, picker, repeatable list, gateway matrix, …) and a section so the
 * dedicated screen can render real form controls.
 *
 * Anything stored in `system_settings` that is not listed here still appears on
 * the "Advanced" tab as a JSON field, unless it is sensitive (tokens, secrets)
 * or managed by another screen.
 */
final class SystemSettingsSchema
{
    /** @var array<string, string> */
    public const GATEWAYS = [
        'korapay' => 'Korapay',
        'paystack' => 'Paystack',
        'flutterwave' => 'Flutterwave',
        'bachs' => 'Bachs',
        'fincra' => 'Fincra',
        'manual' => 'Manual bank transfer',
    ];

    /** Currency columns offered in the gateway matrix. */
    public const CURRENCIES = ['NGN', 'USD', 'GBP', 'EUR', 'GHS', 'KES', 'UGX', 'TZS', 'ZMW', 'ZAR', 'XAF', 'XOF'];

    /**
     * Currency set each gateway ships with (used to preselect a freshly added
     * gateway; the saved value is always what the admin ticked).
     *
     * @var array<string, list<string>>
     */
    public const GATEWAY_CURRENCIES = [
        'korapay' => ['NGN', 'GHS', 'KES', 'UGX', 'USD'],
        'paystack' => ['NGN', 'GHS', 'ZAR', 'USD'],
        'flutterwave' => ['NGN', 'USD', 'GHS', 'KES', 'UGX', 'ZAR', 'GBP', 'EUR'],
        'bachs' => ['USD', 'NGN', 'GHS', 'KES', 'ZAR'],
        'fincra' => ['NGN', 'GHS', 'KES', 'UGX', 'TZS', 'ZMW', 'USD', 'XAF', 'XOF'],
        'manual' => [],
    ];

    /**
     * Settings written by another field on this page (never shown on their own).
     *
     * @var list<string>
     */
    public const DERIVED = ['payment.active_payment_currency'];

    /** @var list<string> */
    public const SENSITIVE_PARTS = ['secret', 'credential', 'token', 'private_key', 'password'];

    /**
     * @return array<string, array{label: string, icon: string, description: string, settings: array<string, array<string, mixed>>}>
     */
    public static function groups(): array
    {
        return [
            'general' => [
                'label' => 'Brand & about page',
                'icon' => '✎',
                'description' => 'Public copy, contact details and footer text shown on the website and inside the app.',
                'settings' => [
                    'about.brand_name' => ['label' => 'Brand name', 'section' => 'Brand', 'type' => 'text', 'max' => 60, 'help' => 'Used in page titles and receipts.'],
                    'about.version' => ['label' => 'Displayed version', 'section' => 'Brand', 'type' => 'text', 'max' => 20],

                    'about.hero_tagline' => ['label' => 'Hero tagline', 'section' => 'Hero', 'type' => 'text', 'max' => 160],
                    'about.hero_image_url' => ['label' => 'Hero image URL', 'section' => 'Hero', 'type' => 'url', 'help' => 'Leave empty to use the bundled image.'],

                    'about.mission_title' => ['label' => 'Mission heading', 'section' => 'Story', 'type' => 'text', 'max' => 120],
                    'about.mission_body' => ['label' => 'Mission text', 'section' => 'Story', 'type' => 'textarea', 'rows' => 4],
                    'about.what_we_do_title' => ['label' => '“What we do” heading', 'section' => 'Story', 'type' => 'text', 'max' => 120],
                    'about.what_we_do_body' => ['label' => '“What we do” intro', 'section' => 'Story', 'type' => 'textarea', 'rows' => 3],
                    'about.what_we_do_bullets' => ['label' => '“What we do” points', 'section' => 'Story', 'type' => 'lines', 'help' => 'One point per line.'],

                    'about.stats_members_label' => ['label' => 'Members counter label', 'section' => 'Counters', 'type' => 'text', 'max' => 60],
                    'about.stats_approved_label' => ['label' => 'Approved tasks label', 'section' => 'Counters', 'type' => 'text', 'max' => 60],
                    'about.stats_rewards_label' => ['label' => 'Rewards paid label', 'section' => 'Counters', 'type' => 'text', 'max' => 60],
                    'about.stats_testimonials_label' => ['label' => 'Testimonials label', 'section' => 'Counters', 'type' => 'text', 'max' => 60],

                    'about.features' => ['label' => 'Feature chips', 'section' => 'Highlights', 'type' => 'lines', 'help' => 'One short feature per line.'],

                    'about.contact_email' => ['label' => 'Contact email', 'section' => 'Contact', 'type' => 'email', 'help' => 'Also the fallback support address in the app.'],
                    'about.contact_phone' => ['label' => 'Contact phone', 'section' => 'Contact', 'type' => 'text', 'max' => 40],
                    'about.contact_address' => ['label' => 'Contact address', 'section' => 'Contact', 'type' => 'text', 'max' => 200],
                    'about.social_links' => ['label' => 'Social links', 'section' => 'Contact', 'type' => 'key_value', 'key_label' => 'Network', 'value_label' => 'URL or handle', 'help' => 'Example: Instagram → https://instagram.com/tlstore'],

                    'about.privacy_label' => ['label' => 'Privacy link label', 'section' => 'Footer', 'type' => 'text', 'max' => 60],
                    'about.terms_label' => ['label' => 'Terms link label', 'section' => 'Footer', 'type' => 'text', 'max' => 60],
                    'about.rate_app_label' => ['label' => '“Rate us” label', 'section' => 'Footer', 'type' => 'text', 'max' => 60],
                    'about.store_url' => ['label' => 'Store URL', 'section' => 'Footer', 'type' => 'url'],
                    'about.footer_copyright' => ['label' => 'Copyright line', 'section' => 'Footer', 'type' => 'text', 'max' => 160],
                    'about.footer_note' => ['label' => 'Footer note', 'section' => 'Footer', 'type' => 'text', 'max' => 160],
                ],
            ],

            'payments' => [
                'label' => 'Payments',
                'icon' => '₦',
                'description' => 'Which gateways and currencies customers may pay with, and who verifies payout account names.',
                'settings' => [
                    'payment.active_gateways' => ['label' => 'Gateways & currencies', 'section' => 'Payment gateways', 'type' => 'gateways', 'link_tab' => 'environment', 'help' => 'Tick every currency each gateway should accept. A gateway only appears to customers once its API keys are saved.'],
                    'account_validation.provider' => ['label' => 'Payout account validation', 'section' => 'Account validation', 'type' => 'select_wrapped', 'wrap' => 'provider', 'required' => true, 'options' => [
                        'paystack' => 'Paystack',
                        'flutterwave' => 'Flutterwave',
                        'korapay' => 'Korapay',
                        'bachs' => 'Bachs',
                        'fincra' => 'Fincra',
                    ], 'help' => 'Provider used to confirm the account name on withdrawal methods. It must have keys configured on the server.'],
                ],
            ],

            'rewards' => [
                'label' => 'Rewards & wallets',
                'icon' => '★',
                'description' => 'Turn reward programmes on or off and choose which wallets may be withdrawn from.',
                'settings' => [
                    'rewards.referral_subscription_active' => ['label' => 'Referral reward on subscription', 'section' => 'Reward programmes', 'type' => 'toggle', 'help' => 'Pay the referrer when an invited member buys a package.'],
                    'rewards.referral_task_active' => ['label' => 'Referral reward on first task', 'section' => 'Reward programmes', 'type' => 'toggle', 'help' => 'Pay the referrer when an invited member completes a task.'],
                    'rewards.subscription_bonus_active' => ['label' => 'Subscription bonus', 'section' => 'Reward programmes', 'type' => 'toggle', 'help' => 'Credit the bonus wallet after a package purchase.'],
                    'rewards.social_follow_active' => ['label' => 'Social follow rewards', 'section' => 'Reward programmes', 'type' => 'toggle', 'help' => 'Allow members to earn for following the brand accounts.'],
                    'rewards.user_ranking_active' => ['label' => 'User ranking', 'section' => 'Reward programmes', 'type' => 'toggle', 'help' => 'Show the leaderboard and rank rewards.'],

                    'withdrawal.wallet.referral' => ['label' => 'Referral wallet withdrawals', 'section' => 'Wallet withdrawals', 'type' => 'toggle_map', 'options' => ['active' => 'Allow withdrawal requests from the referral wallet']],
                    'withdrawal.wallet.bonus' => ['label' => 'Bonus wallet withdrawals', 'section' => 'Wallet withdrawals', 'type' => 'toggle_map', 'options' => ['active' => 'Allow withdrawal requests from the bonus wallet']],
                ],
            ],

            'notifications' => [
                'label' => 'Notifications',
                'icon' => '✉',
                'description' => 'Delivery switches and sender identity for member emails and push messages.',
                'link' => ['label' => 'Edit email & push templates', 'route' => 'admin.notifications.index'],
                'settings' => [
                    'notifications.email.enabled' => ['label' => 'Email notifications', 'section' => 'Delivery', 'type' => 'toggle', 'help' => 'Master switch for every transactional email.'],
                    'notifications.push.enabled' => ['label' => 'Push notifications', 'section' => 'Delivery', 'type' => 'toggle', 'help' => 'Requires the Firebase credentials on the server.'],
                    'notifications.sms.enabled' => ['label' => 'SMS notifications', 'section' => 'Delivery', 'type' => 'toggle', 'disabled' => true, 'help' => 'Reserved for a future provider — switches are stored but no SMS is sent.'],

                    'notifications.email.mailer' => ['label' => 'Mail transport', 'section' => 'Sender identity', 'type' => 'select', 'required' => true, 'options' => [
                        'smtp' => 'SMTP',
                        'resend' => 'Resend',
                        'mailgun' => 'Mailgun',
                        'mailjet' => 'Mailjet',
                    ], 'help' => 'Provider credentials stay in the server environment.'],
                    'notifications.email.from_address' => ['label' => 'From address', 'section' => 'Sender identity', 'type' => 'email'],
                    'notifications.email.from_name' => ['label' => 'From name', 'section' => 'Sender identity', 'type' => 'text', 'max' => 120],
                ],
            ],

            'automation' => [
                'label' => 'Automation',
                'icon' => '⟳',
                'description' => 'Background approvals that run on the scheduler.',
                'settings' => [
                    'app.auto_approve_ads.delay_minutes' => ['label' => 'Auto-approve ad shares after', 'section' => 'Auto-approval', 'type' => 'integer', 'min' => 0, 'max' => 10080, 'suffix' => 'minutes', 'help' => '0 disables automatic approval of shared-ad evidence.'],
                    'app.auto_approve_reviews.delay_minutes' => ['label' => 'Auto-approve app reviews after', 'section' => 'Auto-approval', 'type' => 'integer', 'min' => 0, 'max' => 10080, 'suffix' => 'minutes', 'help' => '0 disables automatic approval of review submissions.'],
                ],
            ],

            'vas' => [
                'label' => 'VAS',
                'icon' => '▤',
                'description' => 'Value-added services (airtime, data, TV, electricity, education).',
                'settings' => [
                    'vas.enabled' => ['label' => 'VAS store', 'section' => 'Availability', 'type' => 'toggle', 'help' => 'Show the VAS section in the app.'],
                    'vas.services' => ['label' => 'Enabled services', 'section' => 'Availability', 'type' => 'toggle_map', 'options' => [
                        'airtime' => 'Airtime',
                        'data' => 'Data bundles',
                        'tv' => 'TV subscriptions',
                        'electricity' => 'Electricity',
                        'education' => 'Education pins',
                    ]],
                    'vas.catalog_cache_minutes' => ['label' => 'Catalog cache', 'section' => 'Catalog', 'type' => 'integer', 'min' => 0, 'max' => 1440, 'suffix' => 'minutes', 'help' => 'How long provider price lists are cached.'],
                ],
            ],

            'campaigns' => [
                'label' => 'Campaigns',
                'icon' => '◈',
                'description' => 'Pricing for user-run promotion campaigns.',
                'settings' => [
                    'campaign.services' => ['label' => 'Service pricing', 'section' => 'Pricing', 'type' => 'campaign_services', 'help' => 'Price per unit for each platform action, in the campaign currency. A row with the action “min” is the minimum order quantity.'],
                    'campaign.service_fee_percent' => ['label' => 'Service fee', 'section' => 'Pricing', 'type' => 'decimal', 'min' => 0, 'max' => 100, 'required' => true, 'step' => '0.01', 'suffix' => '%'],
                    'campaign.currency' => ['label' => 'Campaign currency', 'section' => 'Pricing', 'type' => 'text', 'max' => 3, 'uppercase' => true, 'required' => true, 'help' => 'Three-letter currency code, e.g. NGN.'],
                ],
            ],

            'advanced' => [
                'label' => 'Advanced',
                'icon' => '⚙',
                'description' => 'Settings that do not have a dedicated editor yet. Values must be valid JSON.',
                'settings' => [],
            ],
        ];
    }

    /** @return array<string, array<string, mixed>> */
    public static function settings(): array
    {
        $settings = [];
        foreach (self::groups() as $groupKey => $group) {
            foreach ($group['settings'] as $key => $definition) {
                $settings[$key] = $definition + ['group' => $groupKey];
            }
        }

        return $settings;
    }

    /** @return array<string, mixed>|null */
    public static function setting(string $key): ?array
    {
        foreach (self::groups() as $group) {
            if (isset($group['settings'][$key])) {
                return $group['settings'][$key];
            }
        }

        return null;
    }

    public static function isSensitive(string $key): bool
    {
        $normalised = strtolower($key);
        foreach (self::SENSITIVE_PARTS as $part) {
            if (str_contains($normalised, $part)) {
                return true;
            }
        }

        return str_starts_with($normalised, 'telegram.');
    }

    /** Keys another screen or field owns, so they must not appear on "Advanced". */
    public static function isManagedElsewhere(string $key): bool
    {
        return in_array($key, self::DERIVED, true)
            || str_starts_with($key, 'telegram.')
            || self::setting($key) !== null;
    }
}
