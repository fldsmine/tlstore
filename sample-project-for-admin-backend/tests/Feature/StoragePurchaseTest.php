<?php

use App\Enums\TransactionType;
use App\Models\CloudStoragePlan;
use App\Models\CloudStorageTopUp;
use App\Models\Country;
use App\Models\Currency;
use App\Models\Package;
use App\Models\Transaction;
use App\Models\User;
use App\Services\Payments\ChargeSettlement;
use App\Services\Payments\StoragePurchaseService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as ClientRequest;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

beforeEach(function () {
    // ₦1,500 / $1 — so the NGN the gateway is asked for is a real conversion of the USD price.
    Currency::create(['code' => 'NGN', 'name' => 'Nigerian Naira', 'symbol' => '₦', 'usd_exchange_rate' => 1500, 'is_active' => true]);
    Country::create(['name' => 'Nigeria', 'code' => 'NG', 'currency_code' => 'NGN', 'is_active' => true]);

    Package::create([
        'name' => 'Royal eSIM',
        'tier_level' => 2,
        'enable_cloud' => true,
        'cloud_calls' => true,
        'cloud_sms' => true,
        'cloud_contact_limit' => 10,
        'cloud_sms_limit' => 20,
    ]);

    // Tier-tagged plan: coupon-only, never directly purchasable.
    CloudStoragePlan::create([
        'name' => 'Tier 2 Special Storage',
        'extra_contacts' => 7,
        'tier_level' => 2,
        'is_active' => true,
    ]);

    CloudStoragePlan::create([
        'name' => 'Starter Storage',
        'extra_contacts' => 5,
        'extra_sms' => 5,
        'duration_days' => 30,
        'token_price' => 5,
        'renewal_token_price' => 3,
        'referral_bonus' => 0.5,
        'is_active' => true,
    ]);

    // A live Korapay: secret key set, so the storage purpose resolves to a usable gateway.
    config([
        'settings.payment.storage_provider' => 'korapay',
        'services.korapay.base_url' => 'https://api.korapay.com',
        'services.korapay.secret_key' => 'sk_test_korapay',
        'services.korapay.currency' => 'NGN',
    ]);
});

/** A Cloud-calling user (storage plans only make sense for them). */
function storagePurchaseUser(array $attributes = []): User
{
    return User::factory()->create(array_merge([
        'package_id' => Package::where('tier_level', 2)->first()->id,
        'transaction_pin' => bcrypt('1234'),
        'country' => 'NG',
    ], $attributes));
}

function storagePurchasePlan(): CloudStoragePlan
{
    return CloudStoragePlan::whereNull('tier_level')->first();
}

/**
 * Fake the Korapay API: initialize returns a checkout URL (remembering each charge's NGN amount),
 * verify echoes that amount back as paid — unless overrides make it mismatch.
 *
 * @param  array<string, mixed>  $verifyOverrides  e.g. ['amount' => 1.0, 'currency' => 'USD', 'status' => 'failed']
 */
function fakeKorapayApi(array $verifyOverrides = []): void
{
    $expected = [];

    Http::fake(function (ClientRequest $request) use (&$expected, $verifyOverrides) {
        if (str_contains($request->url(), 'charges/initialize')) {
            $body = $request->data();
            $expected[$body['reference']] = (float) $body['amount'];

            return Http::response([
                'status' => true,
                'data' => ['checkout_url' => 'https://checkout.korapay.com/'.$body['reference'], 'reference' => 'kpy_'.$body['reference']],
            ]);
        }

        $reference = basename((string) parse_url($request->url(), PHP_URL_PATH));

        return Http::response([
            'status' => true,
            'data' => array_merge([
                'status' => 'success',
                'amount' => $expected[$reference] ?? 0.0,
                'currency' => 'NGN',
                'reference' => $reference,
            ], $verifyOverrides),
        ]);
    });
}

