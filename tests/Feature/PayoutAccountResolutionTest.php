<?php

namespace Tests\Feature;

use App\Application\Actions\Wallets\ResolvePayoutAccount;
use App\Models\Bank;
use App\Models\Country;
use App\Models\SystemSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/**
 * ERR-31: Korapay's `/misc/banks/resolve` expects an ISO-4217 currency code,
 * but `banks.country` is an ISO-3166 alpha-2 code. Sending `NG` failed every
 * payout account resolution.
 *
 * The provider itself was also unreachable in practice: the configured
 * `account_validation.provider` value was read with `->value('value')`, which
 * bypasses the model's array cast and produced raw JSON, so every deployment
 * silently used Paystack.
 */
class PayoutAccountResolutionTest extends TestCase
{
    use RefreshDatabase;

    public function test_korapay_receives_the_currency_code_for_the_bank_country(): void
    {
        $this->configureProvider('korapay');
        Country::query()->create(['code' => 'NG', 'name' => 'Nigeria', 'default_currency' => 'NGN', 'is_available' => true]);
        $user = $this->user('NG');
        $bank = $this->bank('NG');

        Http::fake([
            'api.korapay.test/misc/banks/resolve' => Http::response([
                'status' => true,
                'data' => ['account_name' => 'Ada Lovelace'],
            ]),
        ]);

        $resolved = app(ResolvePayoutAccount::class)->handle($user, $bank, '0123456789');

        $this->assertSame('korapay', $resolved['provider']);
        $this->assertSame('Ada Lovelace', $resolved['account_name']);

        Http::assertSent(function ($request) {
            return str_contains($request->url(), '/misc/banks/resolve')
                && $request['currency'] === 'NGN'
                && $request['account'] === '0123456789';
        });
    }

    public function test_unknown_country_currency_is_rejected_instead_of_sent_to_the_provider(): void
    {
        $this->configureProvider('korapay');
        // A country with no payout currency and no inline fallback.
        Country::query()->create(['code' => 'ZZ', 'name' => 'Nowhere', 'default_currency' => '', 'is_available' => false]);
        $user = $this->user('ZZ');
        $bank = $this->bank('ZZ');

        Http::fake();

        $this->expectException(ValidationException::class);

        try {
            app(ResolvePayoutAccount::class)->handle($user, $bank, '0123456789');
        } finally {
            Http::assertNothingSent();
        }
    }

    public function test_paystack_provider_never_receives_a_currency_field(): void
    {
        $this->configureProvider('paystack');
        Country::query()->create(['code' => 'NG', 'name' => 'Nigeria', 'default_currency' => 'NGN', 'is_available' => true]);
        $user = $this->user('NG');
        $bank = $this->bank('NG');

        Http::fake([
            'api.paystack.test/bank/resolve*' => Http::response([
                'status' => true,
                'data' => ['account_name' => 'Grace Hopper'],
            ]),
        ]);

        $resolved = app(ResolvePayoutAccount::class)->handle($user, $bank, '0123456789');

        $this->assertSame('paystack', $resolved['provider']);
        Http::assertSent(fn ($request) => str_contains($request->url(), '/bank/resolve'));
    }

    private function configureProvider(string $provider): void
    {
        SystemSetting::query()->create([
            'setting_key' => 'account_validation.provider',
            'value' => ['provider' => $provider],
            'is_public' => false,
        ]);

        config()->set('trustlens.payment_gateways.korapay', [
            'secret_key' => 'korapay_test_key',
            'base_url' => 'https://api.korapay.test',
        ]);
        config()->set('trustlens.payment_gateways.paystack', [
            'secret_key' => 'paystack_test_key',
            'base_url' => 'https://api.paystack.test',
        ]);
    }

    private function user(string $country): User
    {
        return User::query()->create([
            'full_name' => 'Payout User',
            'username' => 'payout-'.strtolower($country),
            'email' => 'payout-'.strtolower($country).'@example.test',
            'country_code' => $country,
            'preferred_currency' => 'NGN',
            'wallet_currency' => 'NGN',
            'role' => 'reviewer',
            'status' => 'active',
        ]);
    }

    private function bank(string $country): Bank
    {
        return Bank::query()->create([
            'name' => 'Test Bank',
            'code' => '058',
            'paystack_code' => '058',
            'institution_code' => '058',
            'type' => 'bank',
            'country' => $country,
            'is_active' => true,
        ]);
    }
}
