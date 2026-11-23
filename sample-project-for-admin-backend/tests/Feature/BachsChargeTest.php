<?php

use App\Enums\ChargePurpose;
use App\Enums\PaymentProvider;
use App\Enums\TransactionType;
use App\Models\CloudStoragePlan;
use App\Models\Country;
use App\Models\Currency;
use App\Models\Package;
use App\Models\PaymentReference;
use App\Models\Transaction;
use App\Models\User;
use App\Services\Payments\Gateways\BachsGateway;
use App\Services\Payments\PaymentGatewayManager;
use App\Services\Payments\StoragePurchaseService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request as ClientRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

uses(RefreshDatabase::class);

/**
 * Bachs as a CHARGE provider: the five purchases must be able to collect through it, and every
 * settlement guarantee the other gateways honour must hold for Bachs too — verified server-side,
 * reconciled against the amount we asked for, credited exactly once, and never terminalized on an
 * answer that cannot be trusted.
 *
 * Only Bachs' HTTP responses are faked; the real settlement path (service → gateway verify →
 * reconciliation → single-use ledger → entitlement) runs underneath.
 */
beforeEach(function () {
    Currency::create(['code' => 'NGN', 'name' => 'Nigerian Naira', 'symbol' => '₦', 'usd_exchange_rate' => 1500, 'is_active' => true]);
    Country::create(['name' => 'Nigeria', 'code' => 'NG', 'currency_code' => 'NGN', 'is_active' => true]);

    // Cloud storage is only sold to a customer whose package includes Cloud calling.
    Package::create([
        'name' => 'Royal eSIM',
        'tier_level' => 2,
        'price' => 20,
        'upgrade_price' => 12,
        'enable_cloud' => true,
        'cloud_calls' => true,
        'cloud_sms' => true,
        'cloud_contact_limit' => 10,
        'cloud_sms_limit' => 20,
        'is_active' => true,
    ]);

    CloudStoragePlan::create([
        'name' => 'Starter Storage',
        'extra_contacts' => 5,
        'extra_sms' => 5,
        'duration_days' => 30,
        'token_price' => 5,
        'renewal_token_price' => 3,
        'is_active' => true,
    ]);

    config([
        'settings.payment.storage_provider' => 'bachs',
        'settings.payment.bill_provider' => 'bachs',
        'settings.payment.renewal_provider' => 'bachs',
        'settings.payment.package_provider' => 'bachs',
        'settings.payment.special_coupon_provider' => 'bachs',
        'services.bachs.base_url' => 'https://api.bachs.io',
        'services.bachs.secret_key' => 'sk_live_bachs_test',
        'services.bachs.currency' => 'NGN',
        'services.bachs.webhook_secret' => 'whsec_bachs_test',
    ]);
});

function bachsUser(): User
{
    return User::factory()->create([
        'package_id' => Package::where('tier_level', 2)->first()->id,
        'country' => 'NG',
    ]);
}

function bachsPlan(): CloudStoragePlan
{
    return CloudStoragePlan::whereNull('tier_level')->first();
}

/**
 * Fake every Bachs endpoint the charge flow touches with ONE stateful closure (Laravel merges stacked
 * fakes, so a second fake() would silently lose branches).
 *
 * `$verify($reference, $expectedAmount)` decides what retrieving the checkout answers; it returns
 * overrides — `http` (status), `throw` (transport failure), `paymentStatus`, `chargeStatus`,
 * `amount`, `currency`, `reference` — or [] for "paid in full, succeeded".
 *
 * @param  callable(string, float): array<string, mixed>|null  $verify
 * @param  array<string, mixed>|null  $captured  receives the checkout-create payload
 */
