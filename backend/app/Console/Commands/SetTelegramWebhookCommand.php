<?php

namespace App\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

/**
 * Registers the bot update webhook (/webhooks/telegram) with the Bot API so
 * the bot receives commands (/start, /help, /balance, …). A secret token is
 * attached; Telegram echoes it back on every update in the
 * X-Telegram-Bot-Api-Secret-Token header and mismatched calls are dropped.
 *
 * The command list shown in the Telegram command menu is configured once
 * with @BotFather (/setcommands) — see docs/TELEGRAM.md section 10.
 */
#[Signature('telegram:set-webhook {--url= : Public HTTPS URL for updates (defaults to services.telegram.webhook_url, else TELEGRAM_WEBAPP_URL host + /webhooks/telegram)} {--secret= : Secret token (defaults to services.telegram.webhook_secret)}')]
#[Description('Register the Telegram bot webhook that receives command updates.')]
class SetTelegramWebhookCommand extends Command
{
    public function handle(): int
    {
        $token = (string) config('services.telegram.bot_token', '');

        if ($token === '') {
            $this->error('TELEGRAM_BOT_TOKEN is not configured.');

            return self::FAILURE;
        }

        $url = (string) ($this->option('url') ?: config('services.telegram.webhook_url', ''));

        if ($url === '') {
            $url = $this->deriveUrlFromWebapp();
        }

        if ($url === '' || ! str_starts_with($url, 'https://')) {
            $this->error('No webhook URL. Pass --url or set TELEGRAM_WEBHOOK_URL (HTTPS required).');

            return self::FAILURE;
        }

        $secret = (string) ($this->option('secret') ?: config('services.telegram.webhook_secret', ''));

        if ($secret === '') {
            $this->error('No webhook secret. Pass --secret or set TELEGRAM_WEBHOOK_SECRET.');

            return self::FAILURE;
        }

        $response = Http::timeout(15)
            ->post("https://api.telegram.org/bot{$token}/setWebhook", [
                'url' => $url,
                'secret_token' => $secret,
                'allowed_updates' => ['message'],
            ]);

        $body = $response->json();

        if ($response->successful() && ($body['ok'] ?? false)) {
            $this->info("Webhook set — updates now arrive at {$url}");
            $this->line('Remember to configure the command list once via @BotFather → /setcommands.');

            return self::SUCCESS;
        }

        $this->error('Telegram API error: '.($body['description'] ?? $response->status()));

        return self::FAILURE;
    }

    /** https://host/mini-app → https://host/webhooks/telegram */
    private function deriveUrlFromWebapp(): string
    {
        $webapp = (string) config('services.telegram.webapp_url', '');

        if ($webapp === '' || ! str_starts_with($webapp, 'https://')) {
            return '';
        }

        $parts = parse_url($webapp);

        if (($parts['host'] ?? '') === '') {
            return '';
        }

        return $parts['scheme'].'://'.$parts['host'].'/webhooks/telegram';
    }
}
