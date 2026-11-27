<?php

namespace App\Models;

use App\Enums\AdShareEventStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class AdShareEvent extends Model
{
    use TrustLensMorphMap;

    protected $table = 'ad_share_events';
    protected $fillable = [
        'ad_share_task_id', 'channel', 'tracking_token', 'external_reference', 'status',
        'verification_method', 'evidence', 'shared_at', 'validated_at',
    ];
    protected $casts = [
        'status' => AdShareEventStatus::class,
        'evidence' => 'array',
        'shared_at' => 'datetime',
        'validated_at' => 'datetime',
    ];

    public function task(): BelongsTo
    {
        return $this->belongsTo(AdShareTask::class, 'ad_share_task_id');
    }

    public function riskSignals(): HasMany
    {
        return $this->hasMany(RiskSignal::class);
    }

    public function moderationDecisions(): HasMany
    {
        return $this->hasMany(ModerationDecision::class, 'ad_share_event_id');
    }

    public function ledgerEntries(): MorphMany
    {
        return $this->morphMany(LedgerEntry::class, 'source');
    }
}
