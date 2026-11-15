<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PackageAddon extends Model
{
    protected $fillable = ['subscription_plan_id', 'addon_key', 'name', 'price_minor', 'discount', 'currency', 'billing_interval', 'duration_count', 'is_active', 'features'];

    protected $casts = ['price_minor' => 'integer', 'discount' => 'decimal:2', 'duration_count' => 'integer', 'is_active' => 'boolean', 'features' => 'array'];

    public function plan(): BelongsTo
    {
        return $this->belongsTo(SubscriptionPlan::class, 'subscription_plan_id');
    }

    public function purchases(): HasMany
    {
        return $this->hasMany(UserPackageAddon::class);
    }
}
