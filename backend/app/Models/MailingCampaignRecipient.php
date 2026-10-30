<?php

namespace App\Models;

use App\Enums\MailingRecipientStatus;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MailingCampaignRecipient extends Model
{
    use HasUuids;

    protected $fillable = [
        'campaign_id',
        'user_id',
        'status',
        'attempts',
        'last_error_code',
        'last_attempt_at',
        'available_at',
        'sent_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => MailingRecipientStatus::class,
            'attempts' => 'integer',
            'last_attempt_at' => 'datetime',
            'available_at' => 'datetime',
            'sent_at' => 'datetime',
        ];
    }

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(MailingCampaign::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
