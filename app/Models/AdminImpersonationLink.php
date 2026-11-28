<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Laravel\Sanctum\PersonalAccessToken;

/**
 * A one-time hand-off between the admin console and the Flutter web app.
 *
 * The administrator requests a link, the console opens the web app with the
 * plain code in the query string, and the app exchanges it for a short-lived
 * user session. The plain code is never stored — only its SHA-256 hash.
 */
class AdminImpersonationLink extends Model
{
    protected $fillable = [
        'admin_id', 'user_id', 'code_hash', 'expires_at', 'used_at',
        'consumed_ip', 'consumed_user_agent', 'personal_access_token_id',
    ];

    protected $hidden = ['code_hash'];

    protected $casts = [
        'expires_at' => 'datetime',
        'used_at' => 'datetime',
    ];

    public function admin(): BelongsTo
    {
        return $this->belongsTo(Admin::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function accessToken(): BelongsTo
    {
        return $this->belongsTo(PersonalAccessToken::class, 'personal_access_token_id');
    }

    public function isUsable(): bool
    {
        return $this->used_at === null && $this->expires_at->isFuture();
    }
}
