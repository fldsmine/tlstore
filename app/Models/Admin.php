<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Laravel\Sanctum\HasApiTokens;

class Admin extends Authenticatable
{
    use HasApiTokens;

    protected $table = 'admins';

    protected $fillable = ['full_name', 'email', 'password', 'role', 'status', 'last_login_at'];

    protected $hidden = ['password', 'remember_token'];

    protected $casts = [
        'password' => 'hashed',
        'last_login_at' => 'datetime',
    ];

    public function auditLogs(): HasMany
    {
        return $this->hasMany(AuditLog::class, 'actor_admin_id');
    }

    public function settingsUpdated(): HasMany
    {
        return $this->hasMany(SystemSetting::class, 'updated_by_admin_id');
    }
}
