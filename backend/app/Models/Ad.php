<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Ad extends Model
{
    use HasFactory, HasUuids;

    protected function casts(): array
    {
        return [
            'reward' => 'decimal:6',
            'base_reward' => 'decimal:6',
            'version' => 'integer',
            'impressions' => 'integer',
            'views' => 'integer',
            'shares' => 'integer',
            'growth_percent' => 'decimal:2',
            'growth_interval_hours' => 'integer',
            'last_grown_at' => 'datetime',
            'is_active' => 'boolean',
        ];
    }

    public function getImageUrlAttribute(?string $value): ?string
    {
        return media_url($value);
    }

    public function adShares(): HasMany
    {
        return $this->hasMany(AdShare::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }
}
