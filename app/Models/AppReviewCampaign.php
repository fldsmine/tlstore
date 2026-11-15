<?php

namespace App\Models;

use App\Enums\CampaignStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AppReviewCampaign extends Model
{
    protected $appends = ['currency_minor_units'];

    protected $table = 'app_review_campaigns';
    protected $fillable = [
        'campaign_key', 'app_id', 'owner_id', 'paid_amount_minor', 'currency', 'location',
        'review_reward_minor', 'maximum_review', 'reserved_reviews', 'completed_reviews',
        'status', 'starts_at', 'ends_at', 'review_template_version_id',
    ];
    protected $casts = [
        'paid_amount_minor' => 'integer',
        'review_reward_minor' => 'integer',
        'maximum_review' => 'integer',
        'reserved_reviews' => 'integer',
        'completed_reviews' => 'integer',
        'status' => CampaignStatus::class,
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
    ];

    public function app(): BelongsTo
    {
        return $this->belongsTo(App::class);
    }

    public function reviewTemplateVersion(): BelongsTo
    {
        return $this->belongsTo(ReviewTemplateVersion::class, 'review_template_version_id');
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(ReviewTask::class);
    }

    public function reports(): HasMany
    {
        return $this->hasMany(AppReport::class, 'app_review_campaign_id');
    }

    public function getCurrencyMinorUnitsAttribute(): int
    {
        return \App\Application\Money\CurrencyMinorUnits::precision($this->currency);
    }
}
