<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CallRecord extends Model
{
    use HasFactory, HasUuids;

    protected function casts(): array
    {
        return [
            'duration_minutes' => 'decimal:2',
            'rate_per_minute' => 'decimal:6',
            'earnings' => 'decimal:6',
        ];
    }

    public function callType(): BelongsTo
    {
        return $this->belongsTo(CallType::class);
    }

    public function claims(): HasMany
    {
        return $this->hasMany(CallClaim::class);
    }
}
