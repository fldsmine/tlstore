<?php

namespace Tests\Feature;

use App\Application\Settings\EnvironmentFile;
use App\Models\Admin;
use App\Models\AuditLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Admin console → Settings → API keys & secrets: the tab that writes provider
 * credentials straight to the deployment's .env file.
 */
class EnvironmentSettingsAdminTest extends TestCase
{
    use RefreshDatabase;

    private string $envPath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->envPath = sys_get_temp_dir().'/tlstore-env-'.Str::lower(Str::random(10)).'.env';
        file_put_contents($this->envPath, $this->fixture());

        // Never touch the real .env from a test run.
        $this->app->instance(EnvironmentFile::class, new EnvironmentFile($this->envPath));
    }

    protected function tearDown(): void
    {
        @unlink($this->envPath);
        @unlink($this->envPath.'.bak');

        parent::tearDown();
    }

    public function test_the_tab_requires_an_admin_session(): void
    {
        $this->get(route('admin.settings.index', ['tab' => 'environment']))
            ->assertRedirect(route('admin.login'));
    }

    public function test_only_super_admins_can_open_and_save_credentials(): void
    {
        $before = md5_file($this->envPath);

        foreach (['admin', 'moderator', 'finance', 'content_admin'] as $role) {
            $this->actingAs($this->admin($role), 'admin')
                ->get(route('admin.settings.index', ['tab' => 'environment']))
                ->assertForbidden();

            $this->actingAs($this->admin($role), 'admin')
                ->patch(route('admin.settings.environment.update'), [
                    'values' => ['KORAPAY_SECRET_KEY' => 'sk_live_attempt'],
                ])
                ->assertForbidden();
        }

        $this->assertSame($before, md5_file($this->envPath));
        $this->assertStringNotContainsString('sk_live_attempt', $this->contents());
    }

    public function test_the_tab_lists_providers_with_status_and_never_renders_secrets(): void
    {
        $this->actingAs($this->admin('super_admin'), 'admin')
            ->get(route('admin.settings.index', ['tab' => 'environment']))
            ->assertOk()
            ->assertSee('API keys &amp; secrets', false)
            ->assertSee('Provider credentials')
            ->assertSee('Korapay')
            ->assertSee('Flutterwave')
            ->assertSee('VTpass')
            ->assertSee('Firebase Cloud Messaging')
            ->assertSee('Anthropic')
            ->assertSee('App links')
            ->assertSee('Kept on the server')
            // The user's example: the service-account *path* stays on the server.
            ->assertSee('FCM_SERVICE_ACCOUNT_PATH')
            ->assertDontSee('name="values[FCM_SERVICE_ACCOUNT_PATH]"', false)
            // Stored secret values never reach the browser.
            ->assertDontSee('korapay-secret-value')
            ->assertDontSee('mailgun-password-value')
            // An empty/placeholder secret is reported as not set, not as a key.
            ->assertSee('Not set');
    }

    public function test_a_new_secret_is_written_in_place_and_everything_else_is_kept(): void
    {
        $admin = $this->admin('super_admin');

        $this->actingAs($admin, 'admin')
            ->patch(route('admin.settings.environment.update'), [
                'values' => [
                    'KORAPAY_SECRET_KEY' => 'sk_live_replaced',
                    'OPENAI_API_KEY' => 'sk-openai-new',
                ],
            ])
            ->assertRedirect(route('admin.settings.index', ['tab' => 'environment']))
            ->assertSessionHasNoErrors()
            ->assertSessionHas('success', 'Saved 2 variables. The new values apply on the next request; queue workers were restarted.');

        $contents = $this->contents();

        $this->assertStringContainsString('KORAPAY_SECRET_KEY=sk_live_replaced', $contents);
        // Replaced in place, never duplicated.
        $this->assertSame(1, substr_count($contents, 'KORAPAY_SECRET_KEY='));
        // Missing keys are appended.
        $this->assertStringContainsString('OPENAI_API_KEY=sk-openai-new', $contents);
        // Untouched lines, comments and ordering survive.
        $this->assertStringContainsString("# Provider credentials\n", $contents);
        $this->assertStringContainsString('APP_NAME=TLStore', $contents);
        $this->assertStringContainsString('UNRELATED_SETTING="keep me"', $contents);
        $this->assertLessThan(
            strpos($contents, 'KORAPAY_SECRET_KEY='),
            strpos($contents, 'APP_NAME=TLStore'),
        );

        // A backup of the previous file is kept next to it.
        $this->assertFileExists($this->envPath.'.bak');
        $this->assertStringContainsString('korapay-secret-value', (string) file_get_contents($this->envPath.'.bak'));

        // Audit rows never carry the secret itself.
        $rows = AuditLog::query()->where('action', 'environment.updated')->orderBy('id')->get();
        $this->assertCount(2, $rows);
        $this->assertSame('environment', $rows[0]->entity_type);
        $this->assertSame('KORAPAY_SECRET_KEY', $rows[0]->entity_id);
        $this->assertSame('[redacted]', $rows[0]->after_values['value']);
        $this->assertStringNotContainsString('sk_live_replaced', json_encode($rows->toArray()));
        $this->assertSame($admin->id, $rows[0]->actor_admin_id);
    }

    public function test_a_blank_secret_field_keeps_the_stored_value(): void
    {
        $this->actingAs($this->admin('super_admin'), 'admin')
            ->patch(route('admin.settings.environment.update'), [
                'values' => [
                    'KORAPAY_SECRET_KEY' => '',
                    'MAIL_SMTP_SECRET_PLACEHOLDER' => '',
                ],
            ])
            ->assertSessionHas('success', 'Nothing changed — every submitted value already matched.');

        $this->assertStringContainsString('KORAPAY_SECRET_KEY=korapay-secret-value', $this->contents());
        $this->assertSame(0, AuditLog::query()->count());
    }

    public function test_a_secret_can_be_removed_explicitly(): void
    {
        $this->actingAs($this->admin('super_admin'), 'admin')
            ->patch(route('admin.settings.environment.update'), [
                'clear' => ['KORAPAY_SECRET_KEY' => '1', 'MAILGUN_SMTP_PASSWORD' => '1'],
            ])
            ->assertSessionHasNoErrors();

        $contents = $this->contents();
        $this->assertStringNotContainsString('KORAPAY_SECRET_KEY', $contents);
        $this->assertStringNotContainsString('MAILGUN_SMTP_PASSWORD', $contents);
        $this->assertStringContainsString('KORAPAY_PUBLIC_KEY=pk_test', $contents);
    }

    public function test_plain_values_are_written_normalised_and_removed_when_empty(): void
    {
        $this->actingAs($this->admin('super_admin'), 'admin')
            ->patch(route('admin.settings.environment.update'), [
                'values' => [
                    'KORAPAY_BASE_URL' => 'https://sandbox.korapay.test/v1',
                    'VTPASS_TIMEOUT' => '45',
                    'ANDROID_SHA256_CERT_FINGERPRINTS' => ' ab:cd:ef , 12:34 ',
                    'FINCRA_FEE_BEARER' => 'customer',
                    'SUPPORT_EMAIL' => 'help@tlstore.test',
                ],
            ])
            ->assertSessionHasNoErrors();

        $contents = $this->contents();
        $this->assertStringContainsString('KORAPAY_BASE_URL=https://sandbox.korapay.test/v1', $contents);
        $this->assertStringContainsString('VTPASS_TIMEOUT=45', $contents);
        $this->assertStringContainsString('ANDROID_SHA256_CERT_FINGERPRINTS=AB:CD:EF,12:34', $contents);
        $this->assertStringContainsString('FINCRA_FEE_BEARER=customer', $contents);
        $this->assertStringContainsString('SUPPORT_EMAIL=help@tlstore.test', $contents);

        // Emptying a text field removes the variable (the app falls back to its default).
        $this->actingAs($this->admin('super_admin'), 'admin')
            ->patch(route('admin.settings.environment.update'), [
                'values' => ['SUPPORT_EMAIL' => ''],
            ])
            ->assertSessionHasNoErrors();

        $this->assertStringNotContainsString('SUPPORT_EMAIL=', $this->contents());
    }

    public function test_defaults_are_not_written_when_they_match(): void
    {
        $before = $this->contents();

        $this->actingAs($this->admin('super_admin'), 'admin')
            ->patch(route('admin.settings.environment.update'), [
                'values' => [
                    'KORAPAY_BASE_URL' => 'https://api.korapay.com/merchant/api/v1',
                    'FCM_ENABLED' => 'true',
                    'AI_LISTINGS_TIMEOUT' => '180',
                ],
            ])
            ->assertSessionHas('success', 'Nothing changed — every submitted value already matched.');

        $this->assertSame($before, $this->contents());
        $this->assertSame(0, AuditLog::query()->count());
    }

    public function test_invalid_values_are_rejected_and_the_file_is_untouched(): void
    {
        $before = md5_file($this->envPath);

        $this->actingAs($this->admin('super_admin'), 'admin')
            ->from(route('admin.settings.index', ['tab' => 'environment']))
            ->patch(route('admin.settings.environment.update'), [
                'values' => [
                    'KORAPAY_BASE_URL' => 'not-a-url',
                    'SUPPORT_EMAIL' => 'not-an-email',
                    'VTPASS_TIMEOUT' => 'ten',
                    'FINCRA_FEE_BEARER' => 'somebody',
                ],
            ])
            ->assertRedirect(route('admin.settings.index', ['tab' => 'environment']))
            ->assertSessionHasErrors([
                'values.KORAPAY_BASE_URL',
                'values.SUPPORT_EMAIL',
                'values.VTPASS_TIMEOUT',
                'values.FINCRA_FEE_BEARER',
            ]);

        $this->assertSame($before, md5_file($this->envPath));
    }

    public function test_keys_outside_the_schema_are_never_written(): void
    {
        $before = $this->contents();

        $this->actingAs($this->admin('super_admin'), 'admin')
            ->patch(route('admin.settings.environment.update'), [
                'values' => [
                    'DATABASE_PASSWORD' => 'hunter2',
                    'APP_KEY' => 'base64:evil',
                    'FCM_SERVICE_ACCOUNT_PATH' => '/tmp/anything.json',
                ],
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame($before, $this->contents());
        $this->assertSame(0, AuditLog::query()->count());
    }

    public function test_a_missing_env_file_is_reported_instead_of_created(): void
    {
        $missing = $this->envPath.'.missing';
        $this->app->instance(EnvironmentFile::class, new EnvironmentFile($missing));

        $admin = $this->admin('super_admin');

        $this->actingAs($admin, 'admin')
            ->get(route('admin.settings.index', ['tab' => 'environment']))
            ->assertOk()
            ->assertSee('No .env file was found')
            ->assertDontSee('name="values[KORAPAY_SECRET_KEY]"', false);

        $this->actingAs($admin, 'admin')
            ->patch(route('admin.settings.environment.update'), [
                'values' => ['KORAPAY_SECRET_KEY' => 'sk_live_nope'],
            ])
            ->assertSessionHasErrors('environment');

        $this->assertFileDoesNotExist($missing);
    }

    public function test_the_tab_is_part_of_the_settings_console(): void
    {
        $this->actingAs($this->admin('super_admin'), 'admin')
            ->get(route('admin.settings.index'))
            ->assertOk()
            ->assertSee('API keys &amp; secrets', false)
            ->assertSee(route('admin.settings.index', ['tab' => 'environment']), false);
    }

    private function fixture(): string
    {
        return <<<'ENV'
        APP_NAME=TLStore
        APP_KEY=base64:local-test-key
        SUPPORT_EMAIL=support@example.com

        # Provider credentials
        KORAPAY_SECRET_KEY=korapay-secret-value
        KORAPAY_PUBLIC_KEY=pk_test
        MAILGUN_SMTP_PASSWORD=mailgun-password-value
        MAIL_PASSWORD=null

        UNRELATED_SETTING="keep me"

        ENV;
    }

    private function contents(): string
    {
        return (string) file_get_contents($this->envPath);
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
