<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CloudDistribution extends Model
{
    use HasUuids;

    public const string TYPE_PROMOTER = 'promoter';

    public const string TYPE_SHAREHOLDER = 'shareholder';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:6',
            'recipients_count' => 'integer',
        ];
    }

    public function number(): BelongsTo
    {
        return $this->belongsTo(CloudNumber::class, 'cloud_number_id');
    }

    public function claims(): HasMany
    {
        return $this->hasMany(CloudBonusClaim::class);
    }

    public function getIsShareholderAttribute(): bool
    {
        return $this->type === self::TYPE_SHAREHOLDER;
    }

    public function getAvatarUrlAttribute(): ?string
    {
        return media_url($this->avatar);
    }
}
