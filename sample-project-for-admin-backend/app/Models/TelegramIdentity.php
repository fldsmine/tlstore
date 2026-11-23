<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * A verified Telegram account bound to a Nextel user. Created only after
 * server-side validation of Telegram WebApp initData — never from raw
 * client-supplied IDs.
 */
class TelegramIdentity extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'user_id',
        'telegram_user_id',
        'username',
        'first_name',
        'last_name',
        'language_code',
    ];

    protected function casts(): array
    {
        return [
            'telegram_user_id' => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function messages(): HasMany
    {
        return $this->hasMany(TelegramMessage::class);
    }

    public function latestMessage(): HasOne
    {
        return $this->hasOne(TelegramMessage::class)->latestOfMany();
    }

    public function displayName(): string
    {
        $name = trim(($this->first_name ?? '').' '.($this->last_name ?? ''));

        if ($name !== '') {
            return $name;
        }

        if (filled($this->username)) {
            return '@'.$this->username;
        }

        return $this->user?->full_name ?: 'Telegram user';
    }

    public function handle(): ?string
    {
        return filled($this->username) ? '@'.$this->username : null;
    }
}
