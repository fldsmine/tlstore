<?php

namespace Tests\Feature;

use App\Application\Telegram\TelegramSettings;
use App\Models\Admin;
use App\Models\AuditLog;
use App\Models\TelegramMessage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Admin console → Telegram messaging: configuration (encrypted token, chat id,
 * switches), diagnostics, the announcement composer (text + image), the
 * preview/formatting pipeline and the delivery history.
 */
class TelegramAdminTest extends TestCase
{
    use RefreshDatabase;

    private const TOKEN = '123456789:AAHtest-token-value-1234567890';

    private const CHAT = '-1001234567890';

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        Http::preventStrayRequests();
    }

    public function test_the_section_requires_an_admin_session(): void
    {
        $this->get(route('admin.telegram.index'))->assertRedirect(route('admin.login'));
    }

    public function test_only_super_admins_and_admins_can_open_it(): void
    {
        $this->configureTelegram();

        foreach (['super_admin', 'admin'] as $role) {
            $this->actingAs($this->admin($role), 'admin')
                ->get(route('admin.telegram.index'))
                ->assertOk()
                ->assertSee('Telegram messaging')
                ->assertSee(self::CHAT);
        }

        foreach (['moderator', 'finance', 'content_admin'] as $role) {
            $this->actingAs($this->admin($role), 'admin')
                ->get(route('admin.telegram.index'))
                ->assertForbidden();
        }
    }

    public function test_saving_settings_encrypts_the_token_and_keeps_it_out_of_the_page(): void
    {
        $admin = $this->admin('super_admin');

        $this->actingAs($admin, 'admin')
            ->patch(route('admin.telegram.settings'), [
                'bot_token' => self::TOKEN,
                'chat_id' => self::CHAT,
                'enabled' => '1',
                'announcements_enabled' => '1',
                'withdrawals_enabled' => '1',
                'status_updates_enabled' => '1',
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $raw = \App\Models\SystemSetting::query()->where('setting_key', TelegramSettings::TOKEN)->firstOrFail()->value;

        $this->assertIsString($raw);
        $this->assertStringNotContainsString(self::TOKEN, $raw);
        $this->assertSame(self::TOKEN, Crypt::decryptString($raw));

        $page = $this->actingAs($admin, 'admin')->get(route('admin.telegram.index'))->assertOk();
        $page->assertDontSee(self::TOKEN);
        $page->assertDontSee('AAHtest-token-value-1234567890');

        $settings = app(TelegramSettings::class);
        $this->assertSame(self::CHAT, $settings->chatId());
        $this->assertTrue($settings->enabled());
        $this->assertTrue($settings->announcementsEnabled());
        $this->assertTrue($settings->withdrawalNotificationsEnabled());
        $this->assertTrue($settings->withdrawalStatusUpdatesEnabled());
        $this->assertSame('123456789:••••••7890', $settings->maskedToken());

        $audit = AuditLog::query()->where('action', 'telegram.settings_updated')->firstOrFail();
        $this->assertStringNotContainsString(self::TOKEN, json_encode($audit->toArray()));
    }

    public function test_saving_a_blank_token_keeps_the_existing_one(): void
    {
        $this->configureTelegram();
        $admin = $this->admin('super_admin');

        $this->actingAs($admin, 'admin')
            ->patch(route('admin.telegram.settings'), ['bot_token' => '', 'chat_id' => self::CHAT])
            ->assertSessionHasNoErrors();

        $this->assertSame(self::TOKEN, app(TelegramSettings::class)->token());
    }

    public function test_invalid_credentials_are_rejected_with_helpful_copy(): void
    {
        $this->actingAs($this->admin('super_admin'), 'admin')
            ->patch(route('admin.telegram.settings'), ['bot_token' => 'not-a-token', 'chat_id' => 'somewhere'])
            ->assertSessionHasErrors(['bot_token', 'chat_id']);
    }

    public function test_switching_notifications_off_is_respected(): void
    {
        $this->configureTelegram();
        $admin = $this->admin('super_admin');

        $this->actingAs($admin, 'admin')
            ->patch(route('admin.telegram.settings'), ['chat_id' => self::CHAT])
            ->assertSessionHasNoErrors();

        $settings = app(TelegramSettings::class);
        $this->assertFalse($settings->enabled());
        $this->assertFalse($settings->announcementsEnabled());
        $this->assertFalse($settings->withdrawalNotificationsEnabled());
    }

    public function test_test_connection_reports_the_bot_and_chat(): void
    {
        $this->configureTelegram();
        Http::fake([
            'api.telegram.org/bot*/getMe' => Http::response(['ok' => true, 'result' => [
                'id' => 123456789, 'is_bot' => true, 'first_name' => 'TL Store', 'username' => 'tlstore_ops_bot',
            ]]),
            'api.telegram.org/bot*/getChat' => Http::response(['ok' => true, 'result' => [
                'id' => -1001234567890, 'title' => 'TL Store Ops', 'type' => 'supergroup',
            ]]),
        ]);

        $this->actingAs($this->admin('super_admin'), 'admin')
            ->post(route('admin.telegram.test-connection'))
            ->assertRedirect()
            ->assertSessionHas('success', 'Connected to TL Store Ops (supergroup) as @tlstore_ops_bot.');

        $this->assertStringContainsString('TL Store Ops', (string) app(TelegramSettings::class)->lastCheck()['summary']);
    }

    public function test_test_connection_surfaces_a_rejected_token(): void
    {
        $this->configureTelegram();
        Http::fake([
            'api.telegram.org/*' => Http::response(['ok' => false, 'error_code' => 401, 'description' => 'Unauthorized'], 401),
        ]);

        $this->actingAs($this->admin('super_admin'), 'admin')
            ->post(route('admin.telegram.test-connection'))
            ->assertSessionHasErrors('telegram');

        $this->assertStringContainsString('Unauthorized', session('errors')->first('telegram'));
    }

    public function test_test_message_is_delivered_and_recorded(): void
    {
        $this->configureTelegram();
        Http::fake(['api.telegram.org/*' => Http::response(['ok' => true, 'result' => ['message_id' => 4242]])]);

        $this->actingAs($this->admin('super_admin'), 'admin')
            ->post(route('admin.telegram.test-message'))
            ->assertRedirect()
            ->assertSessionHas('success');

        $message = TelegramMessage::query()->where('kind', TelegramMessage::KIND_TEST)->firstOrFail();
        $this->assertSame(TelegramMessage::STATUS_SENT, $message->status);
        $this->assertSame(4242, $message->telegram_message_id);
        $this->assertNotNull($message->sent_at);

        Http::assertSent(function ($request) {
            return str_ends_with($request->url(), '/sendMessage')
                && $request['chat_id'] === self::CHAT
                && $request['parse_mode'] === 'HTML'
                && str_contains($request['text'], 'Telegram integration is working');
        });
    }

    public function test_text_only_announcement_is_sent_and_listed_in_the_history(): void
    {
        $this->configureTelegram();
        Http::fake(['api.telegram.org/*' => Http::response(['ok' => true, 'result' => ['message_id' => 501]])]);
        $admin = $this->admin('super_admin');

        $this->actingAs($admin, 'admin')
            ->post(route('admin.telegram.send'), [
                'message' => "🎉 <b>Payout window open</b>\nWithdrawals are processed within 24 hours.",
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $message = TelegramMessage::query()->where('kind', TelegramMessage::KIND_ANNOUNCEMENT)->firstOrFail();
        $this->assertSame(TelegramMessage::STATUS_SENT, $message->status);
        $this->assertSame($admin->id, $message->sent_by_admin_id);
        $this->assertStringContainsString('<b>Payout window open</b>', (string) $message->body);

        Http::assertSent(fn ($request) => str_ends_with($request->url(), '/sendMessage')
            && str_contains($request['text'], '<b>Payout window open</b>'));

        $this->actingAs($admin, 'admin')
            ->get(route('admin.telegram.index'))
            ->assertOk()
            ->assertSee('Payout window open')
            ->assertSee('Announcement');
    }

    public function test_announcement_with_an_image_uploads_it_and_uses_send_photo(): void
    {
        $this->configureTelegram();
        $sent = [];
        // The multipart body can only be read while the request is still being
        // recorded, so capture it inside the stub.
        Http::fake(function ($request) use (&$sent) {
            $sent[] = ['url' => $request->url(), 'body' => (string) $request->body()];

            return Http::response(['ok' => true, 'result' => ['message_id' => 777]]);
        });

        $this->actingAs($this->admin('super_admin'), 'admin')
            ->post(route('admin.telegram.send'), [
                'message' => '<b>New payout window</b> — see the poster.',
                'image' => UploadedFile::fake()->image('poster.jpg', 800, 600),
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $message = TelegramMessage::query()->where('kind', TelegramMessage::KIND_ANNOUNCEMENT)->firstOrFail();
        $this->assertSame('photo', $message->attachment_type);
        $this->assertNotNull($message->attachment_path);
        Storage::disk('local')->assertExists((string) $message->attachment_path);

        $this->assertCount(1, $sent);
        $this->assertStringEndsWith('/sendPhoto', $sent[0]['url']);
        $this->assertStringContainsString('name="photo"', $sent[0]['body']);
        $this->assertStringContainsString('<b>New payout window</b>', $sent[0]['body']);
        $this->assertStringContainsString(self::CHAT, $sent[0]['body']);
        $this->assertStringContainsString('parse_mode', $sent[0]['body']);
    }

    public function test_unsupported_markup_is_stripped_before_sending(): void
    {
        $this->configureTelegram();
        Http::fake(['api.telegram.org/*' => Http::response(['ok' => true, 'result' => ['message_id' => 9]])]);

        $this->actingAs($this->admin('super_admin'), 'admin')
            ->post(route('admin.telegram.send'), [
                'message' => '<script>alert(1)</script><marquee><b>Hello</b> <a href="javascript:alert(2)">bad</a> <a href="https://tlstore.org">good</a></marquee>',
            ])
            ->assertSessionHasNoErrors();

        $body = (string) TelegramMessage::query()->latest('id')->firstOrFail()->body;

        $this->assertStringNotContainsString('<script', $body);
        $this->assertStringNotContainsString('<marquee', $body);
        $this->assertStringNotContainsString('javascript:', $body);
        $this->assertStringContainsString('<b>Hello</b>', $body);
        $this->assertStringContainsString('<a href="https://tlstore.org">good</a>', $body);
        $this->assertStringNotContainsString('alert(2)', $body);
    }

    public function test_an_image_caption_must_fit_telegrams_limit(): void
    {
        $this->configureTelegram();

        $this->actingAs($this->admin('super_admin'), 'admin')
            ->post(route('admin.telegram.send'), [
                'message' => str_repeat('a', 1200),
                'image' => UploadedFile::fake()->image('poster.png'),
            ])
            ->assertSessionHasErrors('message');

        $this->assertSame(0, TelegramMessage::query()->count());
    }

    public function test_a_rejected_message_is_saved_as_failed_and_can_be_re_sent(): void
    {
        $this->configureTelegram();
        $reject = true;
        Http::fake(function () use (&$reject) {
            return $reject
                ? Http::response(['ok' => false, 'description' => 'Bad Request: chat not found'], 400)
                : Http::response(['ok' => true, 'result' => ['message_id' => 9001]]);
        });
        $admin = $this->admin('super_admin');

        $this->actingAs($admin, 'admin')
            ->post(route('admin.telegram.send'), ['message' => 'Hello world'])
            ->assertSessionHasErrors('telegram');

        $message = TelegramMessage::query()->latest('id')->firstOrFail();
        $this->assertSame(TelegramMessage::STATUS_FAILED, $message->status);
        $this->assertStringContainsString('chat not found', (string) $message->error);

        $reject = false;

        $this->actingAs($admin, 'admin')
            ->post(route('admin.telegram.resend', $message))
            ->assertSessionHas('success');

        $message->refresh();
        $this->assertSame(TelegramMessage::STATUS_SENT, $message->status);
        $this->assertSame(9001, $message->telegram_message_id);
        $this->assertNull($message->error);
    }

    public function test_announcements_are_blocked_when_the_switches_are_off(): void
    {
        $this->configureTelegram(['announcements' => false]);
        Http::fake();

        $this->actingAs($this->admin('super_admin'), 'admin')
            ->post(route('admin.telegram.send'), ['message' => 'Should not go out'])
            ->assertSessionHasErrors('telegram');

        Http::assertNothingSent();
        $this->assertSame(0, TelegramMessage::query()->count());
    }

    public function test_uploaded_images_are_only_readable_by_administrators(): void
    {
        $this->configureTelegram();
        Http::fake(['api.telegram.org/*' => Http::response(['ok' => true, 'result' => ['message_id' => 5]])]);

        $this->actingAs($this->admin('super_admin'), 'admin')
            ->post(route('admin.telegram.send'), [
                'message' => 'Poster',
                'image' => UploadedFile::fake()->image('poster.jpg', 120, 120),
            ]);

        $message = TelegramMessage::query()->latest('id')->firstOrFail();

        $this->actingAs($this->admin('admin'), 'admin')
            ->get(route('admin.telegram.image', $message))
            ->assertOk();

        $this->actingAs($this->admin('moderator'), 'admin')
            ->get(route('admin.telegram.image', $message))
            ->assertForbidden();

        $this->actingAs($this->admin('super_admin'), 'admin')
            ->get(route('admin.telegram.image', TelegramMessage::query()->create([
                'kind' => TelegramMessage::KIND_TEST,
                'chat_id' => self::CHAT,
                'body' => 'no file',
            ])))
            ->assertNotFound();
    }

    public function test_the_admin_settings_api_never_returns_telegram_credentials(): void
    {
        $this->configureTelegram();
        \Laravel\Sanctum\Sanctum::actingAs($this->admin('super_admin'));

        $response = $this->getJson('/api/v1/admin/settings')->assertOk();

        $response->assertDontSee(self::TOKEN);
        $keys = collect($response->json('data'))->pluck('setting_key');
        $this->assertFalse($keys->contains(fn ($key) => str_starts_with((string) $key, 'telegram.')));
    }

    /** @param array{enabled?: bool, announcements?: bool, withdrawals?: bool, status_updates?: bool} $overrides */
    private function configureTelegram(array $overrides = []): void
    {
        $admin = $this->admin('super_admin');
        $settings = app(TelegramSettings::class);
        $settings->saveToken(self::TOKEN, $admin->id);
        $settings->saveChatId(self::CHAT, $admin->id);
        $settings->saveFlag(TelegramSettings::ENABLED, $overrides['enabled'] ?? true, $admin->id);
        $settings->saveFlag(TelegramSettings::ANNOUNCEMENTS, $overrides['announcements'] ?? true, $admin->id);
        $settings->saveFlag(TelegramSettings::WITHDRAWALS, $overrides['withdrawals'] ?? true, $admin->id);
        $settings->saveFlag(TelegramSettings::STATUS_UPDATES, $overrides['status_updates'] ?? true, $admin->id);
        $settings->forgetCachedValues();
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
