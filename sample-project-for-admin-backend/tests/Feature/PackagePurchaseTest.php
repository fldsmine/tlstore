<?php

use App\Enums\TransactionType;
use App\Models\AllocatedNumber;
use App\Models\Country;
use App\Models\Currency;
use App\Models\Package;
use App\Models\ShareSubscription;
use App\Models\Transaction;
use App\Models\User;
use App\Livewire\Dashboard\Activate;
use App\Services\Payments\ChargeSettlement;
use App\Services\Payments\PackagePurchaseService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as ClientRequest;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    Currency::create(['code' => 'NGN', 'name' => 'Nigerian Naira', 'symbol' => '₦', 'usd_exchange_rate' => 1500, 'is_active' => true]);
    Country::create(['name' => 'Nigeria', 'code' => 'NG', 'currency_code' => 'NGN', 'is_active' => true]);

    Package::create([
        'name' => 'Diamond eSIM',
        'tier_level' => 1,
        'price' => 10,
        'upgrade_price' => 0,
        'daily_call_minutes' => 60,
        'call_id_limit' => 2,
        'is_active' => true,
    ]);

    Package::create([
        'name' => 'Royal eSIM',
        'tier_level' => 2,
        'price' => 20,
        'upgrade_price' => 12,
        'daily_call_minutes' => 120,
        'call_id_limit' => 5,
        'is_active' => true,
    ]);

    config([
        'settings.payment.package_provider' => 'korapay',
        'services.korapay.base_url' => 'https://api.korapay.com',
        'services.korapay.secret_key' => 'sk_test_korapay',
        'services.korapay.currency' => 'NGN',
    ]);
});

function packagePurchaseTier(int $tier): Package
{
    return Package::where('tier_level', $tier)->first();
}

/** Fake Korapay initialize/verify for a package purchase. */
function fakeKorapayForPackage(): void
{
    $expected = [];

    Http::fake(function (ClientRequest $request) use (&$expected) {
        if (str_contains($request->url(), 'charges/initialize')) {
            $body = $request->data();
            $expected[$body['reference']] = (float) $body['amount'];

            return Http::response([
                'status' => true,
                'data' => ['checkout_url' => 'https://checkout.korapay.com/'.$body['reference'], 'reference' => 'kpy_'.$body['reference']],
            ]);
        }

        $reference = basename((string) parse_url($request->url(), PHP_URL_PATH));

        $data = [
            'status' => 'success',
            'amount' => $expected[$reference] ?? 0.0,
            'currency' => 'NGN',
            'reference' => $reference,
        ];

        return Http::response(['status' => true, 'data' => $data]);
    });
}

test('paying for a first activation grants the package and its Call IDs once', function () {
    fakeKorapayForPackage();

    $user = User::factory()->create(['package_id' => null, 'country' => 'NG']);
    $package = packagePurchaseTier(1);

    $start = app(PackagePurchaseService::class)->start($user, $package, 'activate');

    expect($start['ok'])->toBeTrue();

    $transaction = Transaction::where('reference', $start['reference'])->first();
    expect($transaction->type)->toBe(TransactionType::PACKAGE_PURCHASE);
    expect($transaction->direction)->toBe('debit');
    expect($transaction->status)->toBe('pending');
    expect($transaction->provider)->toBe('korapay');
    expect($transaction->meta['package_id'])->toBe($package->id);
    expect($transaction->meta['mode'])->toBe('activate');
    expect((float) $transaction->meta['amount_ngn'])->toBe(15000.0); // $10 × ₦1,500
    expect($user->refresh()->package_id)->toBeNull(); // not granted before settlement

    $this->actingAs($user)
        ->get(route('plans.buy.callback', ['reference' => $start['reference']]))
        ->assertRedirect(route('dashboard'));

    expect($user->refresh()->package_id)->toBe($package->id);
    expect(AllocatedNumber::where('user_id', $user->id)->count())->toBe(2);
    expect(Transaction::where('reference', $start['reference'])->value('status'))->toBe('completed');

    // A second settle (webhook after the redirect) is a no-op — never activated twice.
    expect(app(PackagePurchaseService::class)->finalize($start['reference']))->toBe('completed');
    expect(AllocatedNumber::where('user_id', $user->id)->count())->toBe(2);
});

test('paying for an upgrade moves the account up and tops up its Call IDs', function () {
    fakeKorapayForPackage();

    $user = User::factory()->create([
        'package_id' => packagePurchaseTier(1)->id,
        'country' => 'NG',
        'transaction_pin' => bcrypt('1234'),
    ]);

    $package = packagePurchaseTier(2);

    $start = app(PackagePurchaseService::class)->start($user, $package, 'upgrade');
    expect($start['ok'])->toBeTrue();

    // Upgrades are priced from upgrade_price ($12), not the shelf price.
    expect((float) Transaction::where('reference', $start['reference'])->first()->meta['amount_ngn'])->toBe(18000.0);

    expect(app(PackagePurchaseService::class)->finalize($start['reference']))->toBe('completed');

    expect($user->refresh()->package_id)->toBe($package->id);
    expect(AllocatedNumber::where('user_id', $user->id)->where('source', 'package')->count())->toBe(5);
});

