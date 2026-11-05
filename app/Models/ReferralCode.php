<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ReferralCode extends Model
{
    protected $table = 'referral_codes';
    protected $fillable = ['user_id', 'code', 'status', 'expires_at'];
    protected $casts = ['expires_at' => 'datetime'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function attributions(): HasMany
    {
        return $this->hasMany(ReferralAttribution::class);
    }
}
