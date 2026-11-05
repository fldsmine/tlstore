<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Notification extends Model
{
    protected $table = 'notifications';
    protected $fillable = ['user_id', 'notification_key', 'type', 'title', 'body', 'data', 'read_at'];
    protected $casts = ['data' => 'array', 'read_at' => 'datetime'];

    public function preferenceCategory(): string
    {
        return match (true) {
            str_contains($this->type, 'review') => 'reviews',
            str_contains($this->type, 'share') => 'ad_shares',
            str_contains($this->type, 'withdraw') => 'withdrawals',
            str_contains($this->type, 'deposit'), str_contains($this->type, 'payment'), str_contains($this->type, 'topup') => 'payments',
            str_contains($this->type, 'referral') => 'referrals',
            str_contains($this->type, 'account'), str_contains($this->type, 'security') => 'account',
            default => 'system',
        };
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function deliveries(): HasMany
    {
        return $this->hasMany(NotificationDelivery::class);
    }
}
