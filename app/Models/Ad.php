<?php

namespace App\Models;

use App\Enums\CampaignStatus;
use App\Application\Support\Media;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

class Ad extends Model
{
    protected $appends = ['currency_minor_units'];

    protected $table = 'ads';
    protected $fillable = [
        'campaign_key', 'title', 'type', 'description', 'share_message', 'owner_id', 'creative_path',
        'destination_url', 'paid_amount', 'location', 'currency', 'total_share', 'reserved_share',
        'share_reward', 'maximum_share', 'status', 'starts_at', 'ends_at', 'featured',
    ];
    protected $casts = [
        'paid_amount' => 'decimal:2',
        'total_share' => 'integer',
        'reserved_share' => 'integer',
        'share_reward' => 'decimal:2',
        'maximum_share' => 'integer',
        'status' => CampaignStatus::class,
        'featured' => 'boolean',
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
    ];

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function shareTasks(): HasMany
    {
        return $this->hasMany(AdShareTask::class);
    }

    public function shareEvents(): HasManyThrough
    {
        return $this->hasManyThrough(AdShareEvent::class, AdShareTask::class, 'ad_id', 'ad_share_task_id');
    }

    public function getCurrencyMinorUnitsAttribute(): int
    {
        return \App\Application\Money\CurrencyMinorUnits::precision($this->currency);
    }

    public function getCreativePathAttribute(?string $value): ?string
    {
        return Media::url($value);
    }
}
