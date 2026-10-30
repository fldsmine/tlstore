<?php

namespace App\Models;

use App\Enums\RewardTargetStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RewardTarget extends Model
{
    use HasFactory, HasUuids;

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'target_amount' => 'decimal:6',
            'reward_amount' => 'decimal:6',
            'reward_percent' => 'decimal:2',
            'achieved_at' => 'datetime',
            'claimed_at' => 'datetime',
            'status' => RewardTargetStatus::class,
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function scopeUnclaimed(Builder $query): Builder
    {
        return $query->where('status', RewardTargetStatus::ACHIEVED);
    }
}
