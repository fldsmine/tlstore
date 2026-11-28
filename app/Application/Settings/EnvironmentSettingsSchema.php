<?php

namespace App\Application\Settings;

/**
 * Credentials and switches that a super admin can maintain from the console.
 *
 * Only values a non-developer can obtain — provider API keys, portal IDs,
 * hostnames, endpoints and a few feature switches — are listed here. Anything
 * that needs filesystem access, shell access or a deployment pipeline (service
 * account file paths, APP_KEY, database and storage credentials, binaries) is
 * deliberately excluded and documented in {@see self::serverManaged()}.
 *
 * Key types:
 *   secret  – write-only: stored values are never rendered back to the browser
 *   text    – short free text
 *   url     – http(s) URL
 *   email   – email address
 *   integer – whole number between min and max
 *   boolean – true / false
 *   select  – one of `options`
 *   list    – comma separated values (e.g. certificate fingerprints)
 */
final class EnvironmentSettingsSchema
{
    /**
     * @return array<string, array{label: string, description: string, providers: array<string, array<string, mixed>>}>
     */
    public static function sections(): array
    {
        return [
            'gateways' => [
                'label' => 'Payment gateways',
                'description' => 'Keys from each provider dashboard. A gateway is only offered at checkout when the keys listed as required are present (the Payments tab reads the same configuration).',
                'providers' => [
                    'korapay' => [
                        'label' => 'Korapay',
                        'docs' => 'https://docs.korapay.com',
                        'required' => ['KORAPAY_SECRET_KEY'],
                        'keys' => [
                            'KORAPAY_SECRET_KEY' => ['label' => 'Secret key', 'type' => 'secret', 'help' => 'Korapay dashboard → Settings → API keys.'],
                            'KORAPAY_PUBLIC_KEY' => ['label' => 'Public key', 'type' => 'text', 'help' => 'Used by the hosted checkout.'],
                            'KORAPAY_BASE_URL' => ['label' => 'API base URL', 'type' => 'url', 'default' => 'https://api.korapay.com/merchant/api/v1'],
                        ],
                    ],
                    'paystack' => [
                        'label' => 'Paystack',
                        'docs' => 'https://paystack.com/docs',
                        'required' => ['PAYSTACK_SECRET_KEY'],
                        'keys' => [
                            'PAYSTACK_SECRET_KEY' => ['label' => 'Secret key', 'type' => 'secret', 'help' => 'Dashboard → Settings → API Keys & Webhooks (starts with sk_).'],
                            'PAYSTACK_BASE_URL' => ['label' => 'API base URL', 'type' => 'url', 'default' => 'https://api.paystack.co'],
                        ],
                    ],
                    'flutterwave' => [
                        'label' => 'Flutterwave',
                        'docs' => 'https://developer.flutterwave.com/docs',
                        'required' => ['FLUTTERWAVE_SECRET_KEY', 'FLUTTERWAVE_WEBHOOK_SECRET'],
                        'keys' => [
                            'FLUTTERWAVE_SECRET_KEY' => ['label' => 'Secret key', 'type' => 'secret', 'help' => 'Dashboard → Settings → API keys.'],
                            'FLUTTERWAVE_WEBHOOK_SECRET' => ['label' => 'Webhook secret hash', 'type' => 'secret', 'help' => 'Set the same hash on the webhook URL in the dashboard.'],
                            'FLUTTERWAVE_BASE_URL' => ['label' => 'API base URL', 'type' => 'url', 'default' => 'https://api.flutterwave.com/v3'],
                        ],
                    ],
                    'bachs' => [
                        'label' => 'Bachs',
                        'docs' => 'https://docs.bachs.io',
                        'required' => ['BACHS_API_KEY', 'BACHS_WEBHOOK_SECRET'],
                        'keys' => [
                            'BACHS_API_KEY' => ['label' => 'API key', 'type' => 'secret', 'help' => 'Bachs dashboard → Developers.'],
                            'BACHS_WEBHOOK_SECRET' => ['label' => 'Webhook signing secret', 'type' => 'secret'],
                            'BACHS_BASE_URL' => ['label' => 'API base URL', 'type' => 'url', 'default' => 'https://api.bachs.io/v1'],
                        ],
                    ],
                    'fincra' => [
                        'label' => 'Fincra',
                        'docs' => 'https://docs.fincra.com',
                        'required' => ['FINCRA_SECRET_KEY', 'FINCRA_WEBHOOK_SECRET'],
                        'keys' => [
                            'FINCRA_SECRET_KEY' => ['label' => 'Secret key', 'type' => 'secret', 'help' => 'Fincra dashboard → API keys.'],
                            'FINCRA_PUBLIC_KEY' => ['label' => 'Public key', 'type' => 'text'],
                            'FINCRA_BUSINESS_ID' => ['label' => 'Business ID', 'type' => 'text', 'help' => 'Required for the wallet balance card; not needed to accept payments.'],
                            'FINCRA_WEBHOOK_SECRET' => ['label' => 'Webhook secret', 'type' => 'secret', 'help' => 'Signs the raw webhook body with HMAC-SHA512.'],
                            'FINCRA_BASE_URL' => ['label' => 'API base URL', 'type' => 'url', 'default' => 'https://api.fincra.com'],
                            'FINCRA_FEE_BEARER' => ['label' => 'Who pays the fee', 'type' => 'select', 'options' => ['business' => 'Business', 'customer' => 'Customer'], 'default' => 'business'],
                        ],
                    ],
                ],
            ],

            'vas' => [
                'label' => 'Value-added services (VTpass)',
                'description' => 'Airtime, data, TV, electricity and education pins are delivered through VTpass.',
                'providers' => [
                    'vtpass' => [
                        'label' => 'VTpass',
                        'docs' => 'https://vtpass.com/documentation',
                        'required' => ['VTPASS_API_KEY', 'VTPASS_SECRET_KEY'],
                        'keys' => [
                            'VTPASS_BASE_URL' => ['label' => 'API base URL', 'type' => 'url', 'default' => 'https://sandbox.vtpass.com', 'help' => 'Use https://vtpass.com for live traffic.'],
                            'VTPASS_API_KEY' => ['label' => 'API key', 'type' => 'secret'],
                            'VTPASS_PUBLIC_KEY' => ['label' => 'Public key', 'type' => 'text'],
                            'VTPASS_SECRET_KEY' => ['label' => 'Secret key', 'type' => 'secret'],
                            'VTPASS_WEBHOOK_SECRET' => ['label' => 'Webhook secret', 'type' => 'secret'],
                            'VTPASS_TIMEOUT' => ['label' => 'Request timeout', 'type' => 'integer', 'min' => 5, 'max' => 120, 'default' => '20', 'suffix' => 'seconds'],
                        ],
                    ],
                ],
            ],

            'mail' => [
                'label' => 'Mail transport',
                'description' => 'Server credentials for the mail relays. The active transport and the sender identity are chosen on the Email & notification templates page.',
                'providers' => [
                    'smtp' => [
                        'label' => 'SMTP',
                        'required' => ['MAIL_HOST', 'MAIL_PASSWORD'],
                        'keys' => [
                            'MAIL_HOST' => ['label' => 'Host', 'type' => 'text', 'default' => '127.0.0.1'],
                            'MAIL_PORT' => ['label' => 'Port', 'type' => 'integer', 'min' => 1, 'max' => 65535, 'default' => '2525'],
                            'MAIL_SCHEME' => ['label' => 'Encryption', 'type' => 'select', 'options' => ['' => 'Automatic', 'smtp' => 'STARTTLS', 'smtps' => 'TLS / SSL'], 'default' => ''],
                            'MAIL_USERNAME' => ['label' => 'Username', 'type' => 'text'],
                            'MAIL_PASSWORD' => ['label' => 'Password', 'type' => 'secret'],
                        ],
                    ],
                    'resend' => [
                        'label' => 'Resend',
                        'docs' => 'https://resend.com/api-keys',
                        'required' => ['RESEND_API_KEY'],
                        'keys' => [
                            'RESEND_API_KEY' => ['label' => 'API key', 'type' => 'secret', 'help' => 'Used as the SMTP password (re_…).'],
                        ],
                    ],
                    'mailgun' => [
                        'label' => 'Mailgun',
                        'docs' => 'https://app.mailgun.com/settings/api_security',
                        'required' => ['MAILGUN_SMTP_USERNAME', 'MAILGUN_SMTP_PASSWORD'],
                        'keys' => [
                            'MAILGUN_SMTP_USERNAME' => ['label' => 'SMTP username', 'type' => 'text', 'help' => 'Usually postmaster@your-domain.'],
                            'MAILGUN_SMTP_PASSWORD' => ['label' => 'SMTP password', 'type' => 'secret'],
                        ],
                    ],
                    'mailjet' => [
                        'label' => 'Mailjet',
                        'docs' => 'https://app.mailjet.com/account/apikeys',
                        'required' => ['MAILJET_API_KEY', 'MAILJET_SECRET_KEY'],
                        'keys' => [
                            'MAILJET_API_KEY' => ['label' => 'API key', 'type' => 'text', 'help' => 'Mailjet uses the API key as the SMTP username.'],
                            'MAILJET_SECRET_KEY' => ['label' => 'Secret key', 'type' => 'secret'],
                        ],
                    ],
                ],
            ],

            'push' => [
                'label' => 'Push notifications',
                'description' => 'Firebase Cloud Messaging. The service-account JSON file itself is uploaded on the server; only the project and the switch are handled here.',
                'providers' => [
                    'fcm' => [
                        'label' => 'Firebase Cloud Messaging',
                        'docs' => 'https://console.firebase.google.com',
                        'required' => ['FCM_PROJECT_ID'],
                        'keys' => [
                            'FCM_ENABLED' => ['label' => 'Push delivery', 'type' => 'boolean', 'default' => 'true'],
                            'FCM_PROJECT_ID' => ['label' => 'Project ID', 'type' => 'text', 'help' => 'Firebase console → Project settings → General.'],
                        ],
                    ],
                ],
            ],

            'ai' => [
                'label' => 'AI listing generator',
                'description' => 'Keys, endpoints and model names for the agents that draft app listings. Any agent without a key is skipped, and the generator falls through to the next one.',
                'providers' => [
                    'openai' => [
                        'label' => 'OpenAI',
                        'docs' => 'https://platform.openai.com/api-keys',
                        'keys' => [
                            'OPENAI_API_KEY' => ['label' => 'API key', 'type' => 'secret'],
                            'OPENAI_BASE_URL' => ['label' => 'Base URL', 'type' => 'url', 'default' => 'https://api.openai.com/v1'],
                            'OPENAI_MODEL' => ['label' => 'Model', 'type' => 'text', 'default' => 'gpt-4o-mini'],
                        ],
                    ],
                    'anthropic' => [
                        'label' => 'Anthropic',
                        'docs' => 'https://console.anthropic.com/settings/keys',
                        'keys' => [
                            'ANTHROPIC_API_KEY' => ['label' => 'API key', 'type' => 'secret'],
                            'ANTHROPIC_BASE_URL' => ['label' => 'Base URL', 'type' => 'url', 'default' => 'https://api.anthropic.com'],
                            'ANTHROPIC_MODEL' => ['label' => 'Model', 'type' => 'text', 'default' => 'claude-3-5-sonnet-latest'],
                        ],
                    ],
                    'gemini' => [
                        'label' => 'Google Gemini',
                        'docs' => 'https://aistudio.google.com/app/apikey',
                        'keys' => [
                            'GEMINI_API_KEY' => ['label' => 'API key', 'type' => 'secret'],
                            'GEMINI_BASE_URL' => ['label' => 'Base URL', 'type' => 'url', 'default' => 'https://generativelanguage.googleapis.com'],
                            'GEMINI_MODEL' => ['label' => 'Model', 'type' => 'text', 'default' => 'gemini-2.0-flash'],
                        ],
                    ],
                    'groq' => [
                        'label' => 'Groq',
                        'docs' => 'https://console.groq.com/keys',
                        'keys' => [
                            'GROQ_API_KEY' => ['label' => 'API key', 'type' => 'secret'],
                            'GROQ_BASE_URL' => ['label' => 'Base URL', 'type' => 'url', 'default' => 'https://api.groq.com/openai/v1'],
                            'GROQ_MODEL' => ['label' => 'Model', 'type' => 'text', 'default' => 'llama-3.3-70b-versatile'],
                        ],
                    ],
                    'openrouter' => [
                        'label' => 'OpenRouter',
                        'docs' => 'https://openrouter.ai/keys',
                        'keys' => [
                            'OPENROUTER_API_KEY' => ['label' => 'API key', 'type' => 'secret'],
                            'OPENROUTER_BASE_URL' => ['label' => 'Base URL', 'type' => 'url', 'default' => 'https://openrouter.ai/api/v1'],
                            'OPENROUTER_MODEL' => ['label' => 'Model', 'type' => 'text', 'default' => 'openai/gpt-4o-mini'],
                        ],
                    ],
                    'deepseek' => [
                        'label' => 'DeepSeek',
                        'docs' => 'https://platform.deepseek.com/api_keys',
                        'keys' => [
                            'DEEPSEEK_API_KEY' => ['label' => 'API key', 'type' => 'secret'],
                            'DEEPSEEK_BASE_URL' => ['label' => 'Base URL', 'type' => 'url', 'default' => 'https://api.deepseek.com/v1'],
                            'DEEPSEEK_MODEL' => ['label' => 'Model', 'type' => 'text', 'default' => 'deepseek-chat'],
                        ],
                    ],
                    'mistral' => [
                        'label' => 'Mistral',
                        'docs' => 'https://console.mistral.ai/api-keys',
                        'keys' => [
                            'MISTRAL_API_KEY' => ['label' => 'API key', 'type' => 'secret'],
                            'MISTRAL_BASE_URL' => ['label' => 'Base URL', 'type' => 'url', 'default' => 'https://api.mistral.ai/v1'],
                            'MISTRAL_MODEL' => ['label' => 'Model', 'type' => 'text', 'default' => 'mistral-large-latest'],
                        ],
                    ],
                    'listings' => [
                        'label' => 'Generation limits',
                        'keys' => [
                            'AI_LISTINGS_REQUIRE_IMAGES' => ['label' => 'Require artwork', 'type' => 'boolean', 'default' => 'true', 'help' => 'Reject a generated listing when its icon or screenshots cannot be downloaded.'],
                            'AI_LISTINGS_TIMEOUT' => ['label' => 'Agent timeout', 'type' => 'integer', 'min' => 30, 'max' => 600, 'default' => '180', 'suffix' => 'seconds'],
                        ],
                    ],
                ],
            ],

            'social' => [
                'label' => 'Social sign-in',
                'description' => 'OAuth credentials for Google and Facebook sign-in, plus the Telegram bot used for the join reward.',
                'providers' => [
                    'google' => [
                        'label' => 'Google',
                        'docs' => 'https://console.cloud.google.com/apis/credentials',
                        'required' => ['GOOGLE_CLIENT_ID'],
                        'keys' => [
                            'GOOGLE_CLIENT_ID' => ['label' => 'OAuth client ID', 'type' => 'text'],
                        ],
                    ],
                    'facebook' => [
                        'label' => 'Facebook',
                        'docs' => 'https://developers.facebook.com/apps',
                        'required' => ['FACEBOOK_APP_ID', 'FACEBOOK_APP_SECRET'],
                        'keys' => [
                            'FACEBOOK_APP_ID' => ['label' => 'App ID', 'type' => 'text'],
                            'FACEBOOK_APP_SECRET' => ['label' => 'App secret', 'type' => 'secret'],
                            'FACEBOOK_CLIENT_TOKEN' => ['label' => 'Client token', 'type' => 'secret'],
                        ],
                    ],
                    'telegram' => [
                        'label' => 'Telegram bot (social reward)',
                        'docs' => 'https://core.telegram.org/bots#botfather',
                        'required' => ['TELEGRAM_BOT_TOKEN'],
                        'keys' => [
                            'TELEGRAM_BOT_TOKEN' => ['label' => 'Bot token', 'type' => 'secret', 'help' => 'From @BotFather. Announcement/receipt bot credentials are on the Telegram page.'],
                            'TELEGRAM_BOT_USERNAME' => ['label' => 'Bot username', 'type' => 'text', 'help' => 'Without the @, e.g. tlstore_ai_bot.'],
                        ],
                    ],
                ],
            ],

            'platform' => [
                'label' => 'Platform',
                'description' => 'Support address, payment return/webhook origins and the app-link identifiers used by the app.',
                'providers' => [
                    'trustlens' => [
                        'label' => 'Domain & support',
                        'keys' => [
                            'SUPPORT_EMAIL' => ['label' => 'Support email', 'type' => 'email', 'default' => 'support@example.com', 'help' => 'Receives contact-form and support-ticket notifications.'],
                            'PAYMENT_RETURN_URL' => ['label' => 'Payment return URL', 'type' => 'url', 'help' => 'Where customers land after a hosted checkout.'],
                            'PAYMENT_WEBHOOK_BASE_URL' => ['label' => 'Webhook base URL', 'type' => 'url', 'help' => 'Public HTTPS origin providers call back on.'],
                        ],
                    ],
                    'app_links' => [
                        'label' => 'App links',
                        'keys' => [
                            'ANDROID_APP_PACKAGE' => ['label' => 'Android package', 'type' => 'text', 'default' => 'pynith.tlstore.review'],
                            'ANDROID_SHA256_CERT_FINGERPRINTS' => ['label' => 'Android signing fingerprints', 'type' => 'list', 'help' => 'SHA-256 fingerprints separated by commas, e.g. AB:CD:… (32 pairs).'],
                            'IOS_APP_ID' => ['label' => 'iOS app ID', 'type' => 'text', 'help' => 'TEAMID.bundle.id from the Apple developer portal.'],
                        ],
                    ],
                ],
            ],
        ];
    }

