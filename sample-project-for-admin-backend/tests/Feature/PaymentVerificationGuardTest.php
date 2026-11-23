<?php

use App\Enums\TransactionType;
use App\Models\CloudStoragePlan;
use App\Models\Country;
use App\Models\Currency;
use App\Models\Package;
use App\Models\PaymentReference;
use App\Models\Transaction;
use App\Models\User;
use App\Services\Payments\ChargeSettlement;
use App\Services\Payments\PackagePurchaseService;
use App\Services\Payments\PaymentReferenceLedger;
use App\Services\Payments\StoragePurchaseService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as ClientRequest;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

/**
 * Part B of the payment brief: a purchase is only approved once the gateway has VERIFIED it, and a
 * payment reference may credit exactly one entitlement — never the same one over and over.
 *
 * These tests drive the real settlement path (service → gateway verify → reconciliation → the
 * single-use ledger → entitlement), with only the gateway's HTTP responses faked.
 */
beforeEach(function () {
    Currency::create(['code' => 'NGN', 'name' => 'Nigerian Naira', 'symbol' => '₦', 'usd_exchange_rate' => 1500, 'is_active' => true]);
    Country::create(['name' => 'Nigeria', 'code' => 'NG', 'currency_code' => 'NGN', 'is_active' => true]);

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
        'settings.payment.storage_provider' => 'korapay',
        'settings.payment.package_provider' => 'korapay',
        'services.korapay.base_url' => 'https://api.korapay.com',
        'services.korapay.secret_key' => 'sk_test_korapay',
        'services.korapay.currency' => 'NGN',
    ]);
});

function guardUser(array $attributes = []): User
{
    return User::factory()->create(array_merge([
        'package_id' => Package::where('tier_level', 2)->first()->id,
        'country' => 'NG',
    ], $attributes));
}

function guardPlan(): CloudStoragePlan
{
    return CloudStoragePlan::whereNull('tier_level')->first();
}

/**
 * Fake Korapay and remember every charge's expected amount. `$verify()` decides what a verify call
 * for a reference answers, so a test can flip an unpaid charge to paid — or answer with someone
 * else's transaction id.
 *
 * @param  callable(string, float): array<string, mixed>|null  $verify
 */
function fakeKorapayGate(callable $verify): void
{
    $expected = [];

    Http::fake(function (ClientRequest $request) use (&$expected, $verify) {
        if (str_contains($request->url(), 'charges/initialize')) {
            $body = $request->data();
            $expected[$body['reference']] = (float) $body['amount'];

            return Http::response([
                'status' => true,
                'data' => ['checkout_url' => 'https://checkout.korapay.com/'.$body['reference'], 'reference' => 'kpy_'.$body['reference']],
            ]);
        }

        $reference = basename((string) parse_url($request->url(), PHP_URL_PATH));
        $overrides = $verify($reference, (float) ($expected[$reference] ?? 0.0)) ?? [];

        return Http::response(array_merge([
            'status' => true,
            'data' => array_merge([
                'status' => 'success',
                'amount' => $expected[$reference] ?? 0.0,
                'currency' => 'NGN',
                'reference' => $reference,
            ], $overrides),
        ]));
    });
}

test('a purchase is not approved until the gateway verifies the payment', function () {
    // Korapay first says "still pending" — nothing may be credited, and nothing may be lost either.
    // (The gate is stateful: Http::fake() MERGES closures, so a second registration would be
    // shadowed by this one — the gateway's answer has to be flipped instead.)
    $paid = false;

    fakeKorapayGate(function (string $reference) use (&$paid): array {
        return $paid ? [] : ['status' => 'pending'];
    });

    $user = guardUser();
    $start = app(StoragePurchaseService::class)->start($user, guardPlan(), 'activate');

    expect(app(ChargeSettlement::class)->finalize($start['reference']))->toBe('pending')
        ->and(Transaction::where('reference', $start['reference'])->value('status'))->toBe('pending')
        ->and($user->cloudStorageTopUps()->count())->toBe(0)
        ->and(PaymentReference::query()->count())->toBe(0);

    // The gateway then confirms it: the SAME charge now settles exactly once.
    $paid = true;

    expect(app(ChargeSettlement::class)->finalize($start['reference']))->toBe('completed')
        ->and($user->cloudStorageTopUps()->active()->count())->toBe(1)
        ->and(PaymentReference::query()->count())->toBe(1);

    // …and a replayed settle (webhook + callback + sweep all calling in) is a no-op.
    expect(app(ChargeSettlement::class)->finalize($start['reference']))->toBe('completed')
        ->and($user->cloudStorageTopUps()->count())->toBe(1)
        ->and(PaymentReference::query()->count())->toBe(1);
});

test('a charge whose verification fails reconciliation is never credited nor recorded', function () {
    // The gateway confirms a payment, but for the WRONG amount — an underpayment cannot buy the plan.
    fakeKorapayGate(fn (string $reference, float $amount) => ['amount' => $amount - 1]);

    $user = guardUser();
    $start = app(StoragePurchaseService::class)->start($user, guardPlan(), 'activate');

    expect(app(StoragePurchaseService::class)->finalize($start['reference']))->toBe('failed')
        ->and($user->cloudStorageTopUps()->count())->toBe(0)
        // No ledger row: the reference was never consumed, so support can see it was never credited.
        ->and(PaymentReference::query()->count())->toBe(0);
});

