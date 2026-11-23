<?php

namespace App\Models;

use App\Enums\MailingCampaignStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MailingCampaign extends Model
{
    use HasUuids;

    protected $fillable = [
        'admin_id',
        'template_id',
        'name',
        'subject',
        'html_body',
        'text_body',
        'reply_to',
        'filters',
        'status',
        'scheduled_at',
        'recipient_count',
        'queued_count',
        'sending_count',
        'sent_count',
        'failed_count',
        'cancelled_count',
        'started_at',
        'completed_at',
        'cancelled_at',
        'failure_code',
    ];

    protected function casts(): array
    {
        return [
            'filters' => 'array',
            'status' => MailingCampaignStatus::class,
            'scheduled_at' => 'datetime',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'recipient_count' => 'integer',
            'queued_count' => 'integer',
            'sending_count' => 'integer',
            'sent_count' => 'integer',
            'failed_count' => 'integer',
            'cancelled_count' => 'integer',
        ];
    }

    public function admin(): BelongsTo
    {
        return $this->belongsTo(Admin::class);
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(MailingTemplate::class);
    }

    public function recipients(): HasMany
    {
        return $this->hasMany(MailingCampaignRecipient::class, 'campaign_id');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->whereIn('status', [
            MailingCampaignStatus::BUILDING->value,
            MailingCampaignStatus::SCHEDULED->value,
            MailingCampaignStatus::QUEUED->value,
            MailingCampaignStatus::SENDING->value,
        ]);
    }
}
