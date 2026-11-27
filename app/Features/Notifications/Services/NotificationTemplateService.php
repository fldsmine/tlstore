<?php

namespace App\Features\Notifications\Services;

use App\Models\Notification;
use App\Models\NotificationDelivery;
use App\Models\NotificationTemplate;
use App\Models\SystemSetting;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

final class NotificationTemplateService
{
    public function templateFor(string $act): ?NotificationTemplate
    {
        return NotificationTemplate::query()->where('act', $act)->first();
    }

    public function emailEnabled(): bool
    {
        return $this->booleanSetting('notifications.email.enabled', false);
    }

    public function pushEnabled(): bool
    {
        return $this->booleanSetting('notifications.push.enabled', true);
    }

    /** Deliver a configured email template and persist a delivery result. */
    public function sendNotificationEmail(Notification $notification, NotificationTemplate $template): void
    {
        $user = $notification->user;
        if (! $this->emailEnabled() || ! $template->email_status || ! $user?->email || $user->trashed()) {
            return;
        }

        $preference = $user->notificationPreferences()
            ->where('category', $notification->preferenceCategory())->first();
        if (! $preference?->email_enabled) {
            return;
        }

        $delivery = NotificationDelivery::query()->create([
            'notification_id' => $notification->id,
            'channel' => 'email',
            'provider' => $this->mailerName(),
            'status' => 'queued',
        ]);

        try {
            $context = $this->context($user, $notification->title, $notification->body, $notification->data ?? []);
            $subject = $this->render((string) ($template->subject ?: $notification->title), $context);
            $body = $this->renderHtml((string) ($template->email_body ?: $notification->body ?: $notification->title), $context);
            $this->sendHtml($user, $subject, $body);
            $delivery->update(['status' => 'sent', 'sent_at' => now()]);
        } catch (Throwable $error) {
            $delivery->update(['status' => 'failed', 'failure_reason' => mb_substr($error->getMessage(), 0, 2000)]);
            report($error);
        }
    }

    /** Return a template-driven push body, while retaining the event title. */
    public function pushCopy(Notification $notification, ?NotificationTemplate $template): array
    {
        if (! $template || ! $template->firebase_status || trim((string) $template->firebase_body) === '') {
            return ['title' => $notification->title, 'body' => (string) ($notification->body ?? '')];
        }

        return [
            'title' => $notification->title,
            'body' => $this->render($template->firebase_body, $this->context(
                $notification->user,
                $notification->title,
                $notification->body,
                $notification->data ?? [],
            )),
        ];
    }

    /** Password reset is an account/security mail and ignores optional category preferences. */
    public function sendPasswordReset(User $user, string $token): void
    {
        $template = $this->templateFor('password_reset');
        if (! $this->emailEnabled() || ! $template?->email_status || ! $user->email || $user->trashed()) {
            return;
        }

        try {
            $subject = $this->render((string) ($template->subject ?: 'Reset your '.config('app.name').' password'), $this->context($user, 'Password reset', '', [
                'code' => $token,
                'expires_in_minutes' => 60,
            ]));
            $body = $this->renderHtml((string) ($template->email_body ?: "Use this code to reset your password: {{code}}"), $this->context($user, 'Password reset', '', [
                'code' => $token,
                'expires_in_minutes' => 60,
            ]));
            $this->sendHtml($user, $subject, $body);
        } catch (Throwable $error) {
            report($error);
            Log::warning('Password reset email could not be sent.', ['user_id' => $user->id, 'reason' => $error->getMessage()]);
        }
    }

    public function render(string $template, array $context): string
    {
        return preg_replace_callback('/\{\{\s*([a-zA-Z0-9_.-]+)\s*\}\}/', static function (array $match) use ($context): string {
            $value = data_get($context, $match[1], '');
            return is_scalar($value) ? (string) $value : '';
        }, $template) ?? $template;
    }

