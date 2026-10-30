<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class CloudBroadcast extends Model
{
    use HasUuids;

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
        ];
    }

    public function number(): BelongsTo
    {
        return $this->belongsTo(CloudNumber::class, 'cloud_number_id');
    }

    public function recipients(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'cloud_broadcast_user')
            ->withPivot('status', 'responded_at')
            ->withTimestamps();
    }

    public function isExpired(): bool
    {
        return $this->expires_at !== null && $this->expires_at->isPast();
    }
}
