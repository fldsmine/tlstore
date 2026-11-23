<?php

namespace App\Services\Telegram;

use App\Models\Admin;
use App\Models\TelegramIdentity;
use App\Models\TelegramMessage;

/**
 * Backoffice ↔ Mini App DM thread. Outbound messages go through the Bot API
 * (only delivered if the user has started the bot); inbound free-text from
 * linked identities is persisted from the webhook for staff to answer.
 */
class TelegramMessenger
{
    public function __construct(private readonly TelegramBotClient $bot) {}

    public function sendTo(TelegramIdentity $identity, string $body, ?Admin $admin = null): TelegramMessage
    {
        $message = TelegramMessage::query()->create([
            'telegram_identity_id' => $identity->id,
            'admin_id' => $admin?->id,
            'direction' => TelegramMessage::DIRECTION_OUTBOUND,
            'body' => $body,
            'status' => TelegramMessage::STATUS_FAILED,
        ]);

        $result = $this->bot->sendMessageResult((int) $identity->telegram_user_id, $body);

        if ($result['ok']) {
            $message->forceFill([
                'status' => TelegramMessage::STATUS_SENT,
                'telegram_message_id' => $result['message_id'],
                'delivered_at' => now(),
            ])->save();
        }

        return $message->refresh();
    }

    public function recordInbound(TelegramIdentity $identity, string $body, ?int $telegramMessageId = null): TelegramMessage
    {
        if ($telegramMessageId) {
            $existing = TelegramMessage::query()
                ->where('telegram_identity_id', $identity->id)
                ->where('direction', TelegramMessage::DIRECTION_INBOUND)
                ->where('telegram_message_id', $telegramMessageId)
                ->first();

            if ($existing) {
                return $existing;
            }
        }

        return TelegramMessage::query()->create([
            'telegram_identity_id' => $identity->id,
            'direction' => TelegramMessage::DIRECTION_INBOUND,
            'body' => $body,
            'telegram_message_id' => $telegramMessageId,
            'status' => TelegramMessage::STATUS_RECEIVED,
        ]);
    }

    /**
     * @return array{total: int, sent: int, failed: int}
     */
    public function broadcast(string $body, Admin $admin): array
    {
        $sent = 0;
        $failed = 0;
        $total = 0;

        TelegramIdentity::query()
            ->orderBy('created_at')
            ->each(function (TelegramIdentity $identity) use ($body, $admin, &$sent, &$failed, &$total): void {
                $total++;
                $message = $this->sendTo($identity, $body, $admin);
                $message->status === TelegramMessage::STATUS_SENT ? $sent++ : $failed++;
            });

        return ['total' => $total, 'sent' => $sent, 'failed' => $failed];
    }
}
