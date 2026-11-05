<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PlanLocationCoverage extends Model
{
    protected $table = 'plan_location_coverage';
    protected $fillable = ['subscription_plan_id', 'target_type', 'target_code'];

    public function plan(): BelongsTo
    {
        return $this->belongsTo(SubscriptionPlan::class, 'subscription_plan_id');
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
