<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserRankQualification extends Model
{
    protected $fillable = [
        'user_id', 'user_rank_level_id', 'subscribed_referrals_at_qualification',
        'referral_cash_inflow_usd_at_qualification', 'user_cash_inflow_usd_at_qualification',
        'monthly_reward_usd_at_qualification', 'reward_duration_months_at_qualification',
        'qualified_at', 'next_reward_at', 'rewards_paid', 'status',
    ];

    protected $casts = [
        'referral_cash_inflow_usd_at_qualification' => 'decimal:2',
        'user_cash_inflow_usd_at_qualification' => 'decimal:2',
        'monthly_reward_usd_at_qualification' => 'decimal:2',
        'qualified_at' => 'datetime',
        'next_reward_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function level(): BelongsTo
    {
        return $this->belongsTo(UserRankLevel::class, 'user_rank_level_id');
    }
}
