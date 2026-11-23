<?php

namespace App\Models;

use App\Enums\PayLaterStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MinuteCredit extends Model
{
    use HasFactory, HasUuids;

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:6',
            'minutes' => 'decimal:2',
            'due_date' => 'date',
            'status' => PayLaterStatus::class,
            'repaid_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(AdditionalMinutePlan::class, 'additional_minute_plan_id');
    }

    public function minuteTopUp(): BelongsTo
    {
        return $this->belongsTo(MinuteTopUp::class);
    }

    public function scopeOutstanding(Builder $query): Builder
    {
        return $query->where('status', PayLaterStatus::ACTIVE);
    }

    public function getIsOverdueAttribute(): bool
    {
        return $this->status === PayLaterStatus::ACTIVE && $this->due_date?->isBefore(today());
    }
}
