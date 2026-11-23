<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CallClaim extends Model
{
    use HasUuids;

    protected function casts(): array
    {
        return [
            'duration_minutes' => 'decimal:2',
            'amount' => 'decimal:6',
            'claimed_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function shareSubscription(): BelongsTo
    {
        return $this->belongsTo(ShareSubscription::class);
    }

    public function callRecord(): BelongsTo
    {
        return $this->belongsTo(CallRecord::class);
    }

    public function callType(): BelongsTo
    {
        return $this->belongsTo(CallType::class);
    }

    public function minuteTopUp(): BelongsTo
    {
        return $this->belongsTo(MinuteTopUp::class);
    }
}