test('a charge from an account activated in the meantime fails and is flagged, never credited', function () {
    fakeKorapayForPackage();

    $user = User::factory()->create(['package_id' => null, 'country' => 'NG']);
    $package = packagePurchaseTier(1);

    $start = app(PackagePurchaseService::class)->start($user, $package, 'activate');
    expect($start['ok'])->toBeTrue();

    // While the card charge is pending the user redeems a token and activates.
    $user->forceFill(['package_id' => packagePurchaseTier(2)->id])->save();

    $status = app(PackagePurchaseService::class)->finalize($start['reference']);

    expect($status)->toBe('failed');

    $transaction = Transaction::where('reference', $start['reference'])->first();
    expect($transaction->status)->toBe('failed');
    expect($transaction->meta['manual_review']['reason'])->toBe('entitlement_not_granted');

    // The token's activation stands untouched — no downgrade to the paid package.
    expect($user->refresh()->package_id)->toBe(packagePurchaseTier(2)->id);
});

test('an account already activated cannot start a first-activation purchase', function () {
    fakeKorapayForPackage();

    $user = User::factory()->create(['package_id' => packagePurchaseTier(1)->id, 'country' => 'NG']);

    $result = app(PackagePurchaseService::class)->start($user, packagePurchaseTier(2), 'activate');

    expect($result['ok'])->toBeFalse();
    expect(Transaction::where('type', TransactionType::PACKAGE_PURCHASE->value)->count())->toBe(0);
});

test('an upgrade must go to a higher tier — and must still be higher when the charge settles', function () {
    fakeKorapayForPackage();

    $user = User::factory()->create(['package_id' => packagePurchaseTier(2)->id, 'country' => 'NG']);

    // Lower tier: refused up front, nothing is charged.
    expect(app(PackagePurchaseService::class)->start($user, packagePurchaseTier(1), 'upgrade')['ok'])->toBeFalse();
    expect(Transaction::where('type', TransactionType::PACKAGE_PURCHASE->value)->count())->toBe(0);

    // Downgraded while pending (e.g. a tier change by support) → the settled charge is refused,
    // failed and flagged rather than overwriting the account's package.
    $startingUser = User::factory()->create(['package_id' => packagePurchaseTier(1)->id, 'country' => 'NG']);
    $start = app(PackagePurchaseService::class)->start($startingUser, packagePurchaseTier(2), 'upgrade');

    $startingUser->forceFill(['package_id' => packagePurchaseTier(2)->id])->save();

    expect(app(PackagePurchaseService::class)->finalize($start['reference']))->toBe('failed');
    expect(Transaction::where('reference', $start['reference'])->value('status'))->toBe('failed');
    expect($startingUser->refresh()->package_id)->toBe(packagePurchaseTier(2)->id);
});

test('the activation and upgrade screens offer Pay directly alongside token redemption', function () {
    fakeKorapayForPackage();

    // Pre-activation: the activation page offers both ways in.
    $newcomer = User::factory()->create(['package_id' => null, 'country' => 'NG']);

    $this->actingAs($newcomer)
        ->get(route('activate'))
        ->assertOk()
        ->assertSee('Pay directly')
        ->assertSee(route('plans.buy.start'), false)
        ->assertSee('Purchase eSIM Code');

    // Activated: the upgrade modal (opened from the dashboard) offers the same.
    $member = User::factory()->create([
        'package_id' => packagePurchaseTier(1)->id,
        'country' => 'NG',
        'transaction_pin' => bcrypt('1234'),
    ]);

    $this->actingAs($member);

    Livewire::test(Activate::class)
        ->call('show')
        ->assertSee('Pay directly')
        ->assertSee('name="mode" value="upgrade"', false);
});

test('direct purchase is refused without gateway keys', function () {
    config(['services.korapay.secret_key' => '']);

    $user = User::factory()->create(['package_id' => null, 'country' => 'NG']);

    $result = app(PackagePurchaseService::class)->start($user, packagePurchaseTier(1), 'activate');

    expect($result['ok'])->toBeFalse();
    expect(Transaction::where('type', TransactionType::PACKAGE_PURCHASE->value)->count())->toBe(0);
});

test('settlement routes a package purchase by its row type', function () {
    fakeKorapayForPackage();

    $user = User::factory()->create(['package_id' => null, 'country' => 'NG']);
    $start = app(PackagePurchaseService::class)->start($user, packagePurchaseTier(1), 'activate');

    // ChargeSettlement (webhook / callback / sweep) resolves the service from the row's TYPE.
    expect(app(ChargeSettlement::class)->finalize($start['reference']))->toBe('completed');
    expect($user->refresh()->package_id)->toBe(packagePurchaseTier(1)->id);
});

