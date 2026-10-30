<?php

namespace App\Livewire\Backoffice\PaymentManagers\Concerns;

/**
 * Role guards for the payment-manager pages.
 *
 * Livewire actions arrive as separate requests that do NOT replay the route middleware, so a page
 * protected by `EnsureAdminHasRole` at the route level must re-check the role inside every action
 * that can reach a provider API or the database. These helpers are that re-check.
 */
trait GuardsPaymentManagerPages
{
    /** Any signed-in backoffice admin. */
    protected function assertAdmin(): void
    {
        abort_unless(auth('admin')->user() !== null, 403, 'Sign in to the backoffice first.');
    }

    /** Super admins only — the pages that move money. */
    protected function assertSuperAdmin(): void
    {
        abort_unless(auth('admin')->user()?->isSuper() ?? false, 403, 'Only super admins can move money.');
    }
}
