<?php

namespace App\Models;

use App\Application\Support\Media;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class App extends Model
{
    protected $table = 'apps';
    protected $fillable = [
        'prototype_id', 'name', 'short_code', 'store', 'brand_color', 'developer', 'category',
        'install_count_label', 'description', 'illustration_kind', 'paid_amount', 'location',
        'currency', 'rating', 'total_reviews', 'review_reward', 'app_icon', 'maximum_review', 'featured',
    ];
    protected $casts = [
        'prototype_id' => 'integer',
        'paid_amount' => 'decimal:2',
        'rating' => 'decimal:2',
        'total_reviews' => 'integer',
        'review_reward' => 'decimal:2',
        'maximum_review' => 'integer',
        'featured' => 'boolean',
    ];

    public function screenshots(): HasMany
    {
        return $this->hasMany(AppScreenshot::class)->orderBy('position');
    }

    public function getAppIconAttribute(?string $value): ?string
    {
        return Media::url($value);
    }

    public function reviewCampaigns(): HasMany
    {
        return $this->hasMany(AppReviewCampaign::class);
    }

    public function storeListingSnapshots(): HasMany
    {
        return $this->hasMany(AppStoreListingSnapshot::class);
    }
}
