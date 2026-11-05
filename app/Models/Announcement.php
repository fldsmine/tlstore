<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Announcement extends Model
{
    public function dismissals(): HasMany
    {
        return $this->hasMany(UserAnnouncementDismissal::class);
    }

    protected $fillable = [
        'title', 'body', 'action_url', 'action_label', 'is_dismissible',
        'type', 'display_type', 'is_active', 'starts_at', 'ends_at',
    ];

    protected $casts = [
        'is_dismissible' => 'boolean',
        'display_type' => 'integer',
        'is_active' => 'boolean',
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
    ];

    public function scopeVisibleNow(Builder $query): Builder
    {
        return $query->where('is_active', true)
            ->where(fn (Builder $schedule) => $schedule->whereNull('starts_at')->orWhere('starts_at', '<=', now()))
            ->where(fn (Builder $schedule) => $schedule->whereNull('ends_at')->orWhere('ends_at', '>=', now()));
    }
}
