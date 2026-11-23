<?php

namespace App\Services\Mailing;

use App\Models\Package;
use App\Models\User;
use BackedEnum;
use DOMDocument;
use DOMNode;

class MailingContentRenderer
{
    private const array ALLOWED_TAGS = [
        'a', 'b', 'blockquote', 'br', 'caption', 'div', 'em', 'h1', 'h2', 'h3', 'h4',
        'hr', 'i', 'img', 'li', 'ol', 'p', 's', 'span', 'strong', 'table', 'tbody',
        'td', 'th', 'thead', 'tr', 'u', 'ul',
    ];

    private const array BLOCKED_TAGS = [
        'applet', 'audio', 'button', 'canvas', 'embed', 'form', 'iframe', 'input', 'link',
        'math', 'object', 'script', 'select', 'style', 'svg', 'textarea', 'video',
    ];

    /** Sanitize HTML while retaining a small, email-friendly set of semantic tags. */
    public function sanitizeHtml(string $html): string
    {
        if (trim($html) === '') {
            return '';
        }

        if (! class_exists(DOMDocument::class)) {
            return nl2br(e(strip_tags($html)), false);
        }

        $document = new DOMDocument('1.0', 'UTF-8');
        $previous = libxml_use_internal_errors(true);

        try {
            $document->loadHTML(
                '<!doctype html><html><body><div id="mailing-root">'.$html.'</div></body></html>',
                LIBXML_NONET | LIBXML_HTML_NODEFDTD,
            );
            $root = null;
            foreach ($document->getElementsByTagName('div') as $div) {
                if ($div->getAttribute('id') === 'mailing-root') {
                    $root = $div;
                    break;
                }
            }

            if (! $root) {
                return '';
            }

            $result = '';
            foreach ($root->childNodes as $child) {
                $result .= $this->sanitizeNode($child);
            }

            return $result;
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }
    }

    /**
     * @return array{subject:string, html:string, text:string, reply_to:?string}
     */
    public function render(MailingCampaignContent $content, User $user): array
    {
        $user->loadMissing('package');
        $safeHtml = $this->sanitizeHtml($content->html);
        $html = $this->personalize($safeHtml, $user, true);
        $plainSource = trim($content->text) !== '' ? $content->text : $this->textFromHtml($safeHtml);

        return [
            'subject' => $this->safeSubject($this->personalize($content->subject, $user, false)),
            'html' => $html,
            'text' => $this->personalize($plainSource, $user, false),
            'reply_to' => $this->validReplyTo($content->replyTo),
        ];
    }

    public function renderCampaign(\App\Models\MailingCampaign $campaign, User $user): array
    {
        return $this->render(new MailingCampaignContent(
            subject: $campaign->subject,
            html: $campaign->html_body,
            text: (string) ($campaign->text_body ?? ''),
            replyTo: $campaign->reply_to,
        ), $user);
    }

    public function sampleUser(): User
    {
        $user = new User([
            'first_name' => 'Jamie',
            'last_name' => 'Sample',
            'username' => 'jamie.sample',
            'email' => 'preview@example.test',
            'phone' => '+1 555 0100',
            'status' => 'active',
            'created_at' => now(),
        ]);
        $user->setRelation('package', new Package(['name' => 'Sample plan']));

        return $user;
    }

    private function sanitizeNode(DOMNode $node): string
    {
        if ($node->nodeType === XML_TEXT_NODE || $node->nodeType === XML_CDATA_SECTION_NODE) {
            return e($node->nodeValue ?? '');
        }

        if ($node->nodeType !== XML_ELEMENT_NODE) {
            return '';
        }

        $tag = strtolower($node->nodeName);
        if (in_array($tag, self::BLOCKED_TAGS, true)) {
            return '';
        }

        $children = '';
        foreach ($node->childNodes as $child) {
            $children .= $this->sanitizeNode($child);
        }

        if (! in_array($tag, self::ALLOWED_TAGS, true)) {
            return $children;
        }

        $attributes = $this->safeAttributes($node, $tag);
        $open = '<'.$tag.$attributes.'>';
        if (in_array($tag, ['br', 'hr', 'img'], true)) {
            return $open;
        }

        return $open.$children.'</'.$tag.'>';
    }

