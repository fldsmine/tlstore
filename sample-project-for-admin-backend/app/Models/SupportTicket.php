<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class SupportTicket extends Model
{
    use HasUuids;

    public const string STATUS_OPEN = 'open';

    public const string STATUS_WAITING_ON_USER = 'waiting_on_user';

    public const string STATUS_CLOSED = 'closed';

    public const array STATUSES = [self::STATUS_OPEN, self::STATUS_WAITING_ON_USER, self::STATUS_CLOSED];

    protected $fillable = [
        'user_id',
        'contact_name',
        'contact_email',
        'subject',
        'category',
        'status',
        'last_message_at',
    ];

    protected function casts(): array
    {
        return [
            'last_message_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function messages(): HasMany
    {
        return $this->hasMany(SupportTicketMessage::class)->orderBy('created_at')->orderBy('id');
    }

    public function latestMessage(): HasOne
    {
        return $this->hasOne(SupportTicketMessage::class)->ofMany(['created_at' => 'max', 'id' => 'max']);
    }

    public function reference(): string
    {
        return 'SUP-'.strtoupper(substr(str_replace('-', '', (string) $this->id), 0, 8));
    }

    public function statusLabel(): string
    {
        return match ($this->status) {
            self::STATUS_WAITING_ON_USER => 'Waiting for you',
            self::STATUS_CLOSED => 'Closed',
            default => 'Open',
        };
    }
}
