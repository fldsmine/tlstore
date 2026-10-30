<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AdditionalMinutePlan extends Model
{
    use HasFactory, HasUuids;

    protected function casts(): array
    {
        return [
            'price' => 'decimal:6',
            'agent_price' => 'decimal:6',
            'foreign_price' => 'decimal:6',
            'foreign_agent_price' => 'decimal:6',
            'daily_call_minutes' => 'decimal:2',
            'share_minutes' => 'decimal:2',
            'duration_days' => 'integer',
            'paylater_duration_days' => 'integer',
            'min_earning_rate' => 'decimal:6',
            'referral_bonus' => 'decimal:6',
            'features' => 'array',
            'metadata' => 'array',
            'is_featured' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('daily_call_minutes');
    }
}
