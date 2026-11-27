<?php

namespace App\Application\Telegram;

/**
 * Telegram's `parse_mode=HTML` understands a small tag set
 * (https://core.telegram.org/bots/api#html-style): b/strong, i/em, u, s,
 * a[href], code, pre, br. Anything else makes the Bot API reject the whole
 * message with "can't parse entities".
 *
 * Admin input is therefore reduced to that allowlist before it is stored or
 * sent: unknown tags are unwrapped (their text is kept), script/style content
 * is dropped, links are restricted to http(s)/tg schemes and every attribute
 * except `href` is removed.
 */
final class TelegramHtml
{
    /** @var list<string> */
    public const ALLOWED_TAGS = ['b', 'strong', 'i', 'em', 'u', 'ins', 's', 'strike', 'del', 'a', 'code', 'pre', 'br'];

    /** @var list<string> */
    private const ALLOWED_SCHEMES = ['http', 'https', 'tg', 'mailto'];

    /** Tags whose content is discarded with the tag itself. */
    private const DROP_WITH_CONTENT = ['script', 'style', 'iframe', 'object', 'embed', 'template', 'noscript'];

    public static function sanitize(string $html): string
    {
        $html = trim(str_replace(["\r\n", "\r"], "\n", $html));
        $html = (string) preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/u', '', $html);
        if ($html === '') {
            return '';
        }

        // Angle brackets that are not part of a well-formed tag ("Fish < 5")
        // are escaped first: libxml would otherwise swallow the text around
        // them, which silently truncated announcements.
        $html = self::escapeStrayAngles($html);

        $previous = libxml_use_internal_errors(true);
        $document = new \DOMDocument('1.0', 'UTF-8');
        $loaded = $document->loadHTML(
            '<?xml encoding="UTF-8"><!DOCTYPE html><html><body><div id="telegram-root">'.$html.'</div></body></html>',
            LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD | LIBXML_NOERROR | LIBXML_NOWARNING,
        );
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        if (! $loaded) {
            return self::escapeText($html);
        }

        $root = null;
        foreach ($document->getElementsByTagName('div') as $candidate) {
            if ($candidate->getAttribute('id') === 'telegram-root') {
                $root = $candidate;
                break;
            }
        }
        if ($root === null) {
            return self::escapeText($html);
        }

        self::normalize($root);

        return trim(self::serializeChildren($root));
    }

    /**
     * Escape `<`/`>`/`&` in every text segment while leaving well-formed tags
     * (allowed or not — unknown ones are unwrapped later) untouched.
     */
    private static function escapeStrayAngles(string $html): string
    {
        $parts = preg_split('/(<\/?[a-zA-Z][a-zA-Z0-9]*(?:\s[^<>]*)?\/?>)/', $html, -1, PREG_SPLIT_DELIM_CAPTURE);
        if ($parts === false) {
            return self::escapeText($html);
        }

        $output = '';
        foreach ($parts as $index => $part) {
            // Odd indexes are the captured tags themselves.
            $output .= $index % 2 === 1 ? $part : self::escapeText($part);
        }

        return $output;
    }

    /** Visible character count, used against Telegram's 4096/1024 limits. */
    public static function visibleLength(string $html): int
    {
        return mb_strlen(html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
    }

    public static function escapeText(string $value): string
    {
        return str_replace(['&', '<', '>'], ['&amp;', '&lt;', '&gt;'], $value);
    }

    public static function escapeAttribute(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    /** Walk the tree once, repeatedly unwrapping any element that is not allowed. */
    private static function normalize(\DOMNode $node): void
    {
        $restart = true;
        while ($restart) {
            $restart = false;
            foreach (iterator_to_array($node->childNodes) as $child) {
                if (! $child instanceof \DOMElement) {
                    continue;
                }

                $tag = strtolower($child->nodeName);

                if (in_array($tag, self::DROP_WITH_CONTENT, true)) {
                    $node->removeChild($child);
                    $restart = true;
                    break;
                }

                $allowed = in_array($tag, self::ALLOWED_TAGS, true)
                    && ($tag !== 'a' || self::sanitizedHref($child) !== null);

                if (! $allowed) {
                    while ($child->firstChild !== null) {
                        $node->insertBefore($child->firstChild, $child);
                    }
                    $node->removeChild($child);
                    $restart = true;
                    break;
                }

                self::normalize($child);
            }
        }
    }

    private static function sanitizedHref(\DOMElement $link): ?string
    {
        $href = trim($link->getAttribute('href'));
        if ($href === '' || strlen($href) > 2048) {
            return null;
        }

        $scheme = strtolower((string) parse_url($href, PHP_URL_SCHEME));
        if (! in_array($scheme, self::ALLOWED_SCHEMES, true)) {
            return null;
        }
        if (in_array($scheme, ['http', 'https'], true) && ! parse_url($href, PHP_URL_HOST)) {
            return null;
        }

        return $href;
    }

    private static function serializeChildren(\DOMNode $node): string
    {
        $output = '';
        foreach ($node->childNodes as $child) {
            if ($child instanceof \DOMText) {
                $output .= self::escapeText($child->nodeValue ?? '');
                continue;
            }
            if (! $child instanceof \DOMElement) {
                continue;
            }

            $tag = strtolower($child->nodeName);
            $inner = self::serializeChildren($child);
            $output .= match ($tag) {
                'b', 'strong' => '<b>'.$inner.'</b>',
                'i', 'em' => '<i>'.$inner.'</i>',
                'u', 'ins' => '<u>'.$inner.'</u>',
                's', 'strike', 'del' => '<s>'.$inner.'</s>',
                'code' => '<code>'.$inner.'</code>',
                'pre' => '<pre>'.$inner.'</pre>',
                'br' => '<br>',
                'a' => '<a href="'.self::escapeAttribute((string) self::sanitizedHref($child)).'">'.$inner.'</a>',
                default => $inner,
            };
        }

        return $output;
    }
}
