<?php

namespace App\Models;

use Database\Factories\AdminFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Support\Str;
use Laravel\Sanctum\HasApiTokens;

class Admin extends Authenticatable
{
    use HasApiTokens, HasFactory;

    protected $table = 'admins';

    protected $fillable = ['full_name', 'email', 'password', 'role', 'status', 'last_login_at'];

    protected $hidden = ['password', 'remember_token'];

    protected $casts = [
        'password' => 'hashed',
        'last_login_at' => 'datetime',
    ];

    /**
     * Super administrators may manage other administrators. Every role value treated
     * as super admin is listed here; anything else is a standard admin (operational
     * access identical to the existing admin API).
     */
    public const SUPER_ADMIN_ROLES = ['super_admin', 'super-admin', 'super'];

    public function isSuperAdmin(): bool
    {
        return in_array(strtolower(trim((string) $this->role)), self::SUPER_ADMIN_ROLES, true);
    }

    public function roleLabel(): string
    {
        return Str::headline(str_replace(['_', '-'], ' ', (string) $this->role ?: 'admin'));
    }

    public function initials(): string
    {
        return Str::upper(Str::substr(trim((string) $this->full_name), 0, 2)) ?: 'A';
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    protected function newFactory(): AdminFactory
    {
        return AdminFactory::new();
    }

    public function auditLogs(): HasMany
    {
        return $this->hasMany(AuditLog::class, 'actor_admin_id');
    }

    public function settingsUpdated(): HasMany
    {
        return $this->hasMany(SystemSetting::class, 'updated_by_admin_id');
    }
}