function fakeBachsGate(?callable $verify = null, ?array &$captured = null, ?array $scanItems = null): void
{
    $expected = [];
    $checkouts = [];

    Http::fake(function (ClientRequest $request) use (&$expected, &$checkouts, $verify, &$captured, $scanItems) {
        $url = $request->url();
        $body = $request->data();

        // Create a checkout session.
        if (str_ends_with($url, '/v1/checkout-sessions')) {
            $reference = (string) ($body['reference'] ?? '');
            $expected[$reference] = (float) ($body['pricing']['amount'] ?? 0);
            $checkoutId = 'chk_'.substr(md5($reference), 0, 12);
            $checkouts[$checkoutId] = $reference;
            $captured = $body;

            return Http::response([
                'checkout_id' => $checkoutId,
                'checkout_url' => 'https://checkout.bachs.io/c/'.$checkoutId,
                'status' => 'open',
                'reference' => $reference,
                'expires_at' => now()->addHour()->toIso8601String(),
                'created_at' => now()->toIso8601String(),
            ], 201);
        }

        // Retrieve a checkout session (the charge driver's primary verify path).
        if (str_contains($url, '/v1/checkout-sessions/')) {
            $checkoutId = basename((string) parse_url($url, PHP_URL_PATH));
            $reference = $checkouts[$checkoutId] ?? '';

            if ($reference === '') {
                return Http::response(['detail' => 'Checkout not found', 'error_code' => 'NOT_FOUND'], 404);
            }

            $overrides = $verify !== null ? ($verify($reference, $expected[$reference] ?? 0.0) ?? []) : [];

            if (($overrides['throw'] ?? false) === true) {
                throw new ConnectionException('cURL error 28: Operation timed out');
            }

            if (isset($overrides['http'])) {
                return Http::response(['detail' => 'Bachs said no', 'error_code' => 'SERVICE_UNAVAILABLE'], (int) $overrides['http']);
            }

            $paid = ! isset($overrides['paymentStatus']) || $overrides['paymentStatus'] === 'succeeded';

            return Http::response([
                'checkout_id' => $checkoutId,
                'status' => $overrides['checkoutStatus'] ?? ($paid ? 'completed' : 'open'),
                'payment_status' => $overrides['paymentStatus'] ?? 'succeeded',
                'amount' => (string) ($overrides['amount'] ?? $expected[$reference] ?? 0),
                'currency' => (string) ($overrides['currency'] ?? 'NGN'),
                'reference' => $overrides['reference'] ?? $reference,
                'charge' => $paid ? [
                    'payment_id' => 'ch_'.substr(md5($reference), 0, 10),
                    'status' => $overrides['chargeStatus'] ?? 'succeeded',
                    'amount' => (string) ($overrides['amount'] ?? $expected[$reference] ?? 0),
                    'currency' => (string) ($overrides['currency'] ?? 'NGN'),
                ] : null,
            ]);
        }

        // The payments list — used only by the reference-scan fallback.
        if (str_contains($url, '/v1/payments')) {
            if ($scanItems !== null) {
                return Http::response(['items' => $scanItems, 'pagination' => ['has_more' => false, 'total' => count($scanItems)]]);
            }

            $items = [];

            foreach ($checkouts as $reference) {
                if ($reference !== '') {
                    $items[] = [
                        'id' => 'chrg_'.substr(md5($reference), 0, 10),
                        'reference' => $reference,
                        'status' => 'succeeded',
                        'amount' => (string) ($expected[$reference] ?? 0),
                        'amount_paid' => (string) ($expected[$reference] ?? 0),
                        'currency' => 'NGN',
                        'transaction_date' => now()->toIso8601String(),
                    ];
                }
            }

            return Http::response(['items' => $items, 'pagination' => ['has_more' => false, 'total' => count($items)]]);
        }

        return Http::response(['detail' => 'unexpected call to '.$url, 'error_code' => 'UNEXPECTED'], 500);
    });
}

/** Post a signed (or deliberately unsigned) Bachs webhook to the shared endpoint. */
function postBachsWebhook(TestCase $case, string $body, ?string $signature, ?string $timestamp = null, string $header = 'X-Bachs-Signature'): TestResponse
{
    $server = [
        'CONTENT_TYPE' => 'application/json',
        'HTTP_ACCEPT' => 'application/json',
        'HTTP_X-Bachs-Timestamp' => $timestamp ?? (string) now()->getTimestamp(),
    ];

    if ($signature !== null) {
        $server['HTTP_'.str_replace('-', '_', strtoupper($header))] = $signature;
    }

    return $case->call('POST', route('webhooks.payments', ['provider' => 'bachs']), [], [], [], $server, $body);
}