    /** @return array<string, array<string, mixed>> every editable key, flattened */
    public static function keys(): array
    {
        $keys = [];

        foreach (self::sections() as $sectionId => $section) {
            foreach ($section['providers'] as $providerId => $provider) {
                foreach ($provider['keys'] as $key => $definition) {
                    $keys[$key] = $definition + [
                        'section' => $sectionId,
                        'provider' => $providerId,
                        'provider_label' => $provider['label'],
                    ];
                }
            }
        }

        return $keys;
    }

    public static function keyCount(): int
    {
        return count(self::keys());
    }

    /**
     * Settings that stay on the server, with the reason shown in the console.
     *
     * @return array<string, string>
     */
    public static function serverManaged(): array
    {
        return [
            'FCM_SERVICE_ACCOUNT_PATH' => 'Path to the Firebase service-account JSON file. Upload the file on the server and point this variable at it.',
            'APP_KEY' => 'Application encryption key. Rotate it with `php artisan key:generate` on the server.',
            'APP_URL' => 'Public origin of this deployment, set with the rest of the environment.',
            'DB_*' => 'Database connection (database, username, password) — part of the deployment environment.',
            'AWS_*' => 'Object storage credentials used by the filesystem and SES — managed by the deployment.',
            'REDIS_* / SESSION_* / CACHE_* / QUEUE_*' => 'Infrastructure drivers — changing them from a browser can take the site offline.',
            'MAIL_SENDMAIL_PATH' => 'Path to the sendmail binary.',
            'TELEGRAM_NOTIFY_BOT_TOKEN / TELEGRAM_NOTIFY_CHAT_ID' => 'Notification bot credentials, stored encrypted on the Telegram messaging page.',
            'TELEGRAM_GHOSTSCRIPT_BINARY' => 'Path to the Ghostscript binary used for receipt rendering.',
            'SYSTEM_ACCOUNT_PASSWORD' => 'Internal system account password, generated by the deployment.',
        ];
    }
}