test('starting a storage purchase creates a pending debit charge pinned to the gateway', function () {
    fakeKorapayApi();

    $user = storagePurchaseUser();
    $plan = storagePurchasePlan();

    $result = app(StoragePurchaseService::class)->start($user, $plan, 'activate');

    expect($result['ok'])->toBeTrue();
    expect($result['checkout_url'])->toBe('https://checkout.korapay.com/'.$result['reference']);

    $transaction = Transaction::where('reference', $result['reference'])->first();

    expect($transaction)->not->toBeNull();
    expect($transaction->user_id)->toBe($user->id);
    expect($transaction->type)->toBe(TransactionType::STORAGE_PURCHASE);
    expect($transaction->direction)->toBe('debit');
    expect($transaction->wallet)->toBeNull();
    expect($transaction->status)->toBe('pending');
    expect($transaction->provider)->toBe('korapay');
    expect($transaction->meta['plan_id'])->toBe($plan->id);
    expect($transaction->meta['mode'])->toBe('activate');
    expect((float) $transaction->meta['amount_ngn'])->toBe(7500.0); // $5 × ₦1,500
    expect($transaction->meta['currency'])->toBe('NGN');

    // Nothing is granted until the charge is actually settled.
    expect($user->cloudStorageTopUps()->count())->toBe(0);
});

test('the callback settles the charge and grants the pack exactly once', function () {
    fakeKorapayApi();

    $user = storagePurchaseUser();
    $plan = storagePurchasePlan();

    $start = app(StoragePurchaseService::class)->start($user, $plan, 'activate');

    $this->actingAs($user)
        ->get(route('cloud.storage.buy.callback', ['reference' => $start['reference']]))
        ->assertRedirect(route('cloud'));

    expect(Transaction::where('reference', $start['reference'])->value('status'))->toBe('completed');

    $topUp = CloudStorageTopUp::where('user_id', $user->id)->first();
    expect($topUp)->not->toBeNull();
    expect($topUp->cloud_storage_plan_id)->toBe($plan->id);
    expect($topUp->coupon_id)->toBeNull();
    expect($topUp->extra_contacts)->toBe(5);
    expect($topUp->extra_sms)->toBe(5);
    expect($topUp->expires_at->isFuture())->toBeTrue();

    // The Cloud line is provisioned alongside the pack, exactly like a redeemed token.
    expect($user->refresh()->cloud_line_number)->not->toBeNull();

    // Re-finalizing (double callback / webhook racing the redirect) must not grant a second pack.
    expect(app(StoragePurchaseService::class)->finalize($start['reference']))->toBe('completed');
    expect(CloudStorageTopUp::where('user_id', $user->id)->count())->toBe(1);
});

test('settlement routes a storage purchase by its row type', function () {
    fakeKorapayApi();

    $user = storagePurchaseUser();
    $plan = storagePurchasePlan();

    $start = app(StoragePurchaseService::class)->start($user, $plan, 'activate');

    // ChargeSettlement resolves the service from the row's TYPE — the webhook, the callback and the
    // requery sweep all come through here.
    expect(app(ChargeSettlement::class)->finalize($start['reference']))->toBe('completed');
    expect($user->cloudStorageTopUps()->active()->count())->toBe(1);
});

test('a direct purchase replaces the current pack and pays the plan Connect Bonus', function () {
    fakeKorapayApi();

    $upline = storagePurchaseUser(['referrer_id' => null]);
    $user = storagePurchaseUser(['referrer_id' => $upline->id]);

    $oldPlan = CloudStoragePlan::create([
        'name' => 'Old Storage',
        'extra_contacts' => 2,
        'duration_days' => 30,
        'token_price' => 2,
        'is_active' => true,
    ]);

    $user->cloudStorageTopUps()->create([
        'cloud_storage_plan_id' => $oldPlan->id,
        'extra_contacts' => 2,
        'extra_sms' => 0,
    ]);

    $plan = storagePurchasePlan();
    $start = app(StoragePurchaseService::class)->start($user, $plan, 'switch');

    app(StoragePurchaseService::class)->finalize($start['reference']);

    // One plan at a time: the old pack is expired, the new one is active.
    expect(CloudStorageTopUp::where('user_id', $user->id)->count())->toBe(2);
    $old = CloudStorageTopUp::where('cloud_storage_plan_id', $oldPlan->id)->first();
    expect($old->expires_at->isPast())->toBeTrue();
    expect($user->cloudStorageTopUps()->active()->count())->toBe(1);

    // Same Connect Bonus a redeemed token pays.
    expect((float) $upline->refresh()->referral_bonus_balance)->toBe(0.5);
});

