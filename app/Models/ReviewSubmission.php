<?php

namespace App\Models;

use App\Enums\ReviewSubmissionStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ReviewSubmission extends Model
{
    protected $table = 'review_submissions';
    protected $fillable = [
        'review_task_id', 'reviewer_id', 'review_template_version_id', 'status',
        'duration_seconds', 'submitted_at', 'decided_at',
    ];
    protected $casts = [
        'status' => ReviewSubmissionStatus::class,
        'duration_seconds' => 'integer',
        'submitted_at' => 'datetime',
        'decided_at' => 'datetime',
    ];

    public function task(): BelongsTo
    {
        return $this->belongsTo(ReviewTask::class, 'review_task_id');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewer_id');
    }

    public function templateVersion(): BelongsTo
    {
        return $this->belongsTo(ReviewTemplateVersion::class, 'review_template_version_id');
    }

    public function answers(): HasMany
    {
        return $this->hasMany(ReviewAnswer::class);
    }

    public function moderationDecisions(): HasMany
    {
        return $this->hasMany(ModerationDecision::class);
    }

    public function riskSignals(): HasMany
    {
        return $this->hasMany(RiskSignal::class);
    }

    public function ledgerEntries(): HasMany
    {
        return $this->hasMany(LedgerEntry::class, 'source_id')->where('source_type', 'review_submission');
    }
}
