<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReferralReward extends Model
{
    protected $table = 'referral_rewards';
    protected $fillable = ['referral_attribution_id', 'wallet_id', 'ledger_entry_id', 'reward_type', 'source_type', 'source_id', 'amount_minor', 'currency', 'status'];
    protected $casts = ['amount_minor' => 'integer'];

    public function attribution(): BelongsTo
    {
        return $this->belongsTo(ReferralAttribution::class, 'referral_attribution_id');
    }

    public function wallet(): BelongsTo
    {
        return $this->belongsTo(Wallet::class);
    }

    public function ledgerEntry(): BelongsTo
    {
        return $this->belongsTo(LedgerEntry::class);
    }
}