test('an underpaid charge fails without granting the pack', function () {
    fakeKorapayApi(['amount' => 100.0]); // far less than the ₦7,500 asked

    $user = storagePurchaseUser();
    $start = app(StoragePurchaseService::class)->start($user, storagePurchasePlan(), 'activate');

    $status = app(StoragePurchaseService::class)->finalize($start['reference']);

    expect($status)->toBe('failed');
    expect(Transaction::where('reference', $start['reference'])->value('status'))->toBe('failed');
    expect(CloudStorageTopUp::where('user_id', $user->id)->count())->toBe(0);
});

test('a charge settled in a foreign currency fails without granting the pack', function () {
    fakeKorapayApi(['currency' => 'USD']);

    $user = storagePurchaseUser();
    $start = app(StoragePurchaseService::class)->start($user, storagePurchasePlan(), 'activate');

    $status = app(StoragePurchaseService::class)->finalize($start['reference']);

    expect($status)->toBe('failed');
    expect(CloudStorageTopUp::where('user_id', $user->id)->count())->toBe(0);
});

test('a failed charge never grants the pack', function () {
    fakeKorapayApi(['status' => 'failed']);

    $user = storagePurchaseUser();
    $start = app(StoragePurchaseService::class)->start($user, storagePurchasePlan(), 'activate');

    expect(app(StoragePurchaseService::class)->finalize($start['reference']))->toBe('failed');
    expect(CloudStorageTopUp::where('user_id', $user->id)->count())->toBe(0);
});

test('special tier plans are never directly purchasable', function () {
    fakeKorapayApi();

    $user = storagePurchaseUser();
    $tierPlan = CloudStoragePlan::where('tier_level', 2)->first();

    $result = app(StoragePurchaseService::class)->start($user, $tierPlan, 'activate');

    expect($result['ok'])->toBeFalse();
    expect(Transaction::where('type', TransactionType::STORAGE_PURCHASE->value)->count())->toBe(0);
});

test('a renewal price is only offered for the plan the user is on, near expiry', function () {
    fakeKorapayApi();

    $user = storagePurchaseUser();
    $plan = storagePurchasePlan();

    // No pack yet → a renewal is not a renewal at all.
    expect(app(StoragePurchaseService::class)->start($user, $plan, 'renew')['ok'])->toBeFalse();

    // A pack far from expiry cannot be renewed early.
    $user->cloudStorageTopUps()->create([
        'cloud_storage_plan_id' => $plan->id,
        'extra_contacts' => 5,
        'expires_at' => now()->addDays(20),
    ]);

    expect(app(StoragePurchaseService::class)->start($user, $plan, 'renew')['ok'])->toBeFalse();

    // Inside the window (expires tomorrow) it works, at the renewal price × ₦1,500.
    $user->cloudStorageTopUps()->update(['expires_at' => now()->addDay()]);

    $result = app(StoragePurchaseService::class)->start($user, $plan, 'renew');

    expect($result['ok'])->toBeTrue();
    expect((float) Transaction::where('reference', $result['reference'])->first()->meta['amount_ngn'])->toBe(4500.0);
});

test('direct purchase is refused when the gateway has no keys and the vendor anchor stands alone', function () {
    config(['services.korapay.secret_key' => '']);

    $user = storagePurchaseUser();
    $result = app(StoragePurchaseService::class)->start($user, storagePurchasePlan(), 'activate');

    expect($result['ok'])->toBeFalse();
    expect(Transaction::where('type', TransactionType::STORAGE_PURCHASE->value)->count())->toBe(0);

    // The plan picker shows the vendor fallback, never a dead Pay button.
    $this->actingAs($user)
        ->get(route('cloud', ['storage' => 1]))
        ->assertOk()
        ->assertSee('Buy a storage plan from a vendor')
        ->assertDontSee('Pay directly with card');
});

test('the cloud page offers Pay directly next to the vendor anchor', function () {
    fakeKorapayApi();

    $user = storagePurchaseUser();

    $response = $this->actingAs($user)->get(route('cloud', ['storage' => 1]));

    $response->assertOk()
        ->assertSee('Pay directly with card')
        ->assertSee('Buy a storage plan from a vendor')
        ->assertSee(route('cloud.storage.buy.start'), false)
        ->assertSee('name="plan_id"', false)
        ->assertSee('name="mode"', false);
});
