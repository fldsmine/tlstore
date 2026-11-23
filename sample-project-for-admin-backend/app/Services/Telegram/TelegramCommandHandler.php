<?php

namespace App\Services\Telegram;

use App\Models\TelegramIdentity;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

/**
 * Replies to direct bot commands (/help, /balance, …) sent in private chats.
 * Fed by the /webhooks/telegram update endpoint. Replies are plain text with
 * emoji (no markdown escaping pitfalls); /start and /app carry an inline
 * web_app button that opens the Mini App inside the chat.
 */
class TelegramCommandHandler
{
    public function __construct(
        private readonly TelegramBotClient $bot,
        private readonly TelegramMessenger $messenger,
    ) {}

    public function handle(array $update): void
    {
        $message = Arr::get($update, 'message');

        if (! is_array($message)) {
            return;
        }

        $chatId = (int) Arr::get($message, 'chat.id', 0);
        $text = trim((string) (Arr::get($message, 'text') ?: Arr::get($message, 'caption', '')));

        if ($chatId === 0 || $text === '') {
            return;
        }

        $command = $this->parseCommand($text);

        if ($command === null) {
            $this->freeText($chatId, $message, $text);

            return;
        }

        match ($command) {
            '/start' => $this->start($chatId, (int) Arr::get($message, 'from.id', 0), (string) Arr::get($message, 'from.first_name', '')),
            '/help' => $this->help($chatId),
            '/app' => $this->app($chatId),
            '/balance' => $this->balance($chatId, (int) Arr::get($message, 'from.id', 0)),
            '/support' => $this->support($chatId),
            default => $this->bot->sendMessage($chatId, "I didn't get that 🙂 Try /help to see what I can do."),
        };
    }

    /**
     * Linked Mini App users can write to the bot; the backoffice picks the
     * thread up. Unlinked senders still get the command hint.
     */
    private function freeText(int $chatId, array $message, string $text): void
    {
        $identity = $this->identity((int) Arr::get($message, 'from.id', 0));

        if ($identity === null) {
            $this->bot->sendMessage($chatId, "I didn't get that 🙂 Try /help to see what I can do.");

            return;
        }

        $telegramMessageId = (int) Arr::get($message, 'message_id', 0) ?: null;
        $recorded = $this->messenger->recordInbound($identity, $text, $telegramMessageId);

        if ($recorded->wasRecentlyCreated) {
            $this->bot->sendMessage($chatId, 'Got it — Nextel support will reply here.');
        }
    }

    /** Normalises "/Help@NextelBot more" → "/help"; null for non-commands. */
    private function parseCommand(string $text): ?string
    {
        if (! str_starts_with($text, '/')) {
            return null;
        }

        $token = Str::lower(Str::before($text, ' '));

        return Str::before($token, '@');
    }

    private function identity(int $telegramUserId): ?TelegramIdentity
    {
        if ($telegramUserId === 0) {
            return null;
        }

        return TelegramIdentity::query()
            ->with('user')
            ->where('telegram_user_id', $telegramUserId)
            ->first();
    }

    private function start(int $chatId, int $telegramUserId, string $firstName): void
    {
        $identity = $this->identity($telegramUserId);

        $lines = [
            '👋 '.($firstName !== '' ? "Welcome, {$firstName}!" : 'Welcome!').' This is the Nextel Connect bot.',
            '',
            'Calls, eSIM data, airtime, bills and earnings — all in one place.',
            '',
            $identity && $identity->user
                ? '✅ Your Telegram is linked to @'.$identity->user->username.'. Try /balance for your wallets.'
                : 'Tap below to open the app and create your account — linking takes a few seconds.',
        ];

        $this->bot->sendMessage($chatId, implode("\n", $lines), $this->appKeyboard());
    }

    private function help(int $chatId): void
    {
        $this->bot->sendMessage($chatId, implode("\n", [
            'Nextel bot commands:',
            '',
            '/start — welcome & quick start',
            '/app — launch the Nextel Mini App',
            '/balance — your wallet balances',
            '/support — contact support',
            '/help — this list',
            '',
            'Tip: the menu button (☰) in this chat also opens Nextel.',
        ]));
    }

    private function app(int $chatId): void
    {
        $this->bot->sendMessage($chatId, '🚀 Opening Nextel…', $this->appKeyboard());
    }

    private function balance(int $chatId, int $telegramUserId): void
    {
        $identity = $this->identity($telegramUserId);

        if (! $identity || ! $identity->user) {
            $this->bot->sendMessage($chatId, implode("\n", [
                'This Telegram account is not linked to a Nextel account yet.',
                'Open the app below and sign in — your Telegram gets linked automatically.',
            ]), $this->appKeyboard());

            return;
        }

        $user = $identity->user;

        $lines = ["💼 Balances for {$user->first_name} (@{$user->username}):", ''];

        foreach ($user->walletBalances(false) as $wallet) {
            $lines[] = "{$wallet['label']}: {$wallet['amount']}";
        }

        $lines[] = '';
        $lines[] = 'Open the app for full details and withdrawals.';

        $this->bot->sendMessage($chatId, implode("\n", $lines), $this->appKeyboard());
    }

    private function support(int $chatId): void
    {
        $contact = (array) config('settings.contact', []);

        $this->bot->sendMessage($chatId, implode("\n", [
            '🛟 Nextel support',
            '',
            '📧 Email: '.($contact['email'] ?? ''),
            '📞 Phone: '.($contact['phone'] ?? ''),
            '✈️ Telegram: '.($contact['telegram_general'] ?? ''),
            '',
            'We usually reply within 24 hours.',
        ]));
    }

    /** Inline keyboard with a single web_app button opening the Mini App. */
    private function appKeyboard(): array
    {
        $url = (string) config('services.telegram.webapp_url', '');

        if ($url === '' || ! str_starts_with($url, 'https://')) {
            return [];
        }

        return [
            'reply_markup' => [
                'inline_keyboard' => [
                    [['text' => '🚀 Open Nextel', 'web_app' => ['url' => $url]]],
                ],
            ],
        ];
    }
}
