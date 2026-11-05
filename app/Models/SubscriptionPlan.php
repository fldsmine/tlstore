<?php

namespace App\Models;

use App\Enums\BillingInterval;
use App\Enums\SubscriptionProductType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SubscriptionPlan extends Model
{
    protected $appends = ['currency_minor_units'];

    protected $table = 'subscription_plans';
    protected $fillable = [
        'plan_key', 'product_type', 'name', 'tier_key', 'billing_interval', 'duration_count',
        'price_minor', 'currency', 'discount', 'bonus', 'is_special', 'subscription_referral_percent', 'task_referral_percent', 'max_review_tasks',
        'max_ad_shares', 'global_listing_access', 'features', 'is_active',
    ];
    protected $casts = [
        'product_type' => SubscriptionProductType::class,
        'billing_interval' => BillingInterval::class,
        'duration_count' => 'integer',
        'price_minor' => 'integer',
        'discount' => 'decimal:2',
        'bonus' => 'decimal:2',
        'subscription_referral_percent' => 'decimal:2',
        'task_referral_percent' => 'decimal:2',
        'is_special' => 'integer',
        'max_review_tasks' => 'integer',
        'max_ad_shares' => 'integer',
        'global_listing_access' => 'boolean',
        'features' => 'array',
        'is_active' => 'boolean',
    ];

    public function features(): HasMany
    {
        return $this->hasMany(SubscriptionPlanFeature::class);
    }

    public function locationCoverage(): HasMany
    {
        return $this->hasMany(PlanLocationCoverage::class);
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(UserSubscription::class);
    }

    public function getCurrencyMinorUnitsAttribute(): int
    {
        return \App\Application\Money\CurrencyMinorUnits::precision($this->currency);
    }
}
