<?php

namespace Tests\Feature;

use App\Application\Actions\Moderation\SettleWithdrawal;
use App\Application\Actions\Wallets\RequestWithdrawal;
use App\Application\Telegram\TelegramSettings;
use App\Enums\WalletType;
use App\Jobs\SendWithdrawalStatusTelegramJob;
use App\Jobs\SendWithdrawalTelegramNotificationJob;
use App\Models\Admin;
use App\Models\TelegramMessage;
use App\Models\User;
use App\Models\Wallet;
use App\Models\WithdrawalOption;
use App\Models\WithdrawalRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Automatic Telegram notifications: a withdrawal request posts the generated
 * receipt, and a settled withdrawal posts its final status. Telegram failures
 * must never interrupt the withdrawal itself.
 */
class TelegramWithdrawalNotificationTest extends TestCase
{
    use RefreshDatabase;

    private const TOKEN = '987654321:AAHwithdrawal-bot-token-0987654321';

    private const CHAT = '-1009876543210';

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        Http::preventStrayRequests();
    }

    public function test_a_new_withdrawal_request_queues_the_receipt_notification(): void
    {
        Queue::fake();
        $withdrawal = $this->requestWithdrawal($this->user());

        Queue::assertPushed(SendWithdrawalTelegramNotificationJob::class, fn ($job) => $job->withdrawalId === $withdrawal->id);
        $this->assertSame(0, TelegramMessage::query()->count());
    }

    public function test_replaying_the_same_request_does_not_queue_a_second_notification(): void
    {
        Queue::fake();
        $user = $this->user();
        [$wallet, $option] = $this->walletAndOption($user);

        $action = app(RequestWithdrawal::class);
        $first = $action->handle($user, $wallet->id, $option->id, 50000, $this->payoutDetails(), 'replay-key-123');
        $second = $action->handle($user, $wallet->id, $option->id, 50000, $this->payoutDetails(), 'replay-key-123');

        $this->assertSame($first->id, $second->id);
        Queue::assertPushed(SendWithdrawalTelegramNotificationJob::class, 1);
    }

    public function test_the_notification_carries_the_details_and_the_receipt_attachment(): void
    {
        $this->configureTelegram();
        $sent = [];
        Http::fake(function ($request) use (&$sent) {
            $sent[] = ['url' => $request->url(), 'body' => (string) $request->body()];

            return Http::response(['ok' => true, 'result' => ['message_id' => 31]], 200);
        });

        $withdrawal = $this->requestWithdrawal($this->user());

        $message = TelegramMessage::query()->firstOrFail();
        $this->assertSame(TelegramMessage::KIND_WITHDRAWAL_REQUEST, $message->kind);
        $this->assertSame(TelegramMessage::STATUS_SENT, $message->status);
        $this->assertSame(31, $message->telegram_message_id);
        $this->assertSame($withdrawal->id, $message->withdrawal_request_id);
        $this->assertContains($message->attachment_type, ['photo', 'document']);
        $this->assertStringContainsString('NEW WITHDRAWAL REQUEST', (string) $message->body);
        $this->assertStringContainsString('Telegram Notify User', (string) $message->body);
        $this->assertStringContainsString('NGN 500.00', (string) $message->body);
        $this->assertStringContainsString('••••••6789', (string) $message->body);
        $this->assertStringContainsString('GTBank', (string) $message->body);

        $this->assertCount(1, $sent);
        $this->assertMatchesRegularExpression('#/(sendPhoto|sendDocument)$#', $sent[0]['url']);
        $this->assertStringContainsString('NEW WITHDRAWAL REQUEST', $sent[0]['body']);
        $this->assertStringContainsString('name="'.$message->attachment_type.'"', $sent[0]['body']);

        // The generated receipt was cleaned up after a successful delivery.
        $this->assertNull($message->fresh()->attachment_path);
        $this->assertCount(0, Storage::disk('local')->allFiles((string) config('telegram.receipt_directory')));
    }

    public function test_a_telegram_outage_never_breaks_the_withdrawal(): void
    {
        $this->configureTelegram();
        Http::fake(['api.telegram.org/*' => Http::response(['ok' => false, 'description' => 'Bad Gateway'], 502)]);

        $withdrawal = $this->requestWithdrawal($this->user());

        $this->assertDatabaseHas('withdrawal_requests', ['id' => $withdrawal->id, 'status' => 'pending']);

        $message = TelegramMessage::query()->firstOrFail();
        $this->assertSame(TelegramMessage::STATUS_FAILED, $message->status);
        $this->assertStringContainsString('Bad Gateway', (string) $message->error);
    }

    public function test_the_withdrawal_switch_turns_the_notification_off(): void
    {
        $this->configureTelegram(['withdrawals' => false]);
        Http::fake();

        $this->requestWithdrawal($this->user());

        Http::assertNothingSent();
        $this->assertSame(0, TelegramMessage::query()->count());
    }

    public function test_nothing_is_sent_when_telegram_is_not_configured(): void
    {
        Http::fake();
        $this->requestWithdrawal($this->user());

        Http::assertNothingSent();
        $this->assertSame(0, TelegramMessage::query()->count());
    }

    public function test_paid_withdrawals_announce_their_final_status(): void
    {
        $this->configureTelegram();
        $sentBodies = [];
        Http::fake(function ($request) use (&$sentBodies) {
            $sentBodies[] = (string) $request->body();

            return Http::response(['ok' => true, 'result' => ['message_id' => 61]], 200);
        });

        $withdrawal = $this->requestWithdrawal($this->user());
        app(SettleWithdrawal::class)->handle($this->admin(), $withdrawal, 'paid', 'BANK-REF-9931', null);

        $statusMessage = TelegramMessage::query()->where('kind', TelegramMessage::KIND_WITHDRAWAL_STATUS)->firstOrFail();
        $this->assertSame(TelegramMessage::STATUS_SENT, $statusMessage->status);
        $this->assertStringContainsString('WITHDRAWAL PAID', (string) $statusMessage->body);
        $this->assertStringContainsString('BANK-REF-9931', (string) $statusMessage->body);
        $this->assertStringContainsString('WITHDRAWAL PAID', implode("\n", $sentBodies));
    }

    public function test_rejected_withdrawals_include_the_reason(): void
    {
        $this->configureTelegram();
        Http::fake(['api.telegram.org/*' => Http::response(['ok' => true, 'result' => ['message_id' => 62]])]);

        $withdrawal = $this->requestWithdrawal($this->user());
        app(SettleWithdrawal::class)->handle($this->admin(), $withdrawal, 'rejected', null, 'Bank details could not be verified.');

        $statusMessage = TelegramMessage::query()->where('kind', TelegramMessage::KIND_WITHDRAWAL_STATUS)->firstOrFail();
        $this->assertStringContainsString('WITHDRAWAL REJECTED', (string) $statusMessage->body);
        $this->assertStringContainsString('Bank details could not be verified.', (string) $statusMessage->body);
    }

    public function test_status_updates_respect_their_switch(): void
    {
        $this->configureTelegram(['status_updates' => false]);
        $bodies = [];
        Http::fake(function ($request) use (&$bodies) {
            $bodies[] = (string) $request->body();

            return Http::response(['ok' => true, 'result' => ['message_id' => 70]], 200);
        });

        $withdrawal = $this->requestWithdrawal($this->user());
        app(SettleWithdrawal::class)->handle($this->admin(), $withdrawal, 'paid', 'REF-1', null);

        $this->assertSame(0, TelegramMessage::query()->where('kind', TelegramMessage::KIND_WITHDRAWAL_STATUS)->count());
        $this->assertStringNotContainsString('WITHDRAWAL PAID', implode("\n", $bodies));
    }

    public function test_a_failed_delivery_is_retried_by_the_queue_then_marked_failed(): void
    {
        $this->configureTelegram();
        Http::fake(['api.telegram.org/*' => Http::response(['ok' => false, 'description' => 'Too Many Requests: retry after 5'], 429)]);

        $withdrawal = $this->requestWithdrawal($this->user());
        $message = TelegramMessage::query()->firstOrFail();

        $job = new SendWithdrawalTelegramNotificationJob($withdrawal->id);
        try {
            $job->handle(
                app(TelegramSettings::class),
                app(\App\Application\Telegram\TelegramNotifier::class),
                app(\App\Application\Telegram\TelegramDispatcher::class),
                app(\App\Application\Receipts\WithdrawalReceiptFile::class),
            );
            $this->fail('A failed delivery must throw so the queue applies its backoff.');
        } catch (\App\Exceptions\TelegramDeliveryException $expected) {
            $this->assertStringContainsString('Too Many Requests', $expected->getMessage());
        }

        $job->failed(new \RuntimeException('Too Many Requests: retry after 5'));

        $message->refresh();
        $this->assertSame(TelegramMessage::STATUS_FAILED, $message->status);
        $this->assertNotNull($message->error);
        $this->assertDatabaseHas('withdrawal_requests', ['id' => $withdrawal->id, 'status' => 'pending']);
    }

    private function requestWithdrawal(User $user): WithdrawalRequest
    {
        [$wallet, $option] = $this->walletAndOption($user);

        return app(RequestWithdrawal::class)->handle(
            $user,
            $wallet->id,
            $option->id,
            50000,
            $this->payoutDetails(),
            'wd-'.Str::uuid(),
        );
    }

    /** @return array{0: Wallet, 1: WithdrawalOption} */
    private function walletAndOption(User $user): array
    {
        $wallet = Wallet::query()->create([
            'user_id' => $user->id,
            'wallet_type' => WalletType::Referral,
            'currency' => 'NGN',
            'status' => 'active',
        ]);
        $wallet->forceFill(['balance_minor' => 1000000])->save();

        $option = WithdrawalOption::query()->create([
            'option_id' => 'bank-transfer-'.Str::lower(Str::random(6)),
            'name' => 'Bank transfer',
            'fields' => ['account_number', 'bank_name'],
            'currency' => 'NGN',
            'tax' => 0,
            'min_withdrawal' => 0,
            'is_available' => true,
            'order' => 0,
        ]);

        return [$wallet, $option];
    }

    /** @return array{account_number: string, bank_name: string} */
    private function payoutDetails(): array
    {
        return ['account_number' => '0123456789', 'bank_name' => 'GTBank'];
    }

    /** @param array{withdrawals?: bool, status_updates?: bool} $overrides */
    private function configureTelegram(array $overrides = []): void
    {
        $admin = $this->admin();
        $settings = app(TelegramSettings::class);
        $settings->saveToken(self::TOKEN, $admin->id);
        $settings->saveChatId(self::CHAT, $admin->id);
        $settings->saveFlag(TelegramSettings::ENABLED, true, $admin->id);
        $settings->saveFlag(TelegramSettings::ANNOUNCEMENTS, true, $admin->id);
        $settings->saveFlag(TelegramSettings::WITHDRAWALS, $overrides['withdrawals'] ?? true, $admin->id);
        $settings->saveFlag(TelegramSettings::STATUS_UPDATES, $overrides['status_updates'] ?? true, $admin->id);
        $settings->forgetCachedValues();
    }

    private function user(): User
    {
        return User::query()->create([
            'full_name' => 'Telegram Notify User',
            'username' => 'tg-'.Str::lower(Str::random(8)),
            'email' => Str::lower(Str::random(8)).'@example.test',
            'preferred_currency' => 'NGN',
            'wallet_currency' => 'NGN',
            'role' => 'reviewer',
            'status' => 'active',
        ]);
    }

    private function admin(): Admin
    {
        return Admin::query()->firstOrCreate(
            ['email' => 'telegram-admin@tlstore.test'],
            [
                'full_name' => 'Telegram Admin',
                'password' => 'StrongPassword!2345',
                'role' => 'super_admin',
                'status' => 'active',
            ],
        );
    }
}