/* ------------------------------------------------------------------------------- routing */

test('Bachs is offered for every purchase and resolves to its own gateway', function () {
    foreach (ChargePurpose::cases() as $purpose) {
        config(["settings.payment.{$purpose->settingKey()}" => 'bachs']);
    }

    $manager = app(PaymentGatewayManager::class);

    expect($manager->driver('bachs'))->toBeInstanceOf(BachsGateway::class)
        ->and(PaymentProvider::values())->toContain('bachs')
        ->and(PaymentProvider::from('bachs')->label())->toBe('Bachs');

    foreach (ChargePurpose::cases() as $purpose) {
        expect($manager->for($purpose))->toBeInstanceOf(BachsGateway::class)
            ->and($manager->availableFor($purpose))->toBeTrue();
    }
});

test('a charge cannot start through Bachs without its secret key', function () {
    config(['services.bachs.secret_key' => null]);

    expect(app(BachsGateway::class)->configured())->toBeFalse()
        ->and(app(PaymentGatewayManager::class)->availableFor(ChargePurpose::STORAGE))->toBeFalse();

    Http::fake();

    $start = app(StoragePurchaseService::class)->start(bachsUser(), bachsPlan(), 'activate');

    expect($start['ok'])->toBeFalse();
});

/* -------------------------------------------------------------------------- checkout */

test('a checkout session is created with a decimal-string pricing intent and our reference', function () {
    $captured = null;
    fakeBachsGate(captured: $captured);

    $user = bachsUser();
    $start = app(StoragePurchaseService::class)->start($user, bachsPlan(), 'activate');

    expect($start['ok'])->toBeTrue()
        ->and($start['checkout_url'] ?? null)->toContain('checkout.bachs.io');

    // Money is a decimal STRING in the major unit — never minor units, never a bare number.
    expect($captured['pricing']['currency'] ?? null)->toBe('NGN')
        ->and($captured['pricing']['amount'] ?? null)->toBe('7500.00')
        ->and($captured['reference'] ?? null)->toBe($start['reference'])
        ->and($captured['customer']['email'] ?? null)->toBe($user->email)
        ->and($captured['metadata']['reference'] ?? null)->toBe($start['reference']);

    // The checkout id is recorded on the charge, which is how the verify finds it again.
    expect(Transaction::where('reference', $start['reference'])->value('gateway_reference'))->toStartWith('chk_');
});

test('a verified Bachs collection settles once and is credited exactly once', function () {
    fakeBachsGate();

    $user = bachsUser();
    $service = app(StoragePurchaseService::class);
    $start = $service->start($user, bachsPlan(), 'activate');

    expect($service->finalize($start['reference']))->toBe('completed')
        ->and($user->cloudStorageTopUps()->active()->count())->toBe(1);

    $entry = PaymentReference::query()->sole();

    expect($entry->provider)->toBe('bachs')
        ->and($entry->reference)->toBe($start['reference'])
        ->and($entry->type)->toBe(TransactionType::STORAGE_PURCHASE->value)
        ->and($entry->gateway_reference)->toStartWith('chk_');

    // A replayed settle (webhook + callback + sweep) must be a no-op.
    expect($service->finalize($start['reference']))->toBe('completed')
        ->and($user->cloudStorageTopUps()->count())->toBe(1)
        ->and(PaymentReference::query()->count())->toBe(1);
});

test('a checkout that is still open keeps the charge pending, never failed', function () {
    fakeBachsGate(fn (string $reference) => ['paymentStatus' => 'created', 'chargeStatus' => 'created', 'checkoutStatus' => 'open']);

    $user = bachsUser();
    $service = app(StoragePurchaseService::class);
    $start = $service->start($user, bachsPlan(), 'activate');

    expect($service->finalize($start['reference']))->toBe('pending')
        ->and(Transaction::where('reference', $start['reference'])->value('status'))->toBe('pending')
        ->and($user->cloudStorageTopUps()->count())->toBe(0)
        ->and(PaymentReference::query()->count())->toBe(0);
});