    private function safeAttributes(DOMNode $node, string $tag): string
    {
        if (! $node->hasAttributes()) {
            return '';
        }

        $allowed = match ($tag) {
            'a' => ['href', 'title', 'target'],
            'img' => ['src', 'alt', 'width', 'height', 'title'],
            'td', 'th' => ['colspan', 'rowspan', 'align'],
            'table' => ['cellpadding', 'cellspacing', 'border', 'width'],
            default => [],
        };

        $result = '';
        foreach ($node->attributes as $attribute) {
            $name = strtolower($attribute->name);
            $value = trim($attribute->value);
            if (! in_array($name, $allowed, true) || str_starts_with($name, 'on')) {
                continue;
            }

            if (in_array($name, ['href', 'src'], true)) {
                $safeUrl = $this->safeUrl($value, $tag === 'a' && $name === 'href');
                if ($safeUrl === null) {
                    continue;
                }
                $value = $safeUrl;
            } elseif (in_array($name, ['width', 'height', 'colspan', 'rowspan', 'cellpadding', 'cellspacing', 'border'], true)) {
                if (! ctype_digit($value) || (int) $value > 2000) {
                    continue;
                }
            } elseif ($name === 'align' && ! in_array(strtolower($value), ['left', 'right', 'center', 'justify'], true)) {
                continue;
            } elseif ($name === 'target' && $value !== '_blank') {
                continue;
            }

            $result .= ' '.$name.'="'.e($value).'"';
        }

        if ($tag === 'a' && str_contains($result, 'target="_blank"')) {
            $result .= ' rel="noopener noreferrer nofollow"';
        }

        return $result;
    }

    private function safeUrl(string $url, bool $allowMailto): ?string
    {
        if ($url === '' || str_starts_with($url, '#')) {
            return $url === '' ? null : $url;
        }

        $url = html_entity_decode($url, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $scheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));
        if (in_array($scheme, ['http', 'https'], true) && filter_var($url, FILTER_VALIDATE_URL)) {
            return $url;
        }
        if ($allowMailto && $scheme === 'mailto') {
            $address = substr($url, 7);
            return filter_var($address, FILTER_VALIDATE_EMAIL) ? 'mailto:'.$address : null;
        }

        return null;
    }

    private function personalize(string $value, User $user, bool $html): string
    {
        $first = (string) ($user->first_name ?? '');
        $last = (string) ($user->last_name ?? '');
        $values = [
            'name' => trim($first.' '.$last) ?: (string) ($user->username ?? ''),
            'first_name' => $first,
            'last_name' => $last,
            'username' => (string) ($user->username ?? ''),
            'email' => (string) ($user->email ?? ''),
            'phone' => (string) ($user->phone ?? ''),
            'package' => (string) ($user->package?->name ?? ''),
            'status' => $user->status instanceof BackedEnum
                ? (string) $user->status->value : (string) ($user->status ?? ''),
            'joined_date' => $user->created_at?->format('j F Y') ?? '',
        ];

        return preg_replace_callback('/\{\{\s*([a-z][a-z0-9_]*)\s*\}\}/i', function (array $match) use ($values, $html): string {
            $key = strtolower($match[1]);
            $replacement = $values[$key] ?? '';

            return $html ? e($replacement) : $replacement;
        }, $value) ?? $value;
    }

    private function textFromHtml(string $html): string
    {
        $html = preg_replace('/<\/(p|div|h[1-4]|li|tr|blockquote)>/i', "\n", $html) ?? $html;
        $html = preg_replace('/<br\s*\/?\s*>/i', "\n", $html) ?? $html;
        $text = html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8');

        return trim(preg_replace("/[^\S\n]+|\n{3,}/", ' ', $text) ?? $text);
    }

    private function safeSubject(string $subject): string
    {
        return trim(preg_replace('/[\r\n\0]+/', ' ', $subject) ?? $subject);
    }

    private function validReplyTo(?string $email): ?string
    {
        $email = trim((string) $email);

        return $email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL) ? $email : null;
    }
}
