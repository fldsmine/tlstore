<?php

namespace App\Models;

use App\Enums\CloudCallStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CloudCall extends Model
{
    use HasUuids;

    protected function casts(): array
    {
        return [
            'status' => CloudCallStatus::class,
            'earned' => 'decimal:6',
            'duration_seconds' => 'integer',
            'started_at' => 'datetime',
            'ended_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function number(): BelongsTo
    {
        return $this->belongsTo(CloudNumber::class, 'cloud_number_id');
    }

    public function scopeCompleted(Builder $query): Builder
    {
        return $query->where('status', CloudCallStatus::COMPLETED->value);
    }
}
