<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SocialHandle extends Model
{
    protected $fillable = [
        'handle_key', 'name', 'channel_url', 'icon', 'reward_amount_minor',
        'currency', 'is_active', 'sort_order',
    ];

    protected $casts = [
        'reward_amount_minor' => 'integer',
        'is_active' => 'boolean',
        'sort_order' => 'integer',
    ];

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true)->orderBy('sort_order')->orderBy('id');
    }

    public function userFollows(): HasMany
    {
        return $this->hasMany(UserSocialFollow::class);
    }
}
