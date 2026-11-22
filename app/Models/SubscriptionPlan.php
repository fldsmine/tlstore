<?php

namespace App\Models;

use App\Enums\BillingInterval;
use App\Enums\SubscriptionProductType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SubscriptionPlan extends Model
{
    protected $appends = ['currency_minor_units'];

    protected $table = 'subscription_plans';
    protected $fillable = [
        'plan_key', 'product_type', 'name', 'tier_key', 'billing_interval', 'duration_count',
        'price_minor', 'currency', 'discount', 'bonus', 'is_special', 'addons', 'subscription_referral_percent', 'task_referral_percent',
        'subscription_referral_tiers', 'task_referral_tiers', 'max_review_tasks',
        'max_ad_shares', 'subscription_group_id', 'eligible_app_store',
        'eligible_app_category', 'minimum_withdrawal_minor', 'withdrawal_days', 'daily_revenue_minor',
        'task_reward_minor', 'daily_task_limit', 'features', 'is_active',
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
        'subscription_referral_tiers' => 'array',
        'task_referral_tiers' => 'array',
        'is_special' => 'integer',
        'addons' => 'boolean',
        'max_review_tasks' => 'integer',
        'max_ad_shares' => 'integer',
        'subscription_group_id' => 'integer',
        'minimum_withdrawal_minor' => 'integer',
        'withdrawal_days' => 'array',
        'daily_revenue_minor' => 'integer',
        'task_reward_minor' => 'integer',
        'daily_task_limit' => 'integer',
        'features' => 'array',
        'is_active' => 'boolean',
    ];

    public function features(): HasMany
    {
        return $this->hasMany(SubscriptionPlanFeature::class);
    }

    public function group(): BelongsTo
    {
        return $this->belongsTo(SubscriptionGroup::class, 'subscription_group_id');
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(UserSubscription::class);
    }

    public function packageAddon(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(PackageAddon::class, 'subscription_plan_id');
    }

    public function getCurrencyMinorUnitsAttribute(): int
    {
        return \App\Application\Money\CurrencyMinorUnits::precision($this->currency);
    }
}
