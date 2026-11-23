<?php

namespace App\Support;

/**
 * The demo/marketing copy of the product storefront: hero, feature grid, benefits, FAQ and the
 * closing call to action.
 *
 * It lives in one class so the storefront's content is a single editable file rather than strings
 * scattered through a Blade template — and so the same copy can be reused if the page is ever
 * rendered somewhere else. Nothing here is a price or a product: those always come from the catalog.
 */
class ProductPageContent
{
    /**
     * @return array{eyebrow: string, heading: string, body: string, primary_cta: string, secondary_cta: string}
     */
    public static function hero(): array
    {
        return [
            'eyebrow' => 'Nextel Store',
            'heading' => 'Take your <span class="text-primary">Nextel</span> experience to the next level.',
            'body' => 'Buy an eSIM and start earning from sponsored calls and messages — or pick up the devices and gear that make the network easier to live with. Guest checkout: pay with your card and we send everything to your email.',
            'primary_cta' => 'Shop the store',
            'secondary_cta' => 'Get the app',
        ];
    }

    /**
     * @return array{eyebrow: string, heading: string, heading_muted: string, body: string, items: list<array{icon: string, title: string, body: string}>}
     */
    public static function features(): array
    {
        return [
            'eyebrow' => 'Why Nextel',
            'heading' => 'Discover what makes ',
            'heading_muted' => 'Nextel work for you.',
            'body' => 'Everything is bought once, delivered by email, and used for as long as you keep earning. No contracts, no monthly surprises, no account needed to buy.',
            'items' => [
                [
                    'icon' => 'ri-shield-check-line',
                    'title' => 'Pay securely, in naira',
                    'body' => 'Checkout is handled by our payment partner on their own secure page. We never see or store your card details.',
                ],
                [
                    'icon' => 'ri-mail-send-line',
                    'title' => 'Delivered to your email',
                    'body' => 'Your eSIM or product is sent to the address you give us — usually within a few hours, and always with a reference you can quote.',
                ],
                [
                    'icon' => 'ri-user-unfollow-line',
                    'title' => 'Buy as a guest',
                    'body' => 'No sign-up, no onboarding, no PIN to remember before you can pay. Three details and you are done.',
                ],
                [
                    'icon' => 'ri-exchange-dollar-line',
                    'title' => 'Honest, live pricing',
                    'body' => 'Prices track the packages and the exchange rate in the backoffice, so the amount on the card is exactly what the charge asks for.',
                ],
                [
                    'icon' => 'ri-refund-2-line',
                    'title' => 'A real human on support',
                    'body' => 'If anything is wrong with your order, reply to your receipt or email support with the reference and we sort it out.',
                ],
                [
                    'icon' => 'ri-smartphone-line',
                    'title' => 'Built for the Nextel app',
                    'body' => 'Activate the eSIM in the app, track your earnings, and manage the devices you bought from this store.',
                ],
            ],
        ];
    }

    /**
     * @return array{eyebrow: string, heading: string, body: string}
     */
    public static function productsHeading(): array
    {
        return [
            'eyebrow' => 'Store',
            'heading' => 'Everything you need to earn on Nextel',
            'body' => 'Start with an eSIM plan — that is your membership and your earning pass. Then add whatever else you need from the catalog below it.',
        ];
    }

    public static function plansHeading(): string
    {
        return 'eSIM plans';
    }

    public static function extrasHeading(): string
    {
        return 'More from the catalog';
    }

    /**
     * @return array{eyebrow: string, heading: string, body: string, note: string, items: list<array{icon: string, title: string, body: string}>}
     */
    public static function benefits(): array
    {
        return [
            'eyebrow' => 'Buying from us',
            'heading' => 'Simple, honest and fast',
            'body' => 'Four things we promise on every order from this store — whether you are buying an eSIM plan or a router for the office.',
            'note' => 'Guest checkout',
            'items' => [
                [
                    'icon' => 'ri-lock-2-line',
                    'title' => 'Secure payment',
                    'body' => 'Card payments are processed by our licensed payment partner. We store only the reference.',
                ],
                [
                    'icon' => 'ri-truck-line',
                    'title' => 'Email delivery',
                    'body' => 'Digital items arrive by email, usually within a few hours of payment confirming.',
                ],
                [
                    'icon' => 'ri-customer-service-2-line',
                    'title' => 'Support that answers',
                    'body' => 'Quote your order reference and a human picks it up — no ticket maze.',
                ],
                [
                    'icon' => 'ri-file-shield-2-line',
                    'title' => 'Data handled properly',
                    'body' => 'We keep only your name, phone and email for the order, and nothing else about you.',
                ],
            ],
        ];
    }

    /**
     * @return list<array{q: string, a: string}>
     */
    public static function faqs(): array
    {
        return [
            [
                'q' => 'Do I need an account to buy?',
                'a' => 'No. Enter your full name, phone number and email, pay with your card, and the product is sent to that email. You can create an account afterwards if you want to earn with the eSIM.',
            ],
            [
                'q' => 'What exactly am I paying for?',
                'a' => 'The card shows the product, its price and the total before you pay. Nothing is added afterwards, and the amount is locked the moment you start the checkout.',
            ],
            [
                'q' => 'How and when do I receive it?',
                'a' => 'By email, to the address you entered. Digital items are usually sent within a few hours of the payment confirming; if the payment is still being confirmed, the receipt page tells you so instead of pretending it failed.',
            ],
            [
                'q' => 'What if the payment does not go through?',
                'a' => 'You are never charged for a checkout that did not complete. Start again from the store whenever you are ready — and if your bank shows a debit without an order, email support with the reference on your receipt.',
            ],
            [
                'q' => 'Which payment methods work?',
                'a' => 'Card payments through our payment partner, in naira. The secure payment page shows the methods available for your card and country.',
            ],
            [
                'q' => 'Are the eSIM plans monthly?',
                'a' => 'No. You buy a plan once. Each plan differs in how many call minutes, call IDs and cloud features it includes — its card lists exactly what you get.',
            ],
        ];
    }

    /**
     * @return array{eyebrow: string, heading: string, body: string, primary_cta: string}
     */
    public static function final(): array
    {
        return [
            'eyebrow' => 'Nextel app',
            'heading' => 'Nextel app now available on iOS and Android',
            'body' => 'Activate your eSIM, track your earnings, and manage your orders from the official Nextel app.',
            'primary_cta' => 'Download the app',
        ];
    }
}
