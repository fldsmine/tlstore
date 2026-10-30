<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CloudNote extends Model
{
    use HasUuids;

    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function number(): BelongsTo
    {
        return $this->belongsTo(CloudNumber::class, 'cloud_number_id');
    }

    public function audioUrl(): ?string
    {
        return media_url($this->audio_path);
    }
}
