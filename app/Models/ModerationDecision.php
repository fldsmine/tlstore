<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ModerationDecision extends Model
{
    protected $table = 'moderation_decisions';
    protected $fillable = [
        'decision_key', 'review_submission_id', 'ad_share_event_id', 'decided_by', 'decided_by_admin_id', 'decision', 'reason_code',
        'reason', 'policy_version', 'decided_at',
    ];
    protected $casts = ['policy_version' => 'integer', 'decided_at' => 'datetime'];

    public function submission(): BelongsTo
    {
        return $this->belongsTo(ReviewSubmission::class, 'review_submission_id');
    }

    public function moderator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'decided_by');
    }

    public function adminModerator(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'decided_by_admin_id');
    }
}
