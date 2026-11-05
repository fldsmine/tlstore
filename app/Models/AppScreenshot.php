<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Application\Support\Media;

class AppScreenshot extends Model
{
    protected $table = 'app_screenshots';
    protected $fillable = ['app_id', 'position', 'source_type', 'storage_disk', 'storage_path', 'illustration_index', 'alt_text'];
    protected $casts = ['position' => 'integer', 'illustration_index' => 'integer'];

    protected $appends = ['image_url'];

    public function getImageUrlAttribute(): ?string
    {
        if (! $this->storage_path || ! $this->storage_disk) {
            return null;
        }

        return Media::url($this->storage_path, $this->storage_disk);
    }

    public function app(): BelongsTo
    {
        return $this->belongsTo(App::class);
    }
}
