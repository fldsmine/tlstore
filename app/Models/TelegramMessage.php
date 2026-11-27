<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One message handed to (or queued for) the Telegram Bot API.
 *
 * `status` is the delivery state machine: queued → sending → sent | failed.
 * Rows are never deleted by delivery code so the admin console can show the
 * history and failed messages can be re-sent by hand.
 */
class TelegramMessage extends Model
{
    public const STATUS_QUEUED = 'queued';

    public const STATUS_SENDING = 'sending';

    public const STATUS_SENT = 'sent';

    public const STATUS_FAILED = 'failed';

    public const KIND_ANNOUNCEMENT = 'announcement';

    public const KIND_TEST = 'test';

    public const KIND_WITHDRAWAL_REQUEST = 'withdrawal_request';

    public const KIND_WITHDRAWAL_STATUS = 'withdrawal_status';

    protected $fillable = [
        'kind',
        'chat_id',
        'body',
        'attachment_type',
        'attachment_path',
        'status',
        'dedupe_key',
        'telegram_message_id',
        'error',
        'sent_by_admin_id',
        'withdrawal_request_id',
        'meta',
        'sent_at',
    ];

    protected $casts = [
        'meta' => 'array',
        'telegram_message_id' => 'integer',
        'sent_at' => 'datetime',
    ];

    protected $hidden = ['chat_id'];

    public function admin(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'sent_by_admin_id');
    }

    public function withdrawal(): BelongsTo
    {
        return $this->belongsTo(WithdrawalRequest::class, 'withdrawal_request_id');
    }

    public function markSent(?int $messageId): void
    {
        $this->forceFill([
            'status' => self::STATUS_SENT,
            'telegram_message_id' => $messageId,
            'sent_at' => now(),
            'error' => null,
        ])->save();
    }

    public function markFailed(string $error): void
    {
        $this->forceFill([
            'status' => self::STATUS_FAILED,
            'error' => mb_substr($error, 0, 1000),
        ])->save();
    }

    public function kindLabel(): string
    {
        return match ($this->kind) {
            self::KIND_ANNOUNCEMENT => 'Announcement',
            self::KIND_TEST => 'Test message',
            self::KIND_WITHDRAWAL_REQUEST => 'Withdrawal request',
            self::KIND_WITHDRAWAL_STATUS => 'Withdrawal update',
            default => ucfirst(str_replace('_', ' ', (string) $this->kind)),
        };
    }

    /** A one-line, tag-free excerpt for the history table. */
    public function excerpt(int $limit = 90): string
    {
        $text = trim(preg_replace('/\s+/', ' ', html_entity_decode(strip_tags((string) $this->body), ENT_QUOTES | ENT_HTML5, 'UTF-8')) ?? '');

        return mb_strlen($text) > $limit ? mb_substr($text, 0, $limit - 1).'…' : $text;
    }

    public function hasAttachment(): bool
    {
        return filled($this->attachment_path);
    }
}
