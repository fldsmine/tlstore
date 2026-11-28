<?php

namespace Tests\Feature;

use App\Enums\LedgerDirection;
use App\Models\Admin;
use App\Models\AuditLog;
use App\Models\Country;
use App\Models\LedgerEntry;
use App\Models\Notification;
use App\Models\User;
use App\Models\Wallet;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Admin console → Users: the dedicated list page and the per-account workspace
 * that replaced the generic `/admin/modules/users` CRUD screen.
 */
class AdminUsersAreaTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // users.country_code is a foreign key to countries.code.
        foreach ([['NG', 'Nigeria', 'NGN'], ['GH', 'Ghana', 'GHS'], ['US', 'United States', 'USD']] as [$code, $name, $currency]) {
            Country::query()->firstOrCreate(['code' => $code], [
                'name' => $name,
                'default_currency' => $currency,
                'is_available' => true,
            ]);
        }
    }

    public function test_the_generic_users_module_is_gone(): void
    {
        $this->assertNull(config('admin.modules.users'));

        $this->actingAs($this->admin('super_admin'), 'admin')
            ->get('/admin/modules/users')
            ->assertNotFound();
    }

    public function test_the_area_requires_an_admin_session(): void
    {
        $user = User::factory()->create();

        $this->get(route('admin.users.index'))->assertRedirect(route('admin.login'));
        $this->get(route('admin.users.show', $user->id))->assertRedirect(route('admin.login'));
    }

    public function test_the_list_searches_filters_and_hides_deleted_accounts_by_default(): void
    {
        $adaeze = User::factory()->create(['full_name' => 'Adaeze Okafor', 'username' => 'adaeze-'.Str::lower(Str::random(5))]);
        $banned = User::factory()->create(['full_name' => 'Banned Person', 'is_banned' => true, 'status' => 'banned']);
        $deleted = User::factory()->create(['full_name' => 'Deleted Person']);
        $deleted->delete();

        $this->actingAs($this->admin('admin'), 'admin')
            ->get(route('admin.users.index'))
            ->assertOk()
            ->assertSee('Adaeze Okafor')
            ->assertSee('Banned Person')
            ->assertDontSee('Deleted Person');

        $this->actingAs($this->admin('admin'), 'admin')
            ->get(route('admin.users.index', ['q' => 'Adaeze']))
            ->assertOk()
            ->assertSee('Adaeze Okafor')
            ->assertDontSee('Banned Person');

        $this->actingAs($this->admin('admin'), 'admin')
            ->get(route('admin.users.index', ['state' => 'banned']))
            ->assertOk()
            ->assertSee('Banned Person')
            ->assertDontSee('Adaeze Okafor');

        $this->actingAs($this->admin('admin'), 'admin')
            ->get(route('admin.users.index', ['deleted' => 'only']))
            ->assertOk()
            ->assertSee('Deleted Person')
            ->assertDontSee('Adaeze Okafor');
    }

    public function test_every_account_tab_renders(): void
    {
        $user = User::factory()->unverified()->create();
        Wallet::query()->create(['user_id' => $user->id, 'wallet_type' => 'share', 'currency' => 'NGN', 'status' => 'active']);
        AuditLog::query()->create([
            'audit_key' => (string) Str::uuid(),
            'action' => 'user.ban',
            'entity_type' => 'User',
            'entity_id' => (string) $user->id,
            'after_values' => ['is_banned' => true],
            'created_at' => now(),
        ]);

        $admin = $this->admin('super_admin');

        foreach (['show', 'wallet', 'tasks', 'withdrawals', 'referrals', 'notifications', 'activity'] as $page) {
            $this->actingAs($admin, 'admin')
                ->get(route('admin.users.'.$page, $user->id))
                ->assertOk()
                ->assertSee($user->username);
        }

        $this->actingAs($admin, 'admin')
            ->get(route('admin.users.wallet', $user->id))
            ->assertSee('Manual adjustment');
    }

    public function test_readers_without_edit_rights_see_a_read_only_workspace(): void
    {
        $user = User::factory()->create();

        $this->actingAs($this->admin('moderator'), 'admin')
            ->get(route('admin.users.show', $user->id))
            ->assertOk()
            ->assertSee('Read only');
    }

    public function test_profile_details_can_be_edited_and_the_change_is_audited(): void
    {
        $user = User::factory()->create();

        $this->actingAs($this->admin('admin'), 'admin')
            ->patch(route('admin.users.profile.update', $user->id), [
                'full_name' => 'Updated Name',
                'username' => $user->username,
                'email' => $user->email,
                'phone' => '+2348000000000',
                'country_code' => 'GH',
                'preferred_currency' => 'GHS',
                'is_member' => '1',
            ])
            ->assertRedirect(route('admin.users.show', $user->id));

        $user->refresh();
        $this->assertSame('Updated Name', $user->full_name);
        $this->assertSame('GH', $user->country_code);
        $this->assertSame('GHS', $user->preferred_currency);
        $this->assertSame(1, (int) $user->is_member);

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'user.profile_updated',
            'entity_type' => 'User',
            'entity_id' => (string) $user->id,
        ]);
    }

    public function test_finance_can_post_a_wallet_adjustment_and_moderators_cannot(): void
    {
        $user = User::factory()->create(['wallet_currency' => 'NGN']);
        $payload = [
            'wallet_type' => 'share',
            'direction' => 'credit',
            'amount_minor' => 25000,
            'reason' => 'Goodwill credit for a delayed payout',
        ];

        $this->actingAs($this->admin('moderator'), 'admin')
            ->post(route('admin.users.action', [$user->id, 'wallet-adjustment']), $payload)
            ->assertForbidden();

        $this->actingAs($this->admin('finance'), 'admin')
            ->post(route('admin.users.action', [$user->id, 'wallet-adjustment']), $payload)
            ->assertRedirect();

        $wallet = Wallet::query()->where('user_id', $user->id)->where('wallet_type', 'share')->firstOrFail();
        $this->assertSame(25000, (int) $wallet->balance_minor);
        $this->assertSame(LedgerDirection::Credit, LedgerEntry::query()->where('wallet_id', $wallet->id)->firstOrFail()->direction);
        $this->assertDatabaseHas('audit_logs', ['action' => 'user.wallet_adjusted']);
    }

    public function test_debits_cannot_overdraw_a_wallet(): void
    {
        $user = User::factory()->create(['wallet_currency' => 'NGN']);

        $this->actingAs($this->admin('admin'), 'admin')
            ->post(route('admin.users.action', [$user->id, 'wallet-adjustment']), [
                'wallet_type' => 'share',
                'direction' => 'debit',
                'amount_minor' => 500,
                'reason' => 'Clawback attempt',
            ])
            ->assertSessionHasErrors('amount_minor');
    }

    public function test_banning_revokes_sessions_and_unbanning_restores_access(): void
    {
        $user = User::factory()->create();
        $user->createToken('mobile');
        $this->assertSame(1, $user->tokens()->count());

        $this->actingAs($this->admin('moderator'), 'admin')
            ->post(route('admin.users.action', [$user->id, 'ban']), ['reason' => 'Chargeback abuse'])
            ->assertRedirect();

        $user->refresh();
        $this->assertTrue($user->is_banned);
        $this->assertSame('banned', $user->status?->value);
        $this->assertSame(0, $user->tokens()->count());

        $this->actingAs($this->admin('moderator'), 'admin')
            ->post(route('admin.users.action', [$user->id, 'unban']))
            ->assertRedirect();

        $user->refresh();
        $this->assertFalse($user->is_banned);
        $this->assertSame('active', $user->status?->value);
    }

    public function test_only_super_admins_and_admins_can_reset_passwords_verify_emails_or_delete(): void
    {
        $user = User::factory()->unverified()->create();

        foreach (['reset-password', 'delete'] as $action) {
            $this->actingAs($this->admin('moderator'), 'admin')
                ->post(route('admin.users.action', [$user->id, $action]))
                ->assertForbidden();
        }

        $this->actingAs($this->admin('moderator'), 'admin')
            ->post(route('admin.users.action', [$user->id, 'verify-email']))
            ->assertForbidden();

        $this->actingAs($this->admin('admin'), 'admin')
            ->post(route('admin.users.action', [$user->id, 'verify-email']))
            ->assertRedirect();

        $this->assertNotNull($user->refresh()->email_verified_at);

        $this->actingAs($this->admin('admin'), 'admin')
            ->post(route('admin.users.action', [$user->id, 'reset-password']))
            ->assertRedirect()
            ->assertSessionHas('temporary_password');

        $this->actingAs($this->admin('admin'), 'admin')
            ->post(route('admin.users.action', [$user->id, 'delete']))
            ->assertRedirect(route('admin.users.show', $user->id));

        $this->assertTrue($user->refresh()->trashed());
        $this->assertDatabaseHas('audit_logs', ['action' => 'user.deleted_by_admin']);

        $this->actingAs($this->admin('admin'), 'admin')
            ->post(route('admin.users.action', [$user->id, 'restore']))
            ->assertRedirect(route('admin.users.show', $user->id));

        $this->assertFalse($user->refresh()->trashed());
    }

    public function test_support_staff_can_send_an_in_app_notification(): void
    {
        $user = User::factory()->create();

        $this->actingAs($this->admin('moderator'), 'admin')
            ->post(route('admin.users.action', [$user->id, 'notify']), [
                'title' => 'Withdrawal needs a new bank account',
                'body' => 'Please add a payout method from the app so we can settle your request.',
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $notification = Notification::query()->where('user_id', $user->id)->firstOrFail();
        $this->assertSame('admin_message', $notification->type);
        $this->assertNull($notification->read_at);
        $this->assertDatabaseHas('audit_logs', ['action' => 'user.notified']);
    }

    public function test_device_tokens_can_be_revoked_from_the_notifications_tab(): void
    {
        $user = User::factory()->create();
        $device = $user->devices()->create(['platform' => 'android', 'push_token' => 'token-123', 'token_hash' => hash('sha256', 'token-123')]);

        $this->actingAs($this->admin('admin'), 'admin')
            ->post(route('admin.users.action', [$user->id, 'revoke-device']), ['device_id' => $device->id])
            ->assertRedirect();

        $this->assertNotNull($device->refresh()->revoked_at);
    }

    private function admin(string $role): Admin
    {
        return Admin::query()->create([
            'full_name' => ucfirst($role).' Admin',
            'email' => $role.'-'.Str::lower(Str::random(6)).'@tlstore.test',
            'password' => 'StrongPassword!2345',
            'role' => $role,
            'status' => 'active',
        ]);
    }
}
