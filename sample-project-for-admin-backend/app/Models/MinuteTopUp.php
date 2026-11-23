<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MinuteTopUp extends Model
{
    use HasFactory, HasUuids;

    protected function casts(): array
    {
        return [
            'daily_call_minutes' => 'decimal:2',
            'share_minutes' => 'decimal:2',
            'expires_at' => 'datetime',
            'share_expires_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(AdditionalMinutePlan::class, 'additional_minute_plan_id');
    }

    public function coupon(): BelongsTo
    {
        return $this->belongsTo(Coupon::class);
    }

    public function shareSubscription(): BelongsTo
    {
        return $this->belongsTo(ShareSubscription::class);
    }

    public function allocatedNumbers(): HasMany
    {
        return $this->hasMany(AllocatedNumber::class, 'minute_top_up_id');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('expires_at', '>', now());
    }

    public function scopeShareActive(Builder $query): Builder
    {
        return $query->where('share_minutes', '>', 0)
            ->whereNotNull('share_subscription_id')
            ->where('share_expires_at', '>', now())
            ->whereHas('shareSubscription', fn (Builder $subscription): Builder => $subscription
                ->where('status', 'active')
                ->where('expires_at', '>', now()));
    }

    public function scopeActiveOrShareActive(Builder $query): Builder
    {
        return $query->where(function (Builder $active): void {
            $active->where('expires_at', '>', now())
                ->orWhere(function (Builder $share): void {
                    $share->where('share_minutes', '>', 0)
                        ->whereNotNull('share_subscription_id')
                        ->where('share_expires_at', '>', now())
                        ->whereHas('shareSubscription', fn (Builder $subscription): Builder => $subscription
                            ->where('status', 'active')
                            ->where('expires_at', '>', now()));
                });
        });
    }
}
