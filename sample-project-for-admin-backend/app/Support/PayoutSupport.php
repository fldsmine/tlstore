<?php

namespace App\Support;

/**
 * Support hand-off links.
 *
 * Revenue Authentication codes are normally issued automatically the moment a card payment verifies,
 * but a human is still needed for the exceptions (a charge flagged for manual review, a payment the
 * user says is missing). Those cases point at a dedicated Telegram desk, never the general channel —
 * a revenue query answered in the main chat is exactly what this desk exists to stop.
 *
 * The link is an ADMIN SETTING (backoffice → Settings → General), not a constant: the desk can be
 * renamed or moved without a deploy. Only the fallback for a fresh install lives here.
 */
class PayoutSupport
{
    /** Used when the setting is blank or does not resolve to a real http(s) link. */
    public const DEFAULT_TELEGRAM_REVENUE = 'https://t.me/nextelSpecialSupport';

    /**
     * The revenue-code / payout support desk, ready to drop into an href.
     *
     * A value that is not an http(s) URL can never reach a page (an admin typo, or a non-URL scheme
     * someone stored) — it falls back to the built-in desk instead.
     */
    public static function telegramRevenue(): string
    {
        $link = self::normalizeTelegramLink((string) config('settings.contact.telegram_revenue'));

        return preg_match('#^https?://#i', $link) === 1 ? $link : self::DEFAULT_TELEGRAM_REVENUE;
    }

    /**
     * Accepts what an admin actually types: a full URL, or a bare handle (`@nextelSpecialSupport`) —
     * the latter is expanded to a t.me link so the settings form can be forgiving without the page
     * ever rendering a broken href.
     */
    public static function normalizeTelegramLink(string $value): string
    {
        $value = trim($value);

        if ($value === '') {
            return '';
        }

        // Anything already carrying a scheme is left exactly as typed: an http(s) link is fine, and a
        // typo like `javascript:` must NOT be papered over into a valid-looking t.me link. The http(s)
        // check in telegramRevenue() is what decides its fate.
        if (preg_match('#^[a-z][a-z0-9+.\-]*:#i', $value) === 1) {
            return $value;
        }

        // A pasted host or path (`t.me/desk`, `www.example.com/x`) is a URL that lost its scheme.
        if (str_contains($value, '/') || str_contains($value, '.')) {
            return 'https://'.$value;
        }

        // Otherwise it is a bare handle.
        return 'https://t.me/'.ltrim($value, '@');
    }
}
