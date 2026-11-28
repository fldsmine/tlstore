<?php

namespace Tests\Feature;

use App\Application\Actions\Payments\InitializeGatewayTopup;
use App\Application\Actions\Wallets\ResolvePayoutAccount;
use App\Application\Payments\GatewayBalances;
use App\Models\Admin;
use App\Models\Bank;
use App\Models\Country;
use App\Models\LedgerEntry;
use App\Models\PaymentIntent;
use App\Models\PaymentTransaction;
use App\Models\PaymentWebhookEvent;
use App\Models\SystemSetting;
use App\Models\Transaction;
use App\Models\User;
use App\Models\Wallet;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Fincra hosted checkout as a first-class gateway: checkout creation, the
 * HMAC-SHA512 webhook (raw body, `signature` header), provider verification,
 * underpayment safety, live wallet balances and payout account resolution.
 */
class FincraGatewayTest extends TestCase
{
    use RefreshDatabase;

    private const SECRET = 'sk_test_fincra';

    private const WEBHOOK_SECRET = 'whsec_test_fincra';

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('trustlens.payment_gateways.fincra', [
            'secret_key' => self::SECRET,
            'public_key' => 'pk_test_fincra',
            'business_id' => 'biz_test_fincra',
            'webhook_secret' => self::WEBHOOK_SECRET,
            'base_url' => 'https://api.fincra.test',
            'balance_path' => '/wallets',
            'fee_bearer' => 'business',
        ]);
        config()->set('trustlens.implemented_payment_gateways', ['manual', 'fincra']);
        config()->set('trustlens.payment_webhook_base_url', 'https://example.test');
        config()->set('trustlens.payment_return_url', 'https://example.test/payments/return');

