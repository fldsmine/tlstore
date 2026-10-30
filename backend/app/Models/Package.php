<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Package extends Model
{
    use HasFactory, HasUuids;

    protected function casts(): array
    {
        return [
            'price' => 'decimal:6',
            'agent_price' => 'decimal:6',
            'foreign_price' => 'decimal:6',
            'foreign_agent_price' => 'decimal:6',
            'direct_referral_bonus' => 'decimal:6',
            'indirect_referral_bonus' => 'decimal:6',
            'upgrade_price' => 'decimal:6',
            'upgrade_agent_price' => 'decimal:6',
            'foreign_upgrade_price' => 'decimal:6',
            'foreign_upgrade_agent_price' => 'decimal:6',
            'upgrade_direct_referral_bonus' => 'decimal:6',
            'upgrade_indirect_referral_bonus' => 'decimal:6',
            'daily_call_minutes' => 'decimal:2',
            'monthly_share_minutes' => 'decimal:2',
            'share_term_days' => 'integer',
            'max_daily_shareholder_earning' => 'decimal:6',
            'call_id_limit' => 'integer',
            'tier_level' => 'integer',
            'enable_cloud' => 'boolean',
            'cloud_calls' => 'boolean',
            'cloud_sms' => 'boolean',
            'cloud_contact_limit' => 'integer',
            'cloud_sms_limit' => 'integer',
            'cloud_daily_earning_cap' => 'decimal:6',
            'cloud_hourly_earning_cap' => 'decimal:6',
            'features' => 'array',
            'metadata' => 'array',
            'is_featured' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function callTypes(): BelongsToMany
    {
        return $this->belongsToMany(CallType::class, 'package_call_type')
            ->withPivot(['is_available', 'rate_per_minute', 'claimable_percentage'])
            ->withTimestamps();
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function shareSubscriptions(): HasMany
    {
        return $this->hasMany(ShareSubscription::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('tier_level');
    }
}
