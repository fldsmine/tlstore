<?php

namespace Tests\Feature;

use App\Application\Support\PaymentGatewaySettings;
use App\Models\Admin;
use App\Models\AuditLog;
use App\Models\SystemSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Admin console → System settings (the friendly replacement for the raw JSON
 * module editor): tab per settings group, typed controls, and writes that keep
 * every stored JSON shape intact.
 */
class SystemSettingsAdminTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_page_requires_an_admin_session(): void
    {
        $this->get(route('admin.settings.index'))->assertRedirect(route('admin.login'));
    }

    public function test_only_super_admins_can_open_and_save_settings(): void
    {
        foreach (['admin', 'moderator', 'finance', 'content_admin'] as $role) {
            $this->actingAs($this->admin($role), 'admin')
                ->get(route('admin.settings.index'))
                ->assertForbidden();

            $this->actingAs($this->admin($role), 'admin')
                ->patch(route('admin.settings.update'), ['tab' => 'general'])
                ->assertForbidden();
        }

        $this->assertNull(SystemSetting::query()->where('setting_key', 'about.brand_name')->first());
    }

    public function test_each_settings_group_is_a_tab(): void
    {
        $this->actingAs($this->admin('super_admin'), 'admin')
            ->get(route('admin.settings.index'))
            ->assertOk()
            ->assertSee('Brand &amp; about page', false)
            ->assertSee('Payments')
            ->assertSee('Rewards &amp; wallets', false)
            ->assertSee('Notifications')
            ->assertSee('Automation')
            ->assertSee('VAS')
            ->assertSee('Campaigns')
            ->assertSee('Advanced')
            ->assertSee(route('admin.settings.index', ['tab' => 'payments']), false)
            // No JSON textarea is needed for the mapped settings.
            ->assertDontSee('Advanced editor');
    }

    public function test_brand_copy_is_edited_with_plain_inputs(): void
    {
        $this->storeSetting('about.brand_name', 'TrustLens', true);
        $this->storeSetting('about.features', ['Fast payouts', 'Real reviews'], true);

        $admin = $this->admin('super_admin');

        $this->actingAs($admin, 'admin')
            ->get(route('admin.settings.index', ['tab' => 'general']))
            ->assertOk()
            ->assertSee('Brand name')
            ->assertSee('value="TrustLens"', false)
            ->assertSee("Fast payouts\nReal reviews")
            ->assertSee('Feature chips');

        $this->actingAs($admin, 'admin')
            ->patch(route('admin.settings.update'), [
                'tab' => 'general',
                'values' => [
                    'about::brand_name' => 'TL Store',
                    'about::features' => "Fast payouts\nReal reviews\nFair moderation",
                ],
                'public' => ['about::brand_name' => '1'],
            ])
            ->assertRedirect(route('admin.settings.index', ['tab' => 'general']))
            ->assertSessionHas('success', 'Saved 2 settings.');

        $brand = SystemSetting::query()->where('setting_key', 'about.brand_name')->first();
        $this->assertSame('TL Store', $brand->value);
        $this->assertTrue($brand->is_public);
        $this->assertSame($admin->id, $brand->updated_by_admin_id);

        $this->assertSame(
            ['Fast payouts', 'Real reviews', 'Fair moderation'],
            SystemSetting::query()->where('setting_key', 'about.features')->first()->value,
        );

        $this->assertSame(2, AuditLog::query()->where('action', 'system_setting.updated')->count());
    }

    public function test_untouched_fields_are_left_alone(): void
    {
        $this->storeSetting('about.version', '1.0.0', true);
        $this->storeSetting('about.footer_note', 'Built for people who make apps better.', true);

        $this->actingAs($this->admin('super_admin'), 'admin')
            ->patch(route('admin.settings.update'), [
                'tab' => 'general',
                'values' => ['about::version' => '1.0.1'],
            ])
            ->assertSessionHas('success', 'Saved 1 setting.');

        $this->assertSame('1.0.1', SystemSetting::query()->where('setting_key', 'about.version')->first()->value);
        $this->assertSame(
            'Built for people who make apps better.',
            SystemSetting::query()->where('setting_key', 'about.footer_note')->first()->value,
        );
    }

    public function test_saving_without_changes_reports_nothing_changed(): void
    {
        $this->storeSetting('about.brand_name', 'TrustLens', true);

        $this->actingAs($this->admin('super_admin'), 'admin')
            ->patch(route('admin.settings.update'), [
                'tab' => 'general',
                'values' => ['about::brand_name' => 'TrustLens'],
                'public' => ['about::brand_name' => '1'],
            ])
            ->assertSessionHas('success', 'Nothing changed — the saved values already matched.');

        $this->assertSame(0, AuditLog::query()->count());
    }

    public function test_gateway_matrix_writes_both_gateway_shapes(): void
    {
        config(['trustlens.implemented_payment_gateways' => ['manual', 'korapay']]);

        // Legacy install: only the flat gateway → currency matrix exists.
        $this->storeSetting('payment.active_gateways', ['korapay' => ['NGN']], true);
        $this->storeSetting('account_validation.provider', ['provider' => 'paystack'], false);

        $admin = $this->admin('super_admin');

        $this->actingAs($admin, 'admin')
            ->get(route('admin.settings.index', ['tab' => 'payments']))
            ->assertOk()
            ->assertSee('Gateways &amp; currencies', false)
            ->assertSee('Korapay')
            ->assertSee('Manual bank transfer')
            ->assertSee('Payout account validation');

        $this->actingAs($admin, 'admin')
            ->patch(route('admin.settings.update'), [
                'tab' => 'payments',
                'values' => [
                    'payment::active_gateways' => [
                        'manual' => '1',
                        'gateways' => [
                            'korapay' => ['currencies' => ['NGN' => '1', 'USD' => '1', 'GBP' => '0']],
                            'paystack' => ['currencies' => ['NGN' => '0']],
                        ],
                    ],
                    'account_validation::provider' => 'korapay',
                ],
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame(
            ['korapay.NGN' => 'NGN', 'korapay.USD' => 'USD'],
            SystemSetting::query()->where('setting_key', 'payment.active_payment_currency')->first()->value,
        );

        $this->assertSame(
            ['korapay' => ['NGN', 'USD'], 'manual' => []],
            SystemSetting::query()->where('setting_key', 'payment.active_gateways')->first()->value,
        );

        $this->assertSame(['provider' => 'korapay'], SystemSetting::query()->where('setting_key', 'account_validation.provider')->first()->value);

        // Consumers read the same thing back, including manual transfers.
        $this->assertSame(['korapay' => ['NGN', 'USD'], 'manual' => []], PaymentGatewaySettings::all());
    }

    public function test_gateways_without_credentials_are_saved_but_not_offered(): void
    {
        config(['trustlens.implemented_payment_gateways' => ['manual']]);

        $this->actingAs($this->admin('super_admin'), 'admin')
            ->patch(route('admin.settings.update'), [
                'tab' => 'payments',
                'values' => [
                    'payment::active_gateways' => [
                        'manual' => '0',
                        'gateways' => ['fincra' => ['currencies' => ['NGN' => '1']]],
                    ],
                    'account_validation::provider' => 'fincra',
                ],
            ])
            ->assertSessionHasNoErrors();

        // The tick is stored even though Fincra has no keys on this server, so a
        // temporary credential gap cannot wipe the payment configuration.
        $this->assertSame(['fincra' => ['NGN']], SystemSetting::query()->where('setting_key', 'payment.active_gateways')->first()->value);
        $this->assertSame(['fincra.NGN' => 'NGN'], SystemSetting::query()->where('setting_key', 'payment.active_payment_currency')->first()->value);

        // Checkout still hides it: options are filtered by configured gateways.
        $this->assertSame([], PaymentGatewaySettings::all());
    }

    public function test_existing_currency_labels_survive_a_save(): void
    {
        config(['trustlens.implemented_payment_gateways' => ['manual', 'paystack']]);

        $this->storeSetting('payment.active_gateways', ['paystack' => ['NGN']], true);
        $this->storeSetting('payment.active_payment_currency', ['paystack.NGN' => 'NGN-2'], true);
        $this->storeSetting('account_validation.provider', ['provider' => 'paystack'], false);

        $this->actingAs($this->admin('super_admin'), 'admin')
            ->patch(route('admin.settings.update'), [
                'tab' => 'payments',
                'values' => [
                    'payment::active_gateways' => [
                        'manual' => '0',
                        'gateways' => ['paystack' => ['currencies' => ['NGN' => '1']]],
                    ],
                    'account_validation::provider' => 'paystack',
                ],
            ])
            ->assertSessionHasNoErrors()
            ->assertSessionHas('success', 'Nothing changed — the saved values already matched.');

        $this->assertSame(['paystack.NGN' => 'NGN-2'], SystemSetting::query()->where('setting_key', 'payment.active_payment_currency')->first()->value);
    }

    public function test_structured_editors_keep_their_json_shape(): void
    {
        $this->storeSetting('vas.services', ['airtime' => true, 'data' => false, 'tv' => false, 'electricity' => false, 'education' => false], false);
        $this->storeSetting('about.social_links', [], true);
        $this->storeSetting('campaign.services', [
            'tiktok' => ['followers' => 300, 'min' => 100],
        ], true);
        $this->storeSetting('campaign.currency', 'NGN', true);
        $this->storeSetting('campaign.service_fee_percent', 5, true);

        $admin = $this->admin('super_admin');

        $this->actingAs($admin, 'admin')
            ->patch(route('admin.settings.update'), [
                'tab' => 'vas',
                'values' => ['vas::services' => ['airtime' => '1', 'data' => '1', 'tv' => '0', 'electricity' => '0', 'education' => '0']],
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame(
            ['airtime' => true, 'data' => true, 'tv' => false, 'electricity' => false, 'education' => false],
            SystemSetting::query()->where('setting_key', 'vas.services')->first()->value,
        );

        $this->actingAs($admin, 'admin')
            ->patch(route('admin.settings.update'), [
                'tab' => 'general',
                'values' => [
                    'about::social_links' => [
                        'names' => ['Instagram', '', '  X  '],
                        'urls' => ['https://instagram.com/tlstore', 'https://orphan.test', 'https://x.com/tlstore'],
                    ],
                ],
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame(
            ['Instagram' => 'https://instagram.com/tlstore', 'X' => 'https://x.com/tlstore'],
            SystemSetting::query()->where('setting_key', 'about.social_links')->first()->value,
        );

        $this->actingAs($admin, 'admin')
            ->patch(route('admin.settings.update'), [
                'tab' => 'campaigns',
                'values' => [
                    'campaign::services' => [
                        'rows' => [
                            ['platform' => 'tiktok', 'action' => 'followers', 'value' => '300'],
                            ['platform' => 'tiktok', 'action' => 'min', 'value' => '100'],
                            ['platform' => 'instagram', 'action' => 'likes', 'value' => '250'],
                            ['platform' => 'instagram', 'action' => 'min', 'value' => '50'],
                            ['platform' => '', 'action' => '', 'value' => ''],
                            ['platform' => 'youtube', 'action' => 'subscribers', 'value' => '900', 'remove' => '1'],
                        ],
                    ],
                    'campaign::currency' => 'ngn',
                    'campaign::service_fee_percent' => '7.5',
                ],
            ])
            ->assertSessionHasNoErrors();

        $campaign = SystemSetting::query()->where('setting_key', 'campaign.services')->first()->value;
        $this->assertSame([
            'tiktok' => ['followers' => 300, 'min' => 100],
            'instagram' => ['likes' => 250, 'min' => 50],
        ], $campaign);
        $this->assertIsInt($campaign['instagram']['likes']);
        $this->assertSame('NGN', SystemSetting::query()->where('setting_key', 'campaign.currency')->first()->value);
        $this->assertSame(7.5, SystemSetting::query()->where('setting_key', 'campaign.service_fee_percent')->first()->value);
    }

    public function test_notification_switches_and_read_only_flags(): void
    {
        $this->storeSetting('notifications.email.enabled', true, false);
        $this->storeSetting('notifications.sms.enabled', true, false);
        $this->storeSetting('notifications.email.mailer', 'smtp', false);

        $this->actingAs($this->admin('super_admin'), 'admin')
            ->patch(route('admin.settings.update'), [
                'tab' => 'notifications',
                'values' => [
                    'notifications::email::enabled' => '0',
                    'notifications::email::mailer' => 'resend',
                    'notifications::email::from_address' => 'no-reply@tlstore.test',
                    'notifications::email::from_name' => 'TLStore',
                ],
            ])
            ->assertSessionHasNoErrors();

        $this->assertFalse(SystemSetting::query()->where('setting_key', 'notifications.email.enabled')->first()->value);
        $this->assertSame('resend', SystemSetting::query()->where('setting_key', 'notifications.email.mailer')->first()->value);

        // The reserved SMS switch is rendered read-only and never submitted, so
        // its stored value survives a save untouched.
        $this->assertTrue(SystemSetting::query()->where('setting_key', 'notifications.sms.enabled')->first()->value);
    }

    public function test_a_required_choice_cannot_be_blanked(): void
    {
        $this->storeSetting('account_validation.provider', ['provider' => 'paystack'], false);

        $this->actingAs($this->admin('super_admin'), 'admin')
            ->from(route('admin.settings.index', ['tab' => 'payments']))
            ->patch(route('admin.settings.update'), [
                'tab' => 'payments',
                'values' => [
                    'payment::active_gateways' => ['gateways' => []],
                    'account_validation::provider' => '',
                ],
            ])
            ->assertRedirect(route('admin.settings.index', ['tab' => 'payments']))
            ->assertSessionHasErrors('values.account_validation::provider');

        $this->assertSame(['provider' => 'paystack'], SystemSetting::query()->where('setting_key', 'account_validation.provider')->first()->value);
    }

    public function test_the_advanced_tab_edits_unmapped_json_and_hides_secrets(): void
    {
        $this->storeSetting('custom.feature_flags', ['beta' => false], false);
        $this->storeSetting('api.secret_key', 'super-secret-value', false);

        $admin = $this->admin('super_admin');

        $this->actingAs($admin, 'admin')
            ->get(route('admin.settings.index', ['tab' => 'advanced']))
            ->assertOk()
            ->assertSee('custom.feature_flags')
            ->assertSee('Protected values')
            // The key is listed so admins know it exists, but never its value
            // and never an editable field.
            ->assertSee('api.secret_key')
            ->assertDontSee('super-secret-value')
            ->assertDontSee('name="values[api::secret_key]"', false);

        $this->actingAs($admin, 'admin')
            ->patch(route('admin.settings.update'), [
                'tab' => 'advanced',
                'values' => ['custom::feature_flags' => '{"beta":true,"limit":5}'],
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame(
            ['beta' => true, 'limit' => 5],
            SystemSetting::query()->where('setting_key', 'custom.feature_flags')->first()->value,
        );
    }

    public function test_invalid_json_is_rejected_and_nothing_is_written(): void
    {
        $this->storeSetting('custom.feature_flags', ['beta' => false], false);

        $this->actingAs($this->admin('super_admin'), 'admin')
            ->from(route('admin.settings.index', ['tab' => 'advanced']))
            ->patch(route('admin.settings.update'), [
                'tab' => 'advanced',
                'values' => ['custom::feature_flags' => '{"beta":'],
            ])
            ->assertSessionHasErrors('values.custom::feature_flags');

        $this->assertSame(['beta' => false], SystemSetting::query()->where('setting_key', 'custom.feature_flags')->first()->value);
    }

    public function test_public_payload_cache_is_cleared_on_save(): void
    {
        Cache::put('api:frontend:v3', ['stale' => true], 60);

        $this->actingAs($this->admin('super_admin'), 'admin')
            ->patch(route('admin.settings.update'), [
                'tab' => 'general',
                'values' => ['about::brand_name' => 'TrustLens'],
            ])
            ->assertSessionHasNoErrors();

        $this->assertFalse(Cache::has('api:frontend:v3'));
    }

    public function test_the_raw_module_editor_still_works_for_super_admins(): void
    {
        $this->actingAs($this->admin('super_admin'), 'admin')
            ->get(route('admin.modules.index', 'system-settings'))
            ->assertOk();
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

    private function storeSetting(string $key, mixed $value, bool $isPublic): SystemSetting
    {
        return SystemSetting::query()->create([
            'setting_key' => $key,
            'value' => $value,
            'is_public' => $isPublic,
        ]);
    }
}
