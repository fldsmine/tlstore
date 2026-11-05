<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AppVersion extends Model
{
    protected $fillable = [
        'platform', 'version_name', 'build_number', 'minimum_supported_build', 'title', 'release_notes',
        'direct_download_path', 'direct_download_url', 'play_store_url', 'app_store_url', 'force_update',
        'is_active', 'published_at', 'created_by',
    ];

    protected $casts = [
        'build_number' => 'integer', 'minimum_supported_build' => 'integer', 'force_update' => 'boolean',
        'is_active' => 'boolean', 'published_at' => 'datetime',
    ];

    public function creator(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'created_by');
    }
}
