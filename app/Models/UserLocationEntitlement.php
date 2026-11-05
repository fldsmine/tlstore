<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserLocationEntitlement extends Model
{
    protected $table = 'user_location_entitlements';
    protected $fillable = ['user_id', 'user_subscription_id', 'target_type', 'target_code', 'starts_at', 'ends_at', 'status'];
    protected $casts = ['starts_at' => 'datetime', 'ends_at' => 'datetime'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function subscription(): BelongsTo
    {
        return $this->belongsTo(UserSubscription::class, 'user_subscription_id');
    }

    public function targetCountry(): BelongsTo
    {
        return $this->belongsTo(Country::class, 'target_code', 'code');
    }

    public function targetRegion(): BelongsTo
    {
        return $this->belongsTo(Region::class, 'target_code', 'code');
    }
}
