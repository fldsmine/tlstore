<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class UserRankLevel extends Model
{
    protected $fillable = [
        'level', 'name', 'icon', 'minimum_subscribed_referrals',
        'minimum_referral_cash_inflow_usd', 'minimum_user_cash_inflow_usd',
        'monthly_reward_usd', 'reward_duration_months', 'is_active', 'sort_order',
    ];

    protected $casts = [
        'minimum_referral_cash_inflow_usd' => 'decimal:2',
        'minimum_user_cash_inflow_usd' => 'decimal:2',
        'monthly_reward_usd' => 'decimal:2',
        'is_active' => 'boolean',
    ];

    public function qualifications(): HasMany
    {
        return $this->hasMany(UserRankQualification::class);
    }
}
