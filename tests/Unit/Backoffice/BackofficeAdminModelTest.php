<?php

namespace Tests\Unit\Backoffice;

use App\Models\Admin;
use PHPUnit\Framework\TestCase;

class BackofficeAdminModelTest extends TestCase
{
    public function test_super_admin_roles_are_recognised_with_loose_casing(): void
    {
        $admin = new Admin();

        foreach (['super_admin', 'super-admin', 'SUPER_ADMIN', ' Super '] as $role) {
            $admin->role = $role;
            $this->assertTrue($admin->isSuperAdmin(), "Expected {$role} to be super admin");
        }

        foreach (['admin', 'staff', '', null] as $role) {
            $admin->role = $role;
            $this->assertFalse($admin->isSuperAdmin(), 'Expected non-super role to fail closed');
        }
    }

    public function test_role_label_is_human_readable(): void
    {
        $admin = new Admin();
        $admin->role = 'super_admin';
        $this->assertSame('Super Admin', $admin->roleLabel());
    }

    public function test_initials_are_uppercase_and_fall_back(): void
    {
        $admin = new Admin();
        $admin->full_name = 'Ada Lovelace';
        $this->assertSame('AD', $admin->initials());

        $admin->full_name = '';
        $this->assertSame('A', $admin->initials());
    }
}
