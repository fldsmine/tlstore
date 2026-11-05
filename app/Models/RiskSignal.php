<?php

namespace App\Models;

use App\Enums\RiskSeverity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RiskSignal extends Model
{
    protected $table = 'risk_signals';
    public $timestamps = false;
    protected $fillable = [
        'signal_key', 'review_submission_id', 'ad_share_event_id', 'signal_type',
        'severity', 'source', 'rule_version', 'details', 'created_at',
    ];
    protected $casts = [
        'severity' => RiskSeverity::class,
        'details' => 'array',
        'created_at' => 'datetime',
    ];

    public function reviewSubmission(): BelongsTo
    {
        return $this->belongsTo(ReviewSubmission::class, 'review_submission_id');
    }

    public function adShareEvent(): BelongsTo
    {
        return $this->belongsTo(AdShareEvent::class, 'ad_share_event_id');
    }
}
