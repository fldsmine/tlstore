<?php

namespace App\Models;

use App\Enums\WalletType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Wallet extends Model
{
    protected $table = 'wallets';
    protected $fillable = ['user_id', 'wallet_type', 'currency', 'status'];
    protected $casts = ['wallet_type' => WalletType::class, 'balance_minor' => 'integer'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function ledgerEntries(): HasMany
    {
        return $this->hasMany(LedgerEntry::class);
    }

    public function withdrawalRequests(): HasMany
    {
        return $this->hasMany(WithdrawalRequest::class);
    }

    public function referralRewards(): HasMany
    {
        return $this->hasMany(ReferralReward::class);
    }
}
