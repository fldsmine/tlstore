<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SubscriptionPlanFeature extends Model
{
    protected $table = 'subscription_plan_features';
    protected $fillable = ['subscription_plan_id', 'feature_key', 'label', 'value'];
    protected $casts = ['value' => 'array'];

    public function plan(): BelongsTo
    {
        return $this->belongsTo(SubscriptionPlan::class, 'subscription_plan_id');
    }
}
