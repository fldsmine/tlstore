<?php

namespace App\Models;

use App\Enums\TaskStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class ReviewTask extends Model
{
    protected $table = 'review_tasks';
    protected $fillable = [
        'app_review_campaign_id', 'reviewer_id', 'review_template_version_id', 'status',
        'idempotency_key', 'reserved_reward_minor', 'claimed_at', 'expires_at', 'completed_at',
    ];
    protected $casts = [
        'status' => TaskStatus::class,
        'reserved_reward_minor' => 'integer',
        'claimed_at' => 'datetime',
        'expires_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(AppReviewCampaign::class, 'app_review_campaign_id');
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
