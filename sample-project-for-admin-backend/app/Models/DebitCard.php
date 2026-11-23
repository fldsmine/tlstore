<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DebitCard extends Model
{
    use HasUuids;

    protected function casts(): array
    {
        return [
            'card_pin' => 'hashed',
            'expiry_month' => 'integer',
            'expiry_year' => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public static function generateNumber(): string
    {
        return '5061'.str_pad((string) random_int(0, 999999999999), 12, '0', STR_PAD_LEFT);
    }

    public static function generateCvv(): string
    {
        return str_pad((string) random_int(0, 999), 3, '0', STR_PAD_LEFT);
    }

    public function getMaskedNumberAttribute(): string
    {
        return '•••• •••• •••• '.substr($this->card_number, -4);
    }

    public function getFormattedNumberAttribute(): string
    {
        return trim(chunk_split($this->card_number, 4, ' '));
    }

    public function getExpiryAttribute(): string
    {
        return str_pad((string) $this->expiry_month, 2, '0', STR_PAD_LEFT).'/'.substr((string) $this->expiry_year, -2);
    }
}