        // users.country_code is a foreign key onto the countries table.
        Country::query()->firstOrCreate(
            ['code' => 'NG'],
            ['name' => 'Nigeria', 'default_currency' => 'NGN', 'is_available' => true],
        );
    }

    public function test_checkout_creates_a_hosted_link_and_keeps_the_pay_code(): void
    {
        SystemSetting::query()->create([
            'setting_key' => 'payment.active_gateways',
            'value' => ['fincra' => ['NGN']],
            'is_public' => false,
        ]);

        Http::fake([
            'api.fincra.test/checkout/payments' => Http::response([
                'status' => true,
                'message' => 'Hosted link generated',
                'data' => [
                    'link' => 'https://checkout.fincra.test/pay/fcr-p-08e0c36289',
                    'reference' => 'KB-909',
                    'payCode' => 'fcr-p-08e0c36289',
                ],
            ]),
        ]);

        $user = $this->user();
        $token = $user->createToken('fincra-checkout')->plainTextToken;

        $this->withToken($token)
            ->postJson('/api/v1/topups/checkout', [
                'amount_minor' => 50000,
                'currency' => 'NGN',
                'gateway' => 'fincra',
                'idempotency_key' => 'fincra-checkout-1',
            ])
            ->assertCreated()
            ->assertJsonPath('data.checkout_url', 'https://checkout.fincra.test/pay/fcr-p-08e0c36289')
            ->assertJsonPath('data.status', 'pending');

        $intent = PaymentIntent::query()->firstOrFail();
        $this->assertSame('fincra', $intent->provider);
        $this->assertSame('fcr-p-08e0c36289', $intent->provider_metadata['fincra_pay_code']);

        Http::assertSent(function ($request): bool {
            return str_ends_with($request->url(), '/checkout/payments')
                && $request->hasHeader('api-key', self::SECRET)
                && $request->hasHeader('x-pub-key', 'pk_test_fincra')
                // 50,000 kobo → NGN 500.00, in Fincra's major units.
                && $request['amount'] === 500
                && $request['currency'] === 'NGN'
                && $request['feeBearer'] === 'business'
                && str_starts_with((string) $request['redirectUrl'], 'https://example.test/payments/return')
                && str_starts_with((string) $request['reference'], 'tls-');
        });
    }

    public function test_signed_webhook_settles_the_intent_and_credits_the_wallet_once(): void
    {
        $user = $this->user();
        $intent = $this->pendingIntent($user, 250000, 'NGN');

        $this->fakeVerification($intent, amountReceived: 2500);
        $payload = $this->payload($intent, amountReceived: 2500);

        $this->postFincraWebhook($payload)->assertOk();

        $this->assertSame('paid', $intent->fresh()->status->value);
        $wallet = Wallet::query()->where('user_id', $user->id)->where('wallet_type', 'topup_fund')->firstOrFail();
        $this->assertSame(250000, (int) $wallet->balance_minor);
        $this->assertSame(1, LedgerEntry::query()->count());
        $this->assertSame(1, PaymentTransaction::query()->count());
        $this->assertSame('processed', PaymentWebhookEvent::query()->firstOrFail()->processing_status);

        // Fincra retries deliveries; a replay must never double-credit.
        $this->postFincraWebhook($payload)->assertOk();
        $this->assertSame(250000, (int) $wallet->fresh()->balance_minor);
        $this->assertSame(1, LedgerEntry::query()->count());
    }

    public function test_webhook_with_an_invalid_signature_is_rejected(): void
    {
        $intent = $this->pendingIntent($this->user(), 100000, 'NGN');
        Http::fake();

        $body = json_encode($this->payload($intent), JSON_UNESCAPED_SLASHES);

        $this->call('POST', '/api/v1/webhooks/fincra', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_SIGNATURE' => hash_hmac('sha512', $body, 'the-wrong-secret'),
        ], $body)->assertStatus(401);

        $this->assertSame('pending', $intent->fresh()->status->value);
        $this->assertSame(0, LedgerEntry::query()->count());
        $this->assertSame('rejected', PaymentWebhookEvent::query()->firstOrFail()->processing_status);
    }

    public function test_underpaid_bank_transfer_never_settles_the_intent(): void
    {
        $intent = $this->pendingIntent($this->user(), 250000, 'NGN');

        $this->fakeVerification($intent, amountReceived: 2000);

        $this->postFincraWebhook($this->payload($intent, amountReceived: 2000, varianceType: 'underpayment'))
            ->assertStatus(500);

        $this->assertSame('pending', $intent->fresh()->status->value);
        $this->assertSame(0, LedgerEntry::query()->count());
        $this->assertSame('failed', PaymentWebhookEvent::query()->firstOrFail()->processing_status);
    }

    public function test_payload_without_a_merchant_reference_is_matched_by_pay_code(): void
    {
        $intent = $this->pendingIntent($this->user(), 120000, 'NGN');
        $intent->forceFill(['provider_metadata' => ['fincra_pay_code' => 'fcr-p-abc123']])->save();
        Http::fake();

        $body = [
            'event' => 'charge.successful',
            'data' => [
                'id' => 771122,
                'reference' => 'fcr-p-abc123',
                'status' => 'success',
                'amount' => 1200,
                'amountReceived' => 1200,
                'currency' => 'NGN',
            ],
        ];

        $this->postFincraWebhook($body)->assertOk();

        $this->assertSame('paid', $intent->fresh()->status->value);
        // No merchant reference: the signed body itself is trusted, so no
        // outbound verification call is made.
        Http::assertNothingSent();
    }

    public function test_check_endpoint_settles_a_pending_checkout(): void
    {
        $user = $this->user();
        $intent = $this->pendingIntent($user, 150000, 'NGN');
        $this->fakeVerification($intent, amountReceived: 1500);

        $this->withToken($user->createToken('fincra-check')->plainTextToken)
            ->postJson('/api/v1/topups/'.$intent->id.'/check')
            ->assertOk()
            ->assertJsonPath('data.status', 'paid');

        $wallet = Wallet::query()->where('user_id', $user->id)->where('wallet_type', 'topup_fund')->firstOrFail();
        $this->assertSame(150000, (int) $wallet->balance_minor);
    }

    public function test_live_balances_come_from_the_wallets_endpoint(): void
    {
        Http::fake([
            'api.fincra.test/wallets*' => Http::response([
                'success' => true,
                'message' => 'Wallets fetched successfully',
                'data' => [
                    ['currency' => 'NGN', 'availableBalance' => 12345.67, 'ledgerBalance' => 12345.67],
                    ['currency' => 'USD', 'availableBalance' => 100, 'ledgerBalance' => 100],
                ],
            ]),
        ]);

        $result = app(GatewayBalances::class)->forGateway('fincra');

        $this->assertTrue($result['configured']);
        $this->assertNull($result['error']);
        $this->assertSame([
            ['currency' => 'NGN', 'available_minor' => 1234567],
            ['currency' => 'USD', 'available_minor' => 10000],
        ], $result['balances']);

        Http::assertSent(fn ($request): bool => str_contains($request->url(), '/wallets?')
            && str_contains($request->url(), 'businessID=biz_test_fincra')
            && $request->hasHeader('api-key', self::SECRET));
    }

    public function test_payout_accounts_resolve_through_fincra(): void
    {
        SystemSetting::query()->create([
            'setting_key' => 'account_validation.provider',
            'value' => ['provider' => 'fincra'],
            'is_public' => false,
        ]);
        Country::query()->firstOrCreate(['code' => 'NG'], ['name' => 'Nigeria', 'default_currency' => 'NGN', 'is_available' => true]);
        $user = $this->user(country: 'NG');
        $bank = Bank::query()->create([
            'name' => 'Test Bank',
            'code' => '058',
            'type' => 'bank',
            'country' => 'NG',
            'is_active' => true,
        ]);

        Http::fake([
            'api.fincra.test/core/accounts/resolve' => Http::response([
                'success' => true,
                'message' => 'Account resolve successful',
                'data' => ['accountNumber' => '0123456789', 'accountName' => 'Ada Lovelace'],
            ]),
        ]);

        $resolved = app(ResolvePayoutAccount::class)->handle($user, $bank, '0123456789');

        $this->assertSame('fincra', $resolved['provider']);
        $this->assertSame('Ada Lovelace', $resolved['account_name']);

        Http::assertSent(fn ($request): bool => str_ends_with($request->url(), '/core/accounts/resolve')
            && $request->hasHeader('api-key', self::SECRET)
            && $request['type'] === 'nuban'
            && $request['bankCode'] === '058'
            && $request['currency'] === 'NGN');
    }

    public function test_admin_gateway_settings_accept_fincra_currencies(): void
    {
        $admin = Admin::query()->create([
            'full_name' => 'Super Admin',
            'email' => 'super-'.Str::lower(Str::random(6)).'@tlstore.test',
            'password' => 'StrongPassword!2345',
            'role' => 'super_admin',
            'status' => 'active',
        ]);

        $this->actingAs($admin, 'sanctum')
            ->putJson('/api/v1/admin/settings/payment.active_gateways', [
                'value' => ['fincra' => ['NGN', 'GHS']],
            ])
            ->assertOk();

        $this->assertSame(
            ['fincra' => ['NGN', 'GHS']],
            SystemSetting::query()->where('setting_key', 'payment.active_gateways')->firstOrFail()->value,
        );

        $this->actingAs($admin, 'sanctum')
            ->putJson('/api/v1/admin/settings/payment.active_payment_currency', [
                'value' => ['fincra.NGN' => 'Fincra — Naira card & transfer'],
            ])
            ->assertOk();

        $this->assertSame(
            ['fincra.NGN' => 'Fincra — Naira card & transfer'],
            SystemSetting::query()->where('setting_key', 'payment.active_payment_currency')->firstOrFail()->value,
        );
    }

    private function user(string $country = 'NG'): User
    {
        return User::query()->create([
            'full_name' => 'Fincra Payer',
            'username' => 'fincra-'.Str::lower(Str::random(8)),
            'email' => 'fincra-'.Str::lower(Str::random(8)).'@example.test',
            'country_code' => $country,
            'preferred_currency' => 'NGN',
            'wallet_currency' => 'NGN',
            'role' => 'reviewer',
            'status' => 'active',
        ]);
    }

    private function pendingIntent(User $user, int $amountMinor, string $currency): PaymentIntent
    {
        $reference = 'tls-'.$user->id.'-'.Str::lower(Str::random(24));

        $intent = PaymentIntent::query()->create([
            'user_id' => $user->id,
            'purpose' => 'topup',
            'amount_minor' => $amountMinor,
            'currency' => $currency,
            'provider' => 'fincra',
            'provider_reference' => $reference,
            'idempotency_key' => 'fincra-topup:'.$user->id.':'.$reference,
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
                'provider' => 'fincra',
                'purpose' => 'topup',
            ],
        ]);

        return $intent;
    }

    private function fakeVerification(PaymentIntent $intent, int|float $amountReceived): void
    {
        Http::fake([
            'api.fincra.test/checkout/payments/merchant-reference/*' => Http::response([
                'status' => true,
                'message' => 'Payment fetched successfully',
                'data' => [
                    'id' => 445566,
                    'reference' => 'fcr-p-08e0c36289',
                    'merchantReference' => $intent->provider_reference,
                    'status' => 'success',
                    'amount' => $amountReceived,
                    'amountReceived' => $amountReceived,
                    'currency' => $intent->currency,
                ],
            ]),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(PaymentIntent $intent, int|float $amountReceived = 2500, ?string $varianceType = null): array
    {
        return [
            'event' => 'charge.successful',
            'data' => [
                'id' => 889900,
                'reference' => 'fcr-p-08e0c36289',
                'merchantReference' => $intent->provider_reference,
                'chargeReference' => 'fcr-bt-11223344',
                'status' => 'success',
                'amount' => $amountReceived,
                'amountReceived' => $amountReceived,
                'amountExpected' => $intent->amount_minor,
                'varianceType' => $varianceType,
                'currency' => $intent->currency,
                'type' => 'bank_transfer',
                'settlementDestination' => 'wallet',
            ],
        ];
    }

    /**
     * Fincra signs the raw JSON body with HMAC-SHA512; the hex digest is the
     * `signature` header. The body must be posted byte-for-byte as signed.
     *
     * @param  array<string, mixed>  $payload
     */
    private function postFincraWebhook(array $payload): \Illuminate\Testing\TestResponse
    {
        $body = json_encode($payload, JSON_UNESCAPED_SLASHES);
        $signature = hash_hmac('sha512', $body, self::WEBHOOK_SECRET);

        return $this->call(
            'POST',
            '/api/v1/webhooks/fincra',
            [], [], [],
            ['CONTENT_TYPE' => 'application/json', 'HTTP_SIGNATURE' => $signature],
            $body,
        );
    }
}
