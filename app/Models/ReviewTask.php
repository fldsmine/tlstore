<?php

namespace App\Models;

use App\Enums\TaskStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class ReviewTask extends Model
{
    protected $appends = ['reward_currency_minor_units'];

    protected $table = 'review_tasks';
    protected $fillable = [
        'app_review_campaign_id', 'reviewer_id', 'subscription_plan_id', 'review_template_version_id', 'status',
        'idempotency_key', 'reserved_reward_minor', 'reward_currency', 'claimed_at', 'expires_at', 'completed_at',
    ];
    protected $casts = [
        'status' => TaskStatus::class,
        'reserved_reward_minor' => 'integer',
        'claimed_at' => 'datetime',
        'expires_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    public function getRewardCurrencyMinorUnitsAttribute(): int
    {
        return \App\Application\Money\CurrencyMinorUnits::precision($this->reward_currency ?: 'USD');
    }

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(AppReviewCampaign::class, 'app_review_campaign_id');
    }

    public function subscriptionPlan(): BelongsTo
    {
        return $this->belongsTo(SubscriptionPlan::class);
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewer_id');
    }

    public function templateVersion(): BelongsTo
    {
        return $this->belongsTo(ReviewTemplateVersion::class, 'review_template_version_id');
    }

    public function submission(): HasOne
    {
        return $this->hasOne(ReviewSubmission::class);
    }
}
