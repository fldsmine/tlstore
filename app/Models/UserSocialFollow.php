<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserSocialFollow extends Model
{
    protected $fillable = [
        'user_id', 'social_handle_id', 'status', 'reward_amount_minor',
        'currency', 'ledger_entry_id', 'followed_at',
    ];

    protected $casts = [
        'reward_amount_minor' => 'integer',
        'followed_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function socialHandle(): BelongsTo
    {
        return $this->belongsTo(SocialHandle::class);
    }

    public function ledgerEntry(): BelongsTo
    {
        return $this->belongsTo(LedgerEntry::class);
    }
}
