<?php

namespace App\Models;

use App\Enums\AdminRole;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class Admin extends Authenticatable
{
    use HasFactory, HasUuids, Notifiable;

    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'role' => AdminRole::class,
        ];
    }

    public function getAvatarAttribute(?string $value): ?string
    {
        return media_url($value);
    }

    protected static function booted(): void
    {
        static::updating(function (Admin $admin): void {
            if ($admin->isDirty('role') && auth('admin')->check() && ! auth('admin')->user()->canManageAdmins()) {
                abort(403, 'You are not allowed to change an admin role.');
            }
        });
    }

    public function supportTicketMessages(): HasMany
    {
        return $this->hasMany(SupportTicketMessage::class);
    }

    public function isSuper(): bool
    {
        return $this->role === AdminRole::SUPER;
    }

    public function canWrite(): bool
    {
        return $this->role->canWrite();
    }

    public function canManageAdmins(): bool
    {
        return $this->role->canManageAdmins();
    }

    public function scopeSearch(Builder $query, ?string $search): Builder
    {
        return $query->when($search, fn (Builder $query, string $search) => $query
            ->where(fn (Builder $query) => $query
                ->where('name', 'like', "%{$search}%")
                ->orWhere('email', 'like', "%{$search}%")
            )
        );
    }
}
