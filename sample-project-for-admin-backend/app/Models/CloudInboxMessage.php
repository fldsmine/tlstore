<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CloudInboxMessage extends Model
{
    use HasFactory, HasUuids;

    public const string TYPE_SPONSORED = 'sponsored';

    public const string TYPE_SYSTEM = 'system';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'earn_rate' => 'decimal:6',
            'read_at' => 'datetime',
            'claimed_at' => 'datetime',
            'meta' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function getAvatarUrlAttribute(): ?string
    {
        return media_url($this->avatar);
    }

    public function getIsSponsoredAttribute(): bool
    {
        return $this->type === self::TYPE_SPONSORED;
    }

    public function getIsClaimableAttribute(): bool
    {
        return $this->is_sponsored && (float) $this->earn_rate > 0 && $this->claimed_at === null;
    }

    public function getClaimHostAttribute(): ?string
    {
        return $this->claim_url ? (parse_url($this->claim_url, PHP_URL_HOST) ?: $this->claim_url) : null;
    }

    public function scopeUnread(Builder $query): Builder
    {
        return $query->whereNull('read_at');
    }

    public function scopeUnclaimed(Builder $query): Builder
    {
        return $query->where('type', self::TYPE_SPONSORED)->where('earn_rate', '>', 0)->whereNull('claimed_at');
    }
}
