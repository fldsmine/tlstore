<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AppStoreListingSnapshot extends Model
{
    protected $table = 'app_store_listing_snapshots';
    protected $fillable = ['app_id', 'store', 'external_listing_id', 'source_url', 'rating', 'total_reviews', 'metadata', 'captured_at'];
    protected $casts = [
        'rating' => 'decimal:2',
        'total_reviews' => 'integer',
        'metadata' => 'array',
        'captured_at' => 'datetime',
    ];

    public function app(): BelongsTo
    {
        return $this->belongsTo(App::class);
    }
}
