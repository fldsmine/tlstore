<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CloudNumber extends Model
{
    use HasUuids;

    protected function casts(): array
    {
        return [
            'earning_per_call' => 'decimal:6',
            'sort_order' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function notes(): HasMany
    {
        return $this->hasMany(CloudNote::class)->orderBy('sort_order');
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'cloud_user_number');
    }

    public function getAvatarUrlAttribute(): ?string
    {
        return media_url($this->avatar);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }
}
