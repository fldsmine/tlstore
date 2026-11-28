<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\AdminImpersonationLink;
use App\Models\AuditLog;
use App\Models\Country;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Admin console and Flutter admin screens → "Impersonate": a single-use,
 * short-lived hand-off that signs the Flutter web app in as a user.
 */
class AdminImpersonationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        foreach ([['NG', 'Nigeria', 'NGN'], ['US', 'United States', 'USD']] as [$code, $name, $currency]) {
            Country::query()->firstOrCreate(['code' => $code], [
                'name' => $name,
                'default_currency' => $currency,
                'is_available' => true,
            ]);
        }
    }

    public function test_only_super_admins_can_request_an_impersonation_link_on_the_console(): void
    {
        $user = User::factory()->create();

        foreach (['admin', 'moderator', 'finance', 'content_admin'] as $role) {
            $this->actingAs($this->admin($role), 'admin')
                ->post(route('admin.users.impersonate', $user->id))
                ->assertForbidden();
        }

        $this->assertSame(0, AdminImpersonationLink::query()->count());
    }

    public function test_the_account_page_offers_the_button_to_super_admins_only(): void
    {
        $user = User::factory()->create();

        $this->actingAs($this->admin('super_admin'), 'admin')
            ->get(route('admin.users.show', $user->id))
            ->assertOk()
            ->assertSee('Impersonate')
            ->assertSee(route('admin.users.impersonate', $user->id), false);

        $this->actingAs($this->admin('admin'), 'admin')
            ->get(route('admin.users.show', $user->id))
            ->assertOk()
            ->assertDontSee('Impersonate');
    }

    public function test_the_console_redirects_to_the_web_app_with_a_single_use_code(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($this->admin('super_admin'), 'admin')
            ->post(route('admin.users.impersonate', $user->id));

        $response->assertRedirect();
        $location = (string) $response->headers->get('Location');
        $this->assertStringContainsString('impersonation=', $location);

        parse_str((string) parse_url($location, PHP_URL_QUERY), $query);
        $code = (string) ($query['impersonation'] ?? '');
        $this->assertNotEmpty($code);

        $link = AdminImpersonationLink::query()->sole();
        $this->assertSame($user->id, $link->user_id);
        $this->assertNull($link->used_at);
        $this->assertNotSame($code, $link->code_hash);
        $this->assertSame(hash('sha256', $code), $link->code_hash);
        $this->assertTrue($link->expires_at->isFuture());
        $this->assertTrue($link->expires_at->lessThanOrEqualTo(now()->addSeconds(130)));
    }

    public function test_the_code_signs_the_web_app_in_once_and_the_token_works(): void
    {
        $user = User::factory()->create();
        $code = $this->requestCode($user);

        $response = $this->postJson(route('api.v1.auth.impersonation'), ['code' => $code]);
        $response->assertOk()
            ->assertJsonPath('data.id', $user->id)
            ->assertJsonStructure(['data' => ['id', 'username'], 'token', 'token_type', 'expires_at', 'impersonation' => ['admin' => ['id', 'full_name', 'email'], 'expires_at']]);

        $token = (string) $response->json('token');
        $this->assertNotEmpty($token);

        // The token is a normal user session: `me` returns the impersonated account.
        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson(route('api.v1.me'))
            ->assertOk()
            ->assertJsonPath('data.id', $user->id);

        // Single use: the same code cannot be replayed.
        $this->postJson(route('api.v1.auth.impersonation'), ['code' => $code])
            ->assertStatus(422);

        $link = AdminImpersonationLink::query()->sole();
        $this->assertNotNull($link->used_at);
        $this->assertNotNull($link->personal_access_token_id);
    }

    public function test_impersonated_sessions_expire(): void
    {
        config(['impersonation.session_minutes' => 30]);
        $user = User::factory()->create();
        $code = $this->requestCode($user);

        $response = $this->postJson(route('api.v1.auth.impersonation'), ['code' => $code])->assertOk();
        $token = (string) $response->json('token');
        $this->assertNotNull($response->json('expires_at'));

        $this->travel(31)->minutes();

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson(route('api.v1.me'))
            ->assertUnauthorized();
    }

    public function test_expired_links_are_rejected(): void
    {
        $user = User::factory()->create();
        $code = $this->requestCode($user);

        $this->travel(3)->minutes();

        $this->postJson(route('api.v1.auth.impersonation'), ['code' => $code])
            ->assertStatus(422)
            ->assertJsonPath('message', fn (string $message) => str_contains($message, 'expired'));
    }

    public function test_unknown_codes_are_rejected(): void
    {
        $this->postJson(route('api.v1.auth.impersonation'), ['code' => Str::random(64)])
            ->assertStatus(422);
    }

    public function test_inactive_banned_and_deleted_accounts_cannot_be_impersonated(): void
    {
        $admin = $this->admin('super_admin');

        $banned = User::factory()->create(['is_banned' => true, 'status' => 'banned']);
        $suspended = User::factory()->create(['status' => 'suspended']);
        $deleted = User::factory()->create();
        $deleted->delete();

        foreach ([$banned, $suspended, $deleted] as $account) {
            $this->actingAs($admin, 'admin')
                ->from(route('admin.users.show', $account->id))
                ->post(route('admin.users.impersonate', $account->id))
                ->assertRedirect(route('admin.users.show', $account->id))
                ->assertSessionHasErrors('impersonation');
        }

        $this->assertSame(0, AdminImpersonationLink::query()->count());
    }

    public function test_the_admin_api_returns_a_handoff_url_for_the_flutter_screens(): void
    {
        $user = User::factory()->create();

        $response = $this->withHeader('Authorization', 'Bearer '.$this->admin('super_admin')->createToken('admin-api')->plainTextToken)
            ->postJson(route('api.v1.admin.users.impersonation.store', $user->id));

        $response->assertCreated()
            ->assertJsonStructure(['data' => ['url', 'expires_at', 'link_ttl_seconds', 'session_minutes']]);
        $this->assertStringContainsString('impersonation=', (string) $response->json('data.url'));
    }

    public function test_the_admin_api_refuses_roles_other_than_super_admin(): void
    {
        $user = User::factory()->create();

        $this->withHeader('Authorization', 'Bearer '.$this->admin('admin')->createToken('admin-api')->plainTextToken)
            ->postJson(route('api.v1.admin.users.impersonation.store', $user->id))
            ->assertForbidden();
    }

    public function test_both_steps_are_audited_against_the_administrator(): void
    {
        $admin = $this->admin('super_admin');
        $user = User::factory()->create();

        $code = $this->requestCode($user, $admin);
        $this->postJson(route('api.v1.auth.impersonation'), ['code' => $code])->assertOk();

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'user.impersonation_link_issued',
            'entity_type' => 'User',
            'entity_id' => (string) $user->id,
            'actor_admin_id' => $admin->id,
        ]);
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'user.impersonation_session_started',
            'entity_type' => 'User',
            'entity_id' => (string) $user->id,
            'actor_admin_id' => $admin->id,
        ]);
        $this->assertSame(2, AuditLog::query()->whereIn('action', [
            'user.impersonation_link_issued', 'user.impersonation_session_started',
        ])->count());
    }

    public function test_impersonation_can_be_switched_off(): void
    {
        config(['impersonation.enabled' => false]);
        $user = User::factory()->create();

        $this->actingAs($this->admin('super_admin'), 'admin')
            ->post(route('admin.users.impersonate', $user->id))
            ->assertSessionHasErrors('impersonation');

        $this->postJson(route('api.v1.auth.impersonation'), ['code' => Str::random(64)])
            ->assertStatus(422)
            ->assertJsonPath('message', fn (string $message) => str_contains($message, 'disabled'));
    }

    /**
     * Request a hand-off the way the console button does and return the plain
     * code from the redirect — it is never stored anywhere else.
     */
    private function requestCode(User $user, ?Admin $admin = null): string
    {
        $response = $this->actingAs($admin ?? $this->admin('super_admin'), 'admin')
            ->post(route('admin.users.impersonate', $user->id));

        $response->assertRedirect();
        $location = (string) $response->headers->get('Location');
        parse_str((string) parse_url($location, PHP_URL_QUERY), $query);
        $code = (string) ($query['impersonation'] ?? '');
        $this->assertNotEmpty($code, 'The console redirect did not carry an impersonation code.');

        return $code;
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
