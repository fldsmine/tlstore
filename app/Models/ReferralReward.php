<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReferralReward extends Model
{
    protected $table = 'referral_rewards';
    protected $fillable = ['referral_attribution_id', 'beneficiary_user_id', 'referral_level', 'subscription_plan_id', 'reward_percent', 'base_amount_minor', 'base_currency', 'wallet_id', 'ledger_entry_id', 'reward_type', 'source_type', 'source_id', 'amount_minor', 'currency', 'status'];
    protected $casts = ['amount_minor' => 'integer', 'base_amount_minor' => 'integer', 'referral_level' => 'integer', 'reward_percent' => 'decimal:2'];

    public function attribution(): BelongsTo
    {
        return $this->belongsTo(ReferralAttribution::class, 'referral_attribution_id');
    }

    public function wallet(): BelongsTo
    {
        return $this->belongsTo(Wallet::class);
    }

    public function beneficiary(): BelongsTo
    {
        return $this->belongsTo(User::class, 'beneficiary_user_id');
    }

    public function subscriptionPlan(): BelongsTo
    {
        return $this->belongsTo(SubscriptionPlan::class);
    }

    public function ledgerEntry(): BelongsTo
    {
        return $this->belongsTo(LedgerEntry::class);
    }
}
