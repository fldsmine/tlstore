<?php

namespace App\Models;

use App\Enums\TaskStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AdShareTask extends Model
{
    protected $table = 'ad_share_tasks';
    protected $fillable = [
        'ad_id', 'user_id', 'status', 'idempotency_key', 'reserved_reward_minor',
        'claimed_at', 'expires_at', 'completed_at',
    ];
    protected $casts = [
        'status' => TaskStatus::class,
        'reserved_reward_minor' => 'integer',
        'claimed_at' => 'datetime',
        'expires_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    public function ad(): BelongsTo
    {
        return $this->belongsTo(Ad::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function events(): HasMany
    {
        return $this->hasMany(AdShareEvent::class);
    }
}
