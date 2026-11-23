<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TelegramMessage extends Model
{
    use HasFactory, HasUuids;

    public const string DIRECTION_INBOUND = 'inbound';

    public const string DIRECTION_OUTBOUND = 'outbound';

    public const string STATUS_RECEIVED = 'received';

    public const string STATUS_SENT = 'sent';

    public const string STATUS_FAILED = 'failed';

    protected function casts(): array
    {
        return [
            'telegram_message_id' => 'integer',
            'read_at' => 'datetime',
            'delivered_at' => 'datetime',
        ];
    }

    public function identity(): BelongsTo
    {
        return $this->belongsTo(TelegramIdentity::class, 'telegram_identity_id');
    }

    public function admin(): BelongsTo
    {
        return $this->belongsTo(Admin::class);
    }

    public function isInbound(): bool
    {
        return $this->direction === self::DIRECTION_INBOUND;
    }

    public function isOutbound(): bool
    {
        return $this->direction === self::DIRECTION_OUTBOUND;
    }
}
