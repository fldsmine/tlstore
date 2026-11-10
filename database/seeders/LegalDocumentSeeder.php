<?php

namespace Database\Seeders;

use App\Models\LegalDocument;
use Illuminate\Database\Seeder;

class LegalDocumentSeeder extends Seeder
{
    public function run(): void
    {
        $documents = [
            [
                'slug' => 'privacy',
                'title' => 'Privacy, in plain language',
                'eyebrow' => 'Privacy & trust',
                'subtitle' => 'A clear view of the information TrustLens uses to run your account and tasks.',
                'hero_text' => 'Your information helps us match you with eligible campaigns, review submissions, and pay approved rewards.',
                'version' => '1.0',
                'sections' => [
                    [
                        'heading' => 'Information you provide',
                        'body' => 'When you create and use an account, TrustLens processes the details you submit, such as your name, username, email address, phone number, country, profile images, and sign-in provider details.',
                    ],
                    [
                        'heading' => 'Task and campaign information',
                        'body' => 'We store your campaign activity, answers, public post links or evidence, moderation decisions, and related messages so that tasks can be reviewed and rewards can be managed.',
                    ],
                    [
                        'heading' => 'Payments and earnings',
                        'body' => 'Wallet balances, top-ups, transfers, subscriptions, withdrawal requests, and payment references are processed to maintain account balances and support payment review and reconciliation.',
                    ],
                    [
                        'heading' => 'How information is used',
                        'items' => [
                            'Create and secure your account, and respond to support requests.',
                            'Determine campaign eligibility from your registration country and active subscription package.',
                            'Review task evidence and manage pending or approved rewards.',
                            'Process selected payment, notification, and media features you use.',
                            'Protect the service, investigate misuse, and maintain financial records.',
                        ],
                    ],
                    [
                        'heading' => 'Information shared to run the service',
                        'body' => 'Campaign owners and authorized moderators may see task answers and evidence needed to assess a submission. Payment providers, notification providers, and infrastructure providers may process information needed to deliver the features you request. Public ad posts are published on the social platform you choose and may be visible to anyone under that platform’s settings.',
                    ],
                    [
                        'heading' => 'Your choices and account controls',
                        'items' => [
                            'You can update supported profile details and manage notification preferences in the app.',
                            'Your registration country is fixed after signup and is used for campaign eligibility.',
                            'You can request account deletion from settings. The account is soft-deleted first and is scheduled for permanent deletion after 30 days.',
                            'Some payment or ledger records may be retained when needed for financial reconciliation or legal obligations.',
                        ],
                    ],
                    [
                        'heading' => 'Security and contact',
                        'body' => 'We use account controls and service safeguards to protect stored information. No online service can guarantee absolute security. For privacy questions or help with your account, use the Contact Support option in the app.',
                    ],
                ],
            ],
            [
                'slug' => 'terms',
                'title' => 'Clear rules. Honest participation.',
                'eyebrow' => 'Terms of participation',
                'subtitle' => 'The essentials for using TrustLens campaigns, subscriptions, wallets, and rewards.',
                'hero_text' => 'Use the service responsibly, follow each campaign’s instructions, and submit work that reflects your genuine experience.',
                'version' => '1.0',
                'sections' => [
                    [
                        'heading' => 'Your account',
                        'body' => 'Provide accurate registration details, keep your sign-in credentials private, and use the account belonging to you. Your registration country is used to determine initial campaign eligibility and cannot currently be changed in the app.',
                    ],
                    [
                        'heading' => 'Campaign participation',
                        'items' => [
                            'Read the listing and complete the requested steps before submitting a task.',
                            'App feedback must be honest and based on your own experience. A campaign reward is not a request for a positive rating or review.',
                            'Follow the rules of the app store or social platform where you participate. Do not post incentivized store reviews where that store’s rules prohibit them.',
                            'Ad-sharing tasks require a public post and evidence that can be manually checked. Do not submit another person’s post or misleading evidence.',
                            'Do not automate, duplicate, or misrepresent task activity.',
                        ],
                    ],
                    [
                        'heading' => 'Review and rewards',
                        'body' => 'Task submissions remain pending until human review. Rewards are credited after approval according to the campaign’s stated amount and currency. A submission that does not follow the instructions or cannot be verified may be rejected.',
                    ],
                    [
                        'heading' => 'Subscriptions and task eligibility',
                        'body' => 'Marketplace packages define which tasks you may perform, any app-store or category restrictions, task limits, and withdrawal terms. Listings targeted to a country or region are available only to users whose profile country matches. Package availability, price, and duration are shown before purchase.',
                    ],
                    [
                        'heading' => 'Wallets and withdrawals',
                        'body' => 'Top-up funds may be used to purchase subscriptions. Top-up funds cannot be withdrawn directly, but may be transferred without a transfer fee to an eligible earned wallet. Withdrawal methods, package schedules, minimum amounts, and percentage fees are displayed in the app before a request is submitted.',
                    ],
                    [
                        'heading' => 'Fair use and moderation',
                        'body' => 'TrustLens may review activity and restrict or suspend accounts or submissions that appear fraudulent, unsafe, abusive, or inconsistent with these terms or campaign requirements. Contact Support in the app if you need help understanding an account or submission decision.',
                    ],
                    [
                        'heading' => 'Changes to these terms',
                        'body' => 'The current version and update date appear at the top of this page. Continued use after updated terms are published means you agree to use the service under the updated terms.',
                    ],
                ],
            ],
        ];

        foreach ($documents as $document) {
            LegalDocument::query()->updateOrCreate(
                ['slug' => $document['slug']],
                array_merge($document, [
                    'last_updated' => '2026-11-02',
                    'is_published' => true,
                ]),
            );
        }
    }
}
