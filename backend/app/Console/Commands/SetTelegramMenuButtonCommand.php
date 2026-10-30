<?php

namespace App\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

/**
 * Sets the bot's chat menu button so tapping it opens the Nextel Mini App.
 *
 * The Mini App itself (its name, icon and primary launch entry) is registered
 * once with @BotFather; this command only wires the in-chat menu button to the
 * deployed Mini App URL. The bot token stays server-side — it is read from
 * config and never printed or exposed to the frontend.
 */
#[Signature('telegram:set-menu {--text=Open Nextel : Label shown on the menu button} {--url= : Mini App URL (defaults to services.telegram.webapp_url)}')]
#[Description('Point the Telegram bot menu button at the Nextel Mini App.')]
class SetTelegramMenuButtonCommand extends Command
{
    public function handle(): int
    {
        $token = (string) config('services.telegram.bot_token', '');
        $url = (string) ($this->option('url') ?: config('services.telegram.webapp_url', ''));

        if ($token === '') {
            $this->error('TELEGRAM_BOT_TOKEN is not configured.');

            return self::FAILURE;
        }

        if ($url === '') {
            $this->error('No Mini App URL. Pass --url or set TELEGRAM_WEBAPP_URL.');

            return self::FAILURE;
        }

        if (! str_starts_with($url, 'https://')) {
            $this->error('The Mini App URL must use HTTPS (Telegram requirement).');

            return self::FAILURE;
        }

        $response = Http::timeout(15)
            ->post("https://api.telegram.org/bot{$token}/setChatMenuButton", [
                'menu_button' => [
                    'type' => 'web_app',
                    'text' => (string) $this->option('text'),
                    'web_app' => ['url' => $url],
                ],
            ]);

        $body = $response->json();

        if ($response->successful() && ($body['ok'] ?? false)) {
            $this->info("Menu button set — it now opens {$url}");

            return self::SUCCESS;
        }

        $this->error('Telegram API error: '.($body['description'] ?? $response->status()));

        return self::FAILURE;
    }
}