test('one gateway payment can never settle two charges', function () {
    // Both charges verify to the SAME provider transaction. That means one real payment is being
    // replayed — only the first charge may be credited.
    fakeKorapayGate(fn (string $reference) => ['transaction_reference' => 'KPY-ONE-SINGLE-PAYMENT']);

    $first = guardUser();
    $second = guardUser();

    $chargeA = app(StoragePurchaseService::class)->start($first, guardPlan(), 'activate');
    $chargeB = app(StoragePurchaseService::class)->start($second, guardPlan(), 'activate');

    expect(app(StoragePurchaseService::class)->finalize($chargeA['reference']))->toBe('completed')
        ->and(PaymentReference::query()->where('gateway_reference', 'KPY-ONE-SINGLE-PAYMENT')->count())->toBe(1);

    expect(app(StoragePurchaseService::class)->finalize($chargeB['reference']))->toBe('failed');

    $secondCharge = Transaction::where('reference', $chargeB['reference'])->first();

    expect($secondCharge->status)->toBe('failed')
        ->and($secondCharge->meta['manual_review']['reason'] ?? null)->toBe('duplicate_reference')
        ->and($second->cloudStorageTopUps()->count())->toBe(0)
        ->and(PaymentReference::query()->count())->toBe(1);
});

test('a reference that has already settled something can never be credited again', function () {
    fakeKorapayGate(fn (string $reference) => []);

    $user = guardUser();
    $start = app(StoragePurchaseService::class)->start($user, guardPlan(), 'activate');

    // Simulate an earlier settlement of this very reference (e.g. restored backup, edited row).
    PaymentReference::create([
        'reference' => $start['reference'],
        'user_id' => $user->id,
        'provider' => 'korapay',
        'type' => TransactionType::STORAGE_PURCHASE->value,
        'currency' => 'NGN',
        'amount_ngn' => 7500,
        'settled_at' => now()->subDay(),
    ]);

    expect(app(StoragePurchaseService::class)->finalize($start['reference']))->toBe('failed')
        ->and($user->cloudStorageTopUps()->count())->toBe(0)
        ->and(PaymentReference::query()->count())->toBe(1);
});

test('the ledger records one reconciled settlement per charge, with the provider transaction', function () {
    fakeKorapayGate(fn (string $reference) => ['transaction_reference' => 'kpy_'.strtolower($reference)]);

    // Activation is only offered to an un-activated account, so this buyer starts without a package.
    $user = User::factory()->create(['package_id' => null, 'country' => 'NG']);
    $start = app(PackagePurchaseService::class)->start($user, Package::where('tier_level', 2)->first(), 'activate');

    expect($start['ok'])->toBeTrue()
        ->and(app(PackagePurchaseService::class)->finalize($start['reference']))->toBe('completed');

    $entry = PaymentReference::query()->sole();

    expect($entry->reference)->toBe($start['reference'])
        ->and($entry->type)->toBe(TransactionType::PACKAGE_PURCHASE->value)
        ->and($entry->provider)->toBe('korapay')
        ->and($entry->user_id)->toBe($user->id)
        ->and($entry->currency)->toBe('NGN')
        ->and((float) $entry->amount_ngn)->toBe(30000.0)
        ->and($entry->gateway_reference)->toBe('kpy_'.strtolower($start['reference']))
        ->and(app(PaymentReferenceLedger::class)->isSettled($start['reference']))->toBeTrue();
});

test('a charge that cannot grant its entitlement stays failed, flagged and un-creditable', function () {
    fakeKorapayGate(fn (string $reference) => []);

    // Activation is only offered to an un-activated account.
    $user = User::factory()->create(['package_id' => null, 'country' => 'NG']);
    $start = app(PackagePurchaseService::class)->start($user, Package::where('tier_level', 2)->first(), 'activate');

    expect($start['ok'])->toBeTrue();

    // The account gets activated by a code while the card charge is still pending.
    $user->forceFill(['package_id' => Package::where('tier_level', 2)->first()->id])->save();

    expect(app(PackagePurchaseService::class)->finalize($start['reference']))->toBe('failed');

    $charge = Transaction::where('reference', $start['reference'])->first();

    expect($charge->status)->toBe('failed')
        ->and($charge->meta['manual_review']['reason'] ?? null)->toBe('entitlement_not_granted');

    // The reference stays consumed: the money arrived against it, so it can never be re-credited.
    expect(app(PaymentReferenceLedger::class)->isSettled($start['reference']))->toBeTrue()
        ->and(app(PackagePurchaseService::class)->finalize($start['reference']))->toBe('failed');
});

test('the requery sweep settles a genuine charge but never invents a credit', function () {
    // One stateful gate: the sweep must be safe to run twice, so the gateway's answer is what
    // changes between the two runs (not the fake itself).
    $paid = false;

    fakeKorapayGate(function (string $reference) use (&$paid): array {
        return $paid ? [] : ['status' => 'pending'];
    });

    $user = guardUser();
    $start = app(StoragePurchaseService::class)->start($user, guardPlan(), 'activate');

    $this->travel(4)->minutes();
    $this->artisan('payments:requery-pending')->assertSuccessful();

    // Still pending at the gateway → still no entitlement.
    expect(Transaction::where('reference', $start['reference'])->value('status'))->toBe('pending')
        ->and($user->cloudStorageTopUps()->count())->toBe(0);

    // The gateway now has it: the next sweep settles it, exactly once.
    $paid = true;
    $this->artisan('payments:requery-pending')->assertSuccessful();

    expect(Transaction::where('reference', $start['reference'])->value('status'))->toBe('completed')
        ->and($user->cloudStorageTopUps()->active()->count())->toBe(1)
        ->and(PaymentReference::query()->count())->toBe(1);
});
