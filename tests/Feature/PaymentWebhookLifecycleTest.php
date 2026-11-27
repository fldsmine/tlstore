<?php

namespace Tests\Feature;

use App\Models\LedgerEntry;
use App\Models\PaymentIntent;
use App\Models\PaymentTransaction;
use App\Models\PaymentWebhookEvent;
use App\Models\Transaction;
use App\Models\User;
use App\Models\Wallet;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * End-to-end cover for the gateway payment lifecycle (ERR-10 / ERR-18).
 *
 * Each test drives the real `/api/v1/webhooks/paystack` endpoint with a valid
 * HMAC signature and a faked provider verification call, then asserts that the
 * payment intent, its `payment-intent:{id}` transaction projection and the
 * wallet ledger all agree.
 */
class PaymentWebhookLifecycleTest extends TestCase
{
    use RefreshDatabase;

    private const SECRET = 'sk_test_lifecycle';

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('trustlens.payment_gateways.paystack', [
            'secret_key' => self::SECRET,
            'base_url' => 'https://api.paystack.test',
        ]);
        config()->set('trustlens.implemented_payment_gateways', ['manual', 'paystack']);
        config()->set('trustlens.payment_webhook_base_url', 'https://example.test');
        config()->set('trustlens.payment_return_url', 'https://example.test/payments/return');
    }

    public function test_successful_webhook_settles_the_intent_transaction_and_wallet(): void
    {
        $user = $this->user();
        $intent = $this->pendingIntent($user, amountMinor: 250000, currency: 'NGN');

        $this->fakeVerification(['status' => 'success', 'amount' => 250000, 'currency' => 'NGN', 'reference' => $intent->provider_reference]);
        $this->postPaystackWebhook($this->payload($intent))
            ->assertOk();

        $this->assertSame('paid', $intent->fresh()->status->value);

        $transaction = Transaction::query()->where('reference', 'payment-intent:'.$intent->id)->firstOrFail();
        $this->assertSame('completed', $transaction->status);
        $this->assertSame('top_up_fund', $transaction->type->value);
        $this->assertSame(250000, (int) $transaction->meta['amount_minor']);
        $this->assertSame('NGN', $transaction->meta['currency']);

        $wallet = Wallet::query()->where('user_id', $user->id)->where('wallet_type', 'topup_fund')->firstOrFail();
        $this->assertSame(250000, (int) $wallet->balance_minor);
        $this->assertSame('NGN', $wallet->currency);

        $this->assertSame(1, LedgerEntry::query()->count());
        $this->assertSame(1, PaymentTransaction::query()->count());
        $this->assertSame('processed', PaymentWebhookEvent::query()->firstOrFail()->processing_status);
    }

    public function test_duplicate_webhook_replay_never_double_credits(): void
    {
        $user = $this->user();
        $intent = $this->pendingIntent($user, amountMinor: 100000, currency: 'NGN');
        $this->fakeVerification(['status' => 'success', 'amount' => 100000, 'currency' => 'NGN', 'reference' => $intent->provider_reference]);

        $payload = $this->payload($intent);
        $this->postPaystackWebhook($payload)->assertOk();
        $this->postPaystackWebhook($payload)->assertOk();

        $wallet = Wallet::query()->where('user_id', $user->id)->where('wallet_type', 'topup_fund')->firstOrFail();
        $this->assertSame(100000, (int) $wallet->balance_minor);
        $this->assertSame(1, LedgerEntry::query()->count());
        $this->assertSame(1, PaymentWebhookEvent::query()->count());
    }

    public function test_failed_payment_closes_the_intent_and_its_pending_projection(): void
    {
        $user = $this->user();
        $intent = $this->pendingIntent($user, amountMinor: 50000, currency: 'NGN');

        $this->fakeVerification(['status' => 'failed', 'reference' => $intent->provider_reference]);
        $this->postPaystackWebhook($this->payload($intent, providerStatus: 'failed'))->assertOk();

        $this->assertSame('failed', $intent->fresh()->status->value);
        $this->assertSame(
            'failed',
            Transaction::query()->where('reference', 'payment-intent:'.$intent->id)->firstOrFail()->status,
        );
        $this->assertSame(0, LedgerEntry::query()->count(), 'a failed payment must never touch the ledger');
        $this->assertSame('processed', PaymentWebhookEvent::query()->firstOrFail()->processing_status);
    }

    public function test_cancelled_payment_is_recorded_as_cancelled_not_left_pending(): void
    {
        $user = $this->user();
        $intent = $this->pendingIntent($user, amountMinor: 50000, currency: 'NGN');

        $this->fakeVerification(['status' => 'abandoned', 'reference' => $intent->provider_reference]);
        $this->postPaystackWebhook($this->payload($intent, providerStatus: 'abandoned'))->assertOk();

        $this->assertSame('cancelled', $intent->fresh()->status->value);
        $this->assertSame(
            'cancelled',
            Transaction::query()->where('reference', 'payment-intent:'.$intent->id)->firstOrFail()->status,
        );
    }

    public function test_pending_provider_status_leaves_the_checkout_open(): void
    {
        $user = $this->user();
        $intent = $this->pendingIntent($user, amountMinor: 50000, currency: 'NGN');

        $this->fakeVerification(['status' => 'ongoing', 'reference' => $intent->provider_reference]);
        $this->postPaystackWebhook($this->payload($intent, providerStatus: 'ongoing'))->assertOk();

        $this->assertSame('pending', $intent->fresh()->status->value);
        $this->assertSame(
            'pending',
            Transaction::query()->where('reference', 'payment-intent:'.$intent->id)->firstOrFail()->status,
        );
        $this->assertSame('ignored', PaymentWebhookEvent::query()->firstOrFail()->processing_status);
    }

    public function test_cross_currency_topup_credits_the_paid_currency_without_conversion(): void
    {
        // The user's wallet currency is NGN, but they paid in USD: the top-up
        // is credited in USD (no silent conversion) and the projection records
        // the paid amount and currency verbatim.
        $user = $this->user(walletCurrency: 'NGN');
        $intent = $this->pendingIntent($user, amountMinor: 1000, currency: 'USD');

        $this->fakeVerification(['status' => 'success', 'amount' => 1000, 'currency' => 'USD', 'reference' => $intent->provider_reference]);
        $this->postPaystackWebhook($this->payload($intent))->assertOk();

        $wallet = Wallet::query()->where('user_id', $user->id)->where('wallet_type', 'topup_fund')->firstOrFail();
        $this->assertSame('USD', $wallet->currency);
        $this->assertSame(1000, (int) $wallet->balance_minor);
        $this->assertSame(0, Wallet::query()->where('user_id', $user->id)->where('currency', 'NGN')->count());

        $transaction = Transaction::query()->where('reference', 'payment-intent:'.$intent->id)->firstOrFail();
        $this->assertSame('completed', $transaction->status);
        $this->assertSame('USD', $transaction->meta['currency']);
        $this->assertSame(1000, (int) $transaction->meta['amount_minor']);
        $this->assertSame(10.0, (float) $transaction->amount);
    }

    public function test_gateway_checkout_accepts_a_reserved_test_email_domain(): void
    {
        // `email:rfc,dns` used to reject `@example.test` addresses (and time out
        // offline); the checkout now validates syntax only.
        $user = $this->user(email: 'payer@example.test');

        \App\Models\SystemSetting::query()->create([
            'setting_key' => 'payment.active_gateways',
            'value' => ['paystack' => ['NGN']],
            'is_public' => false,
        ]);

        Http::fake([
            'api.paystack.test/transaction/initialize' => Http::response([
                'status' => true,
                'data' => ['authorization_url' => 'https://checkout.paystack.test/abc'],
            ]),
        ]);

        $token = $user->createToken('checkout-test')->plainTextToken;

        $this->withToken($token)
            ->postJson('/api/v1/topups/checkout', [
                'amount_minor' => 50000,
                'currency' => 'NGN',
                'gateway' => 'paystack',
                'idempotency_key' => 'checkout-test-1',
            ])
            ->assertCreated()
            ->assertJsonPath('data.status', 'pending');

        $intent = PaymentIntent::query()->firstOrFail();
        $transaction = Transaction::query()->where('reference', 'payment-intent:'.$intent->id)->firstOrFail();
        $this->assertSame('pending', $transaction->status);
        $this->assertSame('credit', $transaction->direction);
    }

    private function user(string $email = 'payer@example.com', string $walletCurrency = 'NGN'): User
    {
        return User::query()->create([
            'full_name' => 'Webhook Payer',
            'username' => 'webhook-payer-'.substr(md5($email), 0, 8),
            'email' => $email,
            'preferred_currency' => $walletCurrency,
            'wallet_currency' => $walletCurrency,
            'role' => 'reviewer',
            'status' => 'active',
        ]);
    }

    private function pendingIntent(User $user, int $amountMinor, string $currency): PaymentIntent
    {
        $reference = 'ref-'.substr(md5((string) $amountMinor.$currency.microtime()), 0, 12);

        $intent = PaymentIntent::query()->create([
            'user_id' => $user->id,
            'purpose' => 'topup',
            'amount_minor' => $amountMinor,
            'currency' => $currency,
            'provider' => 'paystack',
            'provider_reference' => $reference,
            'idempotency_key' => 'paystack-topup:'.$user->id.':'.$reference,
            'status' => 'pending',
            'expires_at' => now()->addHours(2),
        ]);

        Transaction::query()->create([
            'user_id' => $user->id,
            'type' => 'top_up_fund',
            'direction' => 'credit',
            'amount' => $amountMinor / 100,
            'fee' => 0,
            'wallet' => null,
            'status' => 'pending',
            'reference' => 'payment-intent:'.$intent->id,
            'description' => 'Wallet top-up',
            'meta' => [
                'payment_intent_id' => $intent->id,
                'currency' => $currency,
                'currency_minor_units' => 2,
                'amount_minor' => $amountMinor,
                'provider' => 'paystack',
                'purpose' => 'topup',
            ],
        ]);

        return $intent;
    }

    /** @param array<string, mixed> $verified */
    private function fakeVerification(array $verified): void
    {
        Http::fake([
            'api.paystack.test/transaction/verify/*' => Http::response(['status' => true, 'data' => $verified]),
        ]);
    }

    /** @return array<string, mixed> */
    private function payload(PaymentIntent $intent, string $providerStatus = 'success'): array
    {
        return [
            'event' => 'charge.'.$providerStatus,
            'data' => [
                'id' => 991122,
                'reference' => $intent->provider_reference,
                'status' => $providerStatus,
                'amount' => $intent->amount_minor,
                'currency' => $intent->currency,
            ],
        ];
    }

    /** @param array<string, mixed> $payload */
    private function postPaystackWebhook(array $payload): \Illuminate\Testing\TestResponse
    {
        $body = json_encode($payload, JSON_UNESCAPED_SLASHES);
        $signature = hash_hmac('sha512', $body, self::SECRET);

        return $this->call(
            'POST',
            '/api/v1/webhooks/paystack',
            [], [], [],
            ['CONTENT_TYPE' => 'application/json', 'HTTP_X_PAYSTACK_SIGNATURE' => $signature],
            $body,
        );
    }
}