test('an answer that cannot be trusted never terminalizes a Bachs charge', function () {
    // A paid charge whose verify fails three different ways must stay pending: the money may be in.
    foreach ([['http' => 404], ['http' => 502], ['throw' => true]] as $failure) {
        fakeBachsGate(fn () => $failure);

        $user = bachsUser();
        $service = app(StoragePurchaseService::class);
        $start = $service->start($user, bachsPlan(), 'activate');

        expect($service->finalize($start['reference']))->toBe('unavailable')
            ->and(Transaction::where('reference', $start['reference'])->value('status'))->toBe('pending')
            ->and($user->cloudStorageTopUps()->count())->toBe(0);
    }
});

test('a charge with no recorded checkout id is found by scanning the newest payments', function () {
    fakeBachsGate();

    $user = bachsUser();
    $service = app(StoragePurchaseService::class);
    $start = $service->start($user, bachsPlan(), 'activate');

    // A row that lost its gateway reference (older charge, imported data) still reconciles.
    Transaction::where('reference', $start['reference'])->update(['gateway_reference' => null]);

    expect($service->finalize($start['reference']))->toBe('completed')
        ->and($user->cloudStorageTopUps()->active()->count())->toBe(1);
});

test('a scan that cannot find the reference leaves the charge pending for the sweep', function () {
    // The charge exists, but its checkout id is gone AND the scan cannot match our reference —
    // Bachs has no answer, so the charge must stay pending (never failed) for the sweep.
    fakeBachsGate(scanItems: []);

    $user = bachsUser();
    $service = app(StoragePurchaseService::class);
    $start = $service->start($user, bachsPlan(), 'activate');

    Transaction::where('reference', $start['reference'])->update(['gateway_reference' => null]);

    expect($service->finalize($start['reference']))->toBe('unavailable')
        ->and(Transaction::where('reference', $start['reference'])->value('status'))->toBe('pending')
        ->and(PaymentReference::query()->count())->toBe(0);
});

test('an underpaid Bachs collection fails reconciliation instead of crediting', function () {
    fakeBachsGate(fn (string $reference, float $expected) => ['amount' => (string) ($expected - 1)]);

    $user = bachsUser();
    $service = app(StoragePurchaseService::class);
    $start = $service->start($user, bachsPlan(), 'activate');

    expect($service->finalize($start['reference']))->toBe('failed')
        ->and($user->cloudStorageTopUps()->count())->toBe(0)
        ->and(PaymentReference::query()->count())->toBe(0);

    $charge = Transaction::where('reference', $start['reference'])->first();

    expect((float) ($charge->meta['reconcile_error']['amount'] ?? 0))->toBe((float) $charge->meta['amount_ngn'] - 1);
});

test('a reference Bachs echoes for someone else never settles this charge', function () {
    fakeBachsGate(fn () => ['reference' => 'BTU-SOMEONE-ELSE']);

    $user = bachsUser();
    $service = app(StoragePurchaseService::class);
    $start = $service->start($user, bachsPlan(), 'activate');

    expect($service->finalize($start['reference']))->toBe('failed')
        ->and(PaymentReference::query()->count())->toBe(0);
});

/* -------------------------------------------------------------------------- webhooks */

test('the Bachs webhook refuses unsigned, stale and wrongly-signed deliveries', function () {
    fakeBachsGate();

    $user = bachsUser();
    $start = app(StoragePurchaseService::class)->start($user, bachsPlan(), 'activate');

    $body = json_encode([
        'id' => 'evt_test_1',
        'type' => 'collection.succeeded',
        'created_at' => now()->toIso8601String(),
        'data' => ['charge_id' => 'ch_test', 'checkout_id' => 'chk_none', 'status' => 'SUCCEEDED', 'amount' => '7500.00', 'currency' => 'NGN'],
    ]);

    // No signature at all.
    postBachsWebhook($this, $body, null)->assertUnauthorized();

    // A signature computed with the wrong secret.
    postBachsWebhook($this, $body, hash_hmac('sha256', now()->getTimestamp().'.'.$body, 'whsec_wrong'))->assertUnauthorized();

    // The right secret, but the right secret for a delivery from an hour ago (replay).
    $stale = (string) now()->subHour()->getTimestamp();
    postBachsWebhook($this, $body, hash_hmac('sha256', $stale.'.'.$body, 'whsec_bachs_test'), $stale)->assertUnauthorized();

    // Nothing settled, and the charge is still waiting.
    expect(Transaction::where('reference', $start['reference'])->value('status'))->toBe('pending')
        ->and(PaymentReference::query()->count())->toBe(0);
});