test('the requery sweep picks up a still-pending package purchase and settles it', function () {
    fakeKorapayForPackage();

    $user = User::factory()->create(['package_id' => null, 'country' => 'NG']);
    $start = app(PackagePurchaseService::class)->start($user, packagePurchaseTier(1), 'activate');

    expect(Transaction::where('reference', $start['reference'])->value('status'))->toBe('pending');

    // The sweep only touches charges older than 3 minutes (so a live checkout isn't interfered with).
    $this->travel(4)->minutes();

    $this->artisan('payments:requery-pending')
        ->expectsOutputToContain('Dispatched 1 charge requery job(s).')
        ->assertSuccessful();

    expect(Transaction::where('reference', $start['reference'])->value('status'))->toBe('completed');
    expect($user->refresh()->package_id)->toBe(packagePurchaseTier(1)->id);
});

test('a paid share renewal starts a fresh term without reactivating eSIM assets', function () {
    fakeKorapayForPackage();

    $package = packagePurchaseTier(1);
    $package->update([
        'monthly_share_minutes' => 300,
        'share_term_days' => 30,
        'max_daily_shareholder_earning' => 15,
    ]);
    $user = User::factory()->create([
        'package_id' => $package->id,
        'country' => 'NG',
    ]);
    $oldSubscription = ShareSubscription::query()->create([
        'user_id' => $user->id,
        'package_id' => $package->id,
        'allocated_minutes' => 80,
        'daily_earning_cap' => 5,
        'starts_at' => now()->subDays(31),
        'expires_at' => now()->subDay(),
        'status' => 'active',
    ]);

    $start = app(PackagePurchaseService::class)->start($user, $package, PackagePurchaseService::MODE_RENEW);

    expect($start['ok'])->toBeTrue();
    $renewalTransaction = Transaction::where('reference', $start['reference'])->firstOrFail();
    expect($renewalTransaction->meta['mode'])->toBe('renew')
        ->and($renewalTransaction->description)->toContain('Share plan renewal');
    expect(app(PackagePurchaseService::class)->finalize($start['reference']))->toBe('completed');

    $user->refresh();
    $renewed = app(\App\Services\ShareSubscriptionService::class)->current($user);

    expect($user->package_id)->toBe($package->id)
        ->and($renewed)->not->toBeNull()
        ->and($renewed->id)->not->toBe($oldSubscription->id)
        ->and((float) $renewed->allocated_minutes)->toBe(300.0)
        ->and((float) $renewed->daily_earning_cap)->toBe(15.0)
        ->and($oldSubscription->refresh()->status)->toBe('expired')
        ->and(AllocatedNumber::where('user_id', $user->id)->count())->toBe(0);
});

test('an active share term cannot create an early renewal charge', function () {
    fakeKorapayForPackage();

    $package = packagePurchaseTier(1);
    $package->update([
        'monthly_share_minutes' => 300,
        'share_term_days' => 30,
        'max_daily_shareholder_earning' => 15,
    ]);
    $user = User::factory()->create([
        'package_id' => $package->id,
        'country' => 'NG',
    ]);
    ShareSubscription::query()->create([
        'user_id' => $user->id,
        'package_id' => $package->id,
        'allocated_minutes' => 300,
        'daily_earning_cap' => 15,
        'starts_at' => now(),
        'expires_at' => now()->addDays(30),
        'status' => 'active',
    ]);

    $result = app(PackagePurchaseService::class)->start($user, $package, PackagePurchaseService::MODE_RENEW);

    expect($result['ok'])->toBeFalse()
        ->and(Transaction::where('type', TransactionType::PACKAGE_PURCHASE->value)->count())->toBe(0);
});

test('package purchase uses the currently routed payment channel', function () {
    config([
        'settings.payment.package_provider' => 'manual',
        'settings.payment.manual_bank_account_number' => '0123456789',
        'settings.payment.manual_bank_name' => 'Example Bank',
        'settings.payment.manual_bank_account_name' => 'Nextel Connect',
    ]);

    $user = User::factory()->create(['package_id' => null, 'country' => 'NG']);
    $package = packagePurchaseTier(1);

    $start = app(PackagePurchaseService::class)->start($user, $package, PackagePurchaseService::MODE_ACTIVATE);

    expect($start['ok'])->toBeTrue()
        ->and($start['checkout_url'])->toContain('/manual-payment/');

    $transaction = Transaction::where('reference', $start['reference'])->firstOrFail();
    expect($transaction->provider)->toBe('manual')
        ->and($transaction->status)->toBe('pending')
        ->and($user->refresh()->package_id)->toBeNull();
});
