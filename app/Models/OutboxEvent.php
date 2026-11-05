<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class OutboxEvent extends Model
{
    use TrustLensMorphMap;

    protected $table = 'outbox_events';
    protected $fillable = [
        'event_key', 'event_type', 'aggregate_type', 'aggregate_id', 'payload', 'status',
        'attempts', 'available_at', 'processed_at', 'last_error',
    ];
    protected $casts = [
        'payload' => 'array',
        'attempts' => 'integer',
        'available_at' => 'datetime',
        'processed_at' => 'datetime',
    ];

    public function aggregate(): MorphTo
    {
        return $this->morphTo(__FUNCTION__, 'aggregate_type', 'aggregate_id');
    }
}