test('a correctly signed collection.succeeded webhook settles the charge exactly once', function () {
    fakeBachsGate();

    $user = bachsUser();
    $start = app(StoragePurchaseService::class)->start($user, bachsPlan(), 'activate');
    $checkoutId = (string) Transaction::where('reference', $start['reference'])->value('gateway_reference');

    $body = json_encode([
        'id' => 'evt_test_2',
        'type' => 'collection.succeeded',
        'created_at' => now()->toIso8601String(),
        'data' => ['charge_id' => 'ch_test', 'checkout_id' => $checkoutId, 'status' => 'SUCCEEDED', 'amount' => '7500.00', 'currency' => 'NGN'],
    ]);

    $timestamp = (string) now()->getTimestamp();

    postBachsWebhook($this, $body, hash_hmac('sha256', $timestamp.'.'.$body, 'whsec_bachs_test'), $timestamp)
        ->assertOk()
        ->assertJson(['status' => 'ok']);

    expect($user->cloudStorageTopUps()->active()->count())->toBe(1)
        ->and(PaymentReference::query()->count())->toBe(1);

    // Bachs retries at-least-once: the same delivery again must not credit twice.
    postBachsWebhook($this, $body, hash_hmac('sha256', $timestamp.'.'.$body, 'whsec_bachs_test'), $timestamp)->assertOk();

    expect($user->cloudStorageTopUps()->count())->toBe(1)
        ->and(PaymentReference::query()->count())->toBe(1);
});

test('the V2 signature header is accepted, including several v1 signatures during a rotation', function () {
    fakeBachsGate();

    $user = bachsUser();
    $start = app(StoragePurchaseService::class)->start($user, bachsPlan(), 'activate');
    $checkoutId = (string) Transaction::where('reference', $start['reference'])->value('gateway_reference');

    $body = json_encode([
        'id' => 'evt_test_3',
        'type' => 'collection.succeeded',
        'data' => ['checkout_id' => $checkoutId],
    ]);

    $timestamp = (string) now()->getTimestamp();
    $good = hash_hmac('sha256', $timestamp.'.'.$body, 'whsec_bachs_test');
    $oldSecret = hash_hmac('sha256', $timestamp.'.'.$body, 'whsec_previous');

    postBachsWebhook($this, $body, "t={$timestamp},v1={$oldSecret},v1={$good}", $timestamp, 'X-Bachs-Signature-V2')->assertOk();

    expect($user->cloudStorageTopUps()->active()->count())->toBe(1);
});

test('a webhook that is not a charge event is ignored, and one with no secret refuses everything', function () {
    fakeBachsGate();

    $body = json_encode(['id' => 'evt_test_4', 'type' => 'payout.paid', 'data' => []]);
    $timestamp = (string) now()->getTimestamp();

    postBachsWebhook($this, $body, hash_hmac('sha256', $timestamp.'.'.$body, 'whsec_bachs_test'), $timestamp)
        ->assertOk()
        ->assertJson(['status' => 'ignored']);

    // With no signing secret configured, Bachs deliveries are refused — fail closed.
    config(['services.bachs.webhook_secret' => null]);

    $chargeBody = json_encode(['id' => 'evt_test_5', 'type' => 'collection.succeeded', 'data' => ['checkout_id' => 'chk_x']]);

    postBachsWebhook($this, $chargeBody, hash_hmac('sha256', $timestamp.'.'.$chargeBody, 'whsec_bachs_test'), $timestamp)
        ->assertUnauthorized();
});
