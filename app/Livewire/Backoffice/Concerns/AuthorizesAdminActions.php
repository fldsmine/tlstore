<?php

namespace App\Livewire\Backoffice\Concerns;

/**
 * Server-side authorization re-checks for backoffice Livewire components.
 *
 * Livewire update requests (/livewire/update) do not pass through the backoffice
 * route middleware, so every component with a privileged action MUST call one of
 * these before mutating anything. Route-level middleware (`admin.session`,
 * `admin.super`) protects the initial page load only.
 */
trait AuthorizesAdminActions
{
    /** Any active admin may operate the backoffice (mirrors the admin REST API). */
    protected function authorizeAdmin(): \App\Models\Admin
    {
        $admin = auth('admin')->user();

        abort_unless($admin && $admin->isActive(), 403);

        return $admin;
    }

    /** Destructive or account-level operations: super admins only. */
    protected function authorizeSuperAdmin(): \App\Models\Admin
    {
        $admin = $this->authorizeAdmin();

        abort_unless($admin->isSuperAdmin(), 403, 'This action is restricted to super administrators.');

        return $admin;
    }
}
