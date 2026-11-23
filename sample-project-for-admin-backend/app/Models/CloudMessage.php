<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CloudMessage extends Model
{
    use HasUuids;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'earn_rate' => 'decimal:6',
            'recipients_count' => 'integer',
        ];
    }

    public function number(): BelongsTo
    {
        return $this->belongsTo(CloudNumber::class, 'cloud_number_id');
    }
}