    /** Sanitize HTML before showing it in the admin editor or sending it by email. */
    public function sanitizeTemplateHtml(string $html): string
    {
        if (! class_exists(\DOMDocument::class)) {
            return nl2br(e(strip_tags($html)), false);
        }

        $document = new \DOMDocument('1.0', 'UTF-8');
        $previous = libxml_use_internal_errors(true);
        $document->loadHTML('<?xml encoding="UTF-8"><div id="email-template-root">'.$html.'</div>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD | LIBXML_NONET);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        $root = $document->getElementById('email-template-root');
        if (! $root) return '';

        $allowedTags = ['a','b','blockquote','br','div','em','h1','h2','h3','hr','i','img','li','ol','p','span','strong','table','tbody','td','th','thead','tr','u','ul'];
        $dropTags = ['script','style','iframe','object','embed','form','input','button','video','audio','svg','math','link','meta'];
        $allowedStyle = ['background-color','border','border-bottom','border-radius','color','font-family','font-size','font-style','font-weight','line-height','margin','margin-bottom','margin-top','padding','padding-bottom','padding-left','padding-right','padding-top','text-align','text-decoration','vertical-align','width','max-width'];

        $clean = function (\DOMNode $parent) use (&$clean, $allowedTags, $dropTags, $allowedStyle): void {
            foreach (iterator_to_array($parent->childNodes) as $node) {
                if ($node instanceof \DOMElement) {
                    $tag = strtolower($node->tagName);
                    if (in_array($tag, $dropTags, true)) { $parent->removeChild($node); continue; }
                    $clean($node);
                    if (! in_array($tag, $allowedTags, true)) {
                        while ($node->firstChild) $parent->insertBefore($node->firstChild, $node);
                        $parent->removeChild($node);
                        continue;
                    }
                    foreach (iterator_to_array($node->attributes) as $attribute) {
                        $name = strtolower($attribute->name);
                        $value = trim($attribute->value);
                        $keep = in_array($name, ['alt','title','align','valign','width','height','colspan','rowspan','cellpadding','cellspacing','border','role'], true);
                        if ($name === 'href' && $tag === 'a') $keep = (bool) preg_match('/^(https?:|mailto:|tel:)/i', $value);
                        if ($name === 'src' && $tag === 'img') $keep = (bool) preg_match('/^https?:\/\//i', $value);
                        if ($name === 'style') {
                            $declarations = [];
                            foreach (explode(';', $value) as $declaration) {
                                if (! str_contains($declaration, ':')) continue;
                                [$property, $cssValue] = array_map('trim', explode(':', $declaration, 2));
                                if (in_array(strtolower($property), $allowedStyle, true) && ! preg_match('/url\s*\(|expression|javascript|[<>]/i', $cssValue)) $declarations[] = strtolower($property).':'.$cssValue;
                            }
                            $keep = $declarations !== [];
                            if ($keep) $node->setAttribute('style', implode(';', $declarations));
                        }
                        if (! $keep || str_starts_with($name, 'on')) $node->removeAttributeNode($attribute);
                    }
                }
            }
        };
        $clean($root);
        $result = '';
        foreach ($root->childNodes as $child) $result .= $document->saveHTML($child);
        return $result;
    }

    private function renderHtml(string $template, array $context): string
    {
        $safe = $this->sanitizeTemplateHtml($template);
        $safe = preg_replace_callback('/\{\{\s*([a-zA-Z0-9_.-]+)\s*\}\}/', static function (array $match) use ($context): string {
            $value = data_get($context, $match[1], '');
            return is_scalar($value) ? e((string) $value) : '';
        }, $safe) ?? $safe;
        return $this->sanitizeTemplateHtml($safe);
    }

    private function context(?User $user, string $title, ?string $body, array $data): array
    {
        return array_merge([
            'fullname' => $user?->full_name ?: $user?->username ?: 'there',
            'username' => $user?->username ?? '',
            'email' => $user?->email ?? '',
            'title' => $title,
            'body' => $body ?? '',
            'message' => $body ?? $title,
            'site_name' => config('app.name', 'TLStore'),
            'site_url' => config('app.url', ''),
        ], $data);
    }

    private function sendHtml(User $user, string $subject, string $body): void
    {
        $mailer = $this->mailerName();
        $fromAddress = (string) $this->setting('notifications.email.from_address', config('mail.from.address'));
        $fromName = (string) $this->setting('notifications.email.from_name', config('mail.from.name'));
        $html = '<!doctype html><html><body style="margin:0;background:#f1f5f2;color:#132d23;font:15px/1.65 Arial,sans-serif;padding:28px 14px">'
            .'<main style="max-width:620px;margin:auto;background:#fff;border:1px solid #dce6df;border-radius:16px;padding:30px">'
            .'<div style="font-weight:800;font-size:18px;color:#08734f;margin-bottom:20px">'.e(config('app.name', 'TLStore')).'</div>'
            .'<div>'. $body .'</div>'
            .'<div style="border-top:1px solid #e6ece8;margin-top:24px;padding-top:14px;color:#64756d;font-size:12px">'.e(config('app.name', 'TLStore')).' · Review · Share · Earn</div>'
            .'</main></body></html>';

        Mail::mailer($mailer)->html($html, static function ($message) use ($user, $subject, $fromAddress, $fromName): void {
            $message->to($user->email, $user->full_name ?: $user->username)
                ->from($fromAddress, $fromName)
                ->subject($subject);
        });
    }

    private function mailerName(): string
    {
        $mailer = (string) $this->setting('notifications.email.mailer', config('mail.default', 'smtp'));
        return array_key_exists($mailer, config('mail.mailers', [])) ? $mailer : (string) config('mail.default', 'smtp');
    }

    private function booleanSetting(string $key, bool $default): bool
    {
        $value = $this->setting($key, $default);
        if (is_array($value)) $value = $value['enabled'] ?? $default;
        return filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? $default;
    }

    private function setting(string $key, mixed $default = null): mixed
    {
        return SystemSetting::query()->where('setting_key', $key)->first()?->value ?? $default;
    }
}
