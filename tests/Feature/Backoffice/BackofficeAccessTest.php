<?php

namespace Tests\Feature\Backoffice;

use App\Livewire\Backoffice\Settings\SettingsIndex;
use App\Models\Admin;
use App\Models\AuditLog;
use App\Models\SystemSetting;
use Database\Seeders\AdminBootstrapSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Authz + settings-write coverage for the backoffice. NB: these tests cannot be
 * executed in the environment this was authored in (no PHP/Composer); they are
 * written to run under `php artisan test` / `composer test` after dependency
 * installation. Do not treat a green local marker as authoritative otherwise.
 */
class BackofficeAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_redirected_to_backoffice_login(): void
    {
        $this->get(route('backoffice.dashboard'))
            ->assertRedirectToRoute('backoffice.login');
    }

    public function test_active_admin_can_sign_in_and_see_the_dashboard(): void
    {
        $admin = Admin::factory()->create(['status' => 'active']);

        $this->actingAs($admin, 'admin')
            ->get(route('backoffice.dashboard'))
            ->assertOk();
    }

    public function test_disabled_admin_is_signed_out_and_redirected(): void
    {
        $admin = Admin::factory()->create(['status' => 'disabled']);

        $this->actingAs($admin, 'admin')
            ->get(route('backoffice.dashboard'))
            ->assertRedirectToRoute('backoffice.login');
    }

    public function test_administrators_area_requires_super_admin(): void
    {
        $regular = Admin::factory()->create(['role' => 'admin', 'status' => 'active']);
        $super = Admin::factory()->superAdmin()->create(['status' => 'active']);

        $this->actingAs($regular, 'admin')
            ->get(route('backoffice.admins.index'))
            ->assertForbidden();

        $this->actingAs($super, 'admin')
            ->get(route('backoffice.admins.index'))
            ->assertOk();
    }

    public function test_allowlisted_setting_save_updates_value_and_writes_audit(): void
    {
        $admin = Admin::factory()->create(['status' => 'active']);
        SystemSetting::query()->create(['setting_key' => 'campaign.service_fee_percent', 'value' => 5, 'is_public' => true]);

        $this->actingAs($admin, 'admin');

        Livewire::test(SettingsIndex::class)
            ->set('inputs.campaign.service_fee_percent', '12.5')
            ->call('save', 'campaign.service_fee_percent')
            ->assertHasNoErrors();

        $setting = SystemSetting::query()->where('setting_key', 'campaign.service_fee_percent')->firstOrFail();
        $this->assertSame(12.5, (float) $setting->value);
        $this->assertDatabaseHas('audit_logs', [
            'actor_admin_id' => $admin->id,
            'action' => 'system_setting.updated',
            'entity_type' => 'system_setting',
            'entity_id' => 'campaign.service_fee_percent',
        ]);
    }

    public function test_non_allowlisted_setting_is_never_written(): void
    {
        $admin = Admin::factory()->create(['status' => 'active']);
        $this->actingAs($admin, 'admin');

        Livewire::test(SettingsIndex::class)
            ->set('inputs.payment.active_gateways', 'korapay')
            ->call('save', 'payment.active_gateways');

        $this->assertDatabaseMissing('system_settings', ['setting_key' => 'payment.active_gateways']);
        $this->assertDatabaseMissing('audit_logs', ['entity_id' => 'payment.active_gateways']);
        $this->assertNull(AuditLog::query()->where('entity_id', 'payment.active_gateways')->first());
    }

    public function test_out_of_range_fee_is_rejected(): void
    {
        $admin = Admin::factory()->create(['status' => 'active']);
        SystemSetting::query()->create(['setting_key' => 'campaign.service_fee_percent', 'value' => 5, 'is_public' => true]);
        $this->actingAs($admin, 'admin');

        Livewire::test(SettingsIndex::class)
            ->set('inputs.campaign.service_fee_percent', '150')
            ->call('save', 'campaign.service_fee_percent')
            ->assertHasErrors(['inputs.campaign.service_fee_percent']);

        $this->assertSame(5.0, (float) SystemSetting::query()->where('setting_key', 'campaign.service_fee_percent')->value('value'));
    }

    public function test_bootstrap_seeder_creates_super_admin_without_default_password(): void
    {
        putenv('ADMIN_BOOTSTRAP_EMAIL=root@example.com');
        putenv('ADMIN_BOOTSTRAP_PASSWORD=correct-horse-battery-staple-9');
        putenv('ADMIN_BOOTSTRAP_NAME=Root');
        $_ENV['ADMIN_BOOTSTRAP_EMAIL'] = 'root@example.com';
        $_ENV['ADMIN_BOOTSTRAP_PASSWORD'] = 'correct-horse-battery-staple-9';
        $_ENV['ADMIN_BOOTSTRAP_NAME'] = 'Root';

        $this->seed(AdminBootstrapSeeder::class);

        $admin = Admin::query()->where('email', 'root@example.com')->firstOrFail();
        $this->assertTrue($admin->isSuperAdmin());
        $this->assertSame('active', $admin->status);
        $this->assertTrue(Hash::check('correct-horse-battery-staple-9', $admin->password));
        $this->assertDatabaseHas('audit_logs', ['action' => 'admin.bootstrap_upserted', 'entity_id' => (string) $admin->id]);
    }
}
