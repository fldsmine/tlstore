<?php

namespace App\Models;

use App\Enums\CallType as CallTypeEnum;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CallType extends Model
{
    use HasFactory, HasUuids;

    protected function casts(): array
    {
        return [
            'key' => CallTypeEnum::class,
            'rate_per_minute' => 'decimal:6',
            'claimable_percentage' => 'decimal:2',
            'price' => 'decimal:6',
            'agent_price' => 'decimal:6',
            'foreign_price' => 'decimal:6',
            'foreign_agent_price' => 'decimal:6',
            'region' => 'string',
            'monthly_share_minutes' => 'decimal:2',
            'share_term_days' => 'integer',
            'max_daily_shareholder_earning' => 'decimal:6',
            'share_purchase_prompt_minutes' => 'decimal:2',
            'sort_order' => 'integer',
            'min_minutes' => 'decimal:2',
            'max_minutes' => 'decimal:2',
            'min_per_batch' => 'integer',
            'max_per_batch' => 'integer',
            'is_active' => 'boolean',
            'metadata' => 'array',
        ];
    }

    public function packages(): BelongsToMany
    {
        return $this->belongsToMany(Package::class, 'package_call_type')
            ->withPivot(['is_available', 'rate_per_minute', 'claimable_percentage'])
            ->withTimestamps();
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
        return $query->orderBy('sort_order')->orderByDesc('rate_per_minute');
    }
}
