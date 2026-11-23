<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SocialFollow extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'user_social_follows';

    protected $fillable = [
        'user_id',
        'social_handle_id',
        'followed_at',
        'unfollowed_at',
        'unfollowed_by_admin_id',
    ];

    protected function casts(): array
    {
        return [
            'followed_at' => 'datetime',
            'unfollowed_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function socialHandle(): BelongsTo
    {
        return $this->belongsTo(SocialHandle::class);
    }

    public function removedByAdmin(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'unfollowed_by_admin_id');
    }

    public function scopeCurrent(Builder $query): Builder
    {
        return $query->whereNull('unfollowed_at');
    }
}
