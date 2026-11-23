<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ShareSubscription extends Model
{
    use HasUuids;

    protected function casts(): array
    {
        return [
            'allocated_minutes' => 'decimal:2',
            'daily_earning_cap' => 'decimal:6',
            'region' => 'string',
            'starts_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function package(): BelongsTo
    {
        return $this->belongsTo(Package::class);
    }

    public function callType(): BelongsTo
    {
        return $this->belongsTo(CallType::class);
    }

    public function claims(): HasMany
    {
        return $this->hasMany(CallClaim::class);
    }

    public function minuteTopUps(): HasMany
    {
        return $this->hasMany(MinuteTopUp::class);
    }

    public function scopeCurrent(Builder $query): Builder
    {
        return $query
            ->where('status', 'active')
            ->where('starts_at', '<=', now())
            ->where('expires_at', '>', now());
    }
}
