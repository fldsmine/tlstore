<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CloudStoragePlan extends Model
{
    use HasUuids;

    protected function casts(): array
    {
        return [
            'token_price' => 'decimal:6',
            'agent_price' => 'decimal:6',
            'foreign_token_price' => 'decimal:6',
            'foreign_agent_price' => 'decimal:6',
            'renewal_token_price' => 'decimal:6',
            'renewal_agent_price' => 'decimal:6',
            'foreign_renewal_token_price' => 'decimal:6',
            'foreign_renewal_agent_price' => 'decimal:6',
            'extra_contacts' => 'integer',
            'extra_sms' => 'integer',
            'referral_bonus' => 'decimal:6',
            'duration_days' => 'integer',
            'sort_order' => 'integer',
            'is_active' => 'boolean',
            'tier_level' => 'integer',
        ];
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('sort_order')->orderBy('name');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeForTier(Builder $query, int $tierLevel): Builder
    {
        return $query->where('tier_level', $tierLevel);
    }

    public function packs(): HasMany
    {
        return $this->hasMany(CloudStorageTopUp::class);
    }
}
