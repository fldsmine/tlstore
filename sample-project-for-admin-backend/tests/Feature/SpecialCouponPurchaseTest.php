<?php

use App\Enums\CouponType;
use App\Enums\TransactionType;
use App\Livewire\Backoffice\Settings\GeneralSetting;
use App\Livewire\Backoffice\Users\ShowUser;
use App\Livewire\Dashboard\SpecialCoupon\Activate as ActivateRevenueCode;
use App\Models\Admin;
use App\Models\Bank;
use App\Models\BankAccount;
use App\Models\CloudStoragePlan;
use App\Models\CloudStorageTopUp;
use App\Models\Country;
use App\Models\Coupon;
use App\Models\Currency;
use App\Models\Package;
use App\Models\Setting;
use App\Models\Transaction;
use App\Models\User;
use App\Services\Payments\ChargeSettlement;
use App\Services\Payments\RevenueCodeService;
use App\Services\Payments\SpecialCouponPurchaseService;
use App\Services\WithdrawalService;
use App\Support\PayoutSupport;
use App\Support\SettingMapper;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as ClientRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

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

    // The tier plan the card purchase sells — $20 for the buyer's region.
    CloudStoragePlan::create([
        'name' => 'Tier 2 Revenue Storage',
        'extra_contacts' => 7,
        'extra_sms' => 3,
        'duration_days' => 30,
        'token_price' => 20,
        'foreign_token_price' => 25,
        'referral_bonus' => 0.5,
        'tier_level' => 2,
        'is_active' => true,
    ]);

    // A plan for another tier — a crafted request must never be able to buy this one.
    CloudStoragePlan::create([
        'name' => 'Tier 1 Revenue Storage',
        'extra_contacts' => 4,
        'token_price' => 8,
        'tier_level' => 1,
        'is_active' => true,
    ]);

    // An ordinary storage plan: NOT a revenue code (no tier tag).
    CloudStoragePlan::create([
        'name' => 'Starter Storage',
        'extra_contacts' => 5,
        'token_price' => 5,
        'is_active' => true,
    ]);

    Bank::create(['name' => 'Test Bank', 'code' => 'TESTBANK', 'tag' => 'test', 'is_active' => true]);

    config([
        'settings.payment.special_coupon_provider' => 'korapay',
        'services.korapay.base_url' => 'https://api.korapay.com',
        'services.korapay.secret_key' => 'sk_test_korapay',
        'services.korapay.currency' => 'NGN',
    ]);
});

/** A Cloud-calling, withdrawable user on the tier revenue codes are published for. */
function revenueCodeUser(array $attributes = []): User
{
    $user = User::factory()->create(array_merge([
        'package_id' => Package::where('tier_level', 2)->first()->id,
        'cloud_call_balance' => 50.0,
        'transaction_pin' => bcrypt('1234'),
        'country' => 'NG',
    ], $attributes));

    BankAccount::create([
        'user_id' => $user->id,
        'bank_id' => Bank::first()->id,
        'account_number' => '1234567890',
        'account_name' => 'Test Account',
    ]);

    return $user;
}

function revenueCodePlan(): CloudStoragePlan
{
    return CloudStoragePlan::where('tier_level', 2)->first();
}

/**
 * Fake the Korapay charge API: initialize returns a checkout URL (remembering each charge's NGN
 * amount), verify echoes that amount back as paid — unless an override changes the answer.
 *
 * @param  array<string, mixed>  $verifyOverrides  e.g. ['amount' => 1.0, 'status' => 'pending']
 */
function fakeKorapayForRevenueCode(array $verifyOverrides = []): void
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

// ---------------------------------------------------------------------------
// Starting the purchase
// ---------------------------------------------------------------------------

test('paying directly with card creates a pending charge for the tier plan at the customer price', function () {
    fakeKorapayForRevenueCode();

    $user = revenueCodeUser();
    $plan = revenueCodePlan();

    $result = app(SpecialCouponPurchaseService::class)->start($user);

    expect($result['ok'])->toBeTrue();
    expect($result['checkout_url'])->toBe('https://checkout.korapay.com/'.$result['reference']);

    $transaction = Transaction::where('reference', $result['reference'])->first();

    expect($transaction)->not->toBeNull();
    expect($transaction->user_id)->toBe($user->id);
    expect($transaction->type)->toBe(TransactionType::SPECIAL_COUPON_PURCHASE);
    expect($transaction->direction)->toBe('debit');
    expect($transaction->wallet)->toBeNull();
    expect($transaction->status)->toBe('pending');
    expect($transaction->provider)->toBe('korapay');
    expect($transaction->meta['plan_id'])->toBe($plan->id);
    expect((float) $transaction->amount)->toBe(20.0);                  // the tier plan's customer price
    expect((float) $transaction->meta['amount_ngn'])->toBe(30000.0);   // $20 × ₦1,500
    expect($transaction->meta['currency'])->toBe('NGN');

    // Nothing is issued — and no storage granted — until the charge is settled.
    expect(Coupon::where('payment_reference', $result['reference'])->count())->toBe(0);
    expect($user->cloudStorageTopUps()->count())->toBe(0);
});

test('the page offers the card option with the support link', function () {
    fakeKorapayForRevenueCode();

    // Full-page render, exactly as the user sees it: the option sits above the code-entry card, so
    // the vendor route stays visible underneath it.
    $this->actingAs(revenueCodeUser())
        ->get(route('special-coupon'))
        ->assertOk()
        ->assertSee('Pay directly with card')
        ->assertSee(route('special-coupon.buy.start'), false)
        ->assertSee('t.me/nextelSpecialSupport')
        ->assertSee('ENTER REVENUE CODE');
});

// ---------------------------------------------------------------------------
// Settling: the code is issued, bound, active, and spends like a vendor code
// ---------------------------------------------------------------------------

test('the callback settles the charge and issues an already-active code bound to the buyer', function () {
    fakeKorapayForRevenueCode();

    $user = revenueCodeUser();
    $plan = revenueCodePlan();

    $start = app(SpecialCouponPurchaseService::class)->start($user);

    $this->actingAs($user)
        ->get(route('special-coupon.buy.callback', ['reference' => $start['reference']]))
        ->assertRedirect(route('special-coupon'))
        // The callback tells the page which charge to show as "just issued" — no code travels in the URL.
        ->assertSessionHas('special_coupon_code_issued', $start['reference']);

    expect(Transaction::where('reference', $start['reference'])->value('status'))->toBe('completed');

    $coupon = Coupon::where('payment_reference', $start['reference'])->first();

    expect($coupon)->not->toBeNull();
    expect($coupon->type)->toBe(CouponType::STORAGE_ACTIVATION);
    expect($coupon->user_id)->toBe($user->id);        // bound: ready for the withdrawal authorisation
    expect($coupon->used_at)->toBeNull();             // …but NOT spent — the withdrawal consumes it
    expect($coupon->is_special)->toBeTrue();
    expect($coupon->source)->toBe(Coupon::SOURCE_DIRECT);
    expect($coupon->cloud_storage_plan_id)->toBe($plan->id);
    expect((float) $coupon->price)->toBe(20.0);
    expect($coupon->expires_at->toDateString())->toBe(now()->addDays(90)->toDateString());

    // The storage bonus is applied at settle time — the buyer does not activate anything.
    $topUp = CloudStorageTopUp::where('user_id', $user->id)->first();

    expect($topUp)->not->toBeNull();
    expect($topUp->cloud_storage_plan_id)->toBe($plan->id);
    expect($topUp->coupon_id)->toBe($coupon->id);
    expect($topUp->extra_contacts)->toBe(7);
    expect($topUp->extra_sms)->toBe(3);
    expect($topUp->expires_at->isFuture())->toBeTrue();
    expect($user->refresh()->cloud_line_number)->not->toBeNull();
});

test('the issued code authorises one withdrawal and is spent by it', function () {
    fakeKorapayForRevenueCode();

    $user = revenueCodeUser();

    $start = app(SpecialCouponPurchaseService::class)->start($user);
    app(SpecialCouponPurchaseService::class)->finalize($start['reference']);

    $coupon = Coupon::where('payment_reference', $start['reference'])->firstOrFail();
    $code = $coupon->code;

    // The very same code the buyer sees is the one the withdrawal server checks.
    expect(app(WithdrawalService::class)->validateAuthorizationCode($user, $code)->id)->toBe($coupon->id);

    $withdrawal = app(WithdrawalService::class)->request($user, 'cloud_call', 10.0, $code);

    expect($withdrawal)->not->toBeNull();
    expect((float) $user->refresh()->cloud_call_balance)->toBe(40.0);
    expect($coupon->refresh()->used_at)->not->toBeNull();

    // One code, one withdrawal — a second attempt with the same code is refused.
    expect(fn () => app(WithdrawalService::class)->request($user, 'cloud_call', 5.0, $code))
        ->toThrow(ValidationException::class);
});

test('settling twice never issues a second code, and the code is nobody else', function () {
    fakeKorapayForRevenueCode();

    $user = revenueCodeUser();
    $other = revenueCodeUser();

    $start = app(SpecialCouponPurchaseService::class)->start($user);

    app(SpecialCouponPurchaseService::class)->finalize($start['reference']);

    // Replaying the callback (webhook racing the redirect, requery sweep) must not mint a second code.
    expect(app(SpecialCouponPurchaseService::class)->finalize($start['reference']))->toBe('completed');
    expect(Coupon::where('payment_reference', $start['reference'])->count())->toBe(1);
    expect(CloudStorageTopUp::where('user_id', $user->id)->count())->toBe(1);

    $coupon = Coupon::where('payment_reference', $start['reference'])->firstOrFail();

    // The buyer can read it back on their page…
    $this->actingAs($user);

    Livewire::test(ActivateRevenueCode::class)->assertSee($coupon->code);

    // …but the code authorises nothing for anybody else.
    expect(fn () => app(WithdrawalService::class)->validateAuthorizationCode($other, $coupon->code))
        ->toThrow(ValidationException::class);
});

test('after the callback the page shows the issued code as brand new — and never a vendor code', function () {
    fakeKorapayForRevenueCode();

    $user = revenueCodeUser();

    // A vendor-minted code the user activated earlier: bound, unused — never displayed on the page.
    $vendorCode = Coupon::create([
        'code' => 'VENCODE123456',
        'type' => CouponType::STORAGE_ACTIVATION,
        'region' => Coupon::REGION_NIGERIA,
        'user_id' => $user->id,
        'cloud_storage_plan_id' => revenueCodePlan()->id,
        'expires_at' => now()->addDays(90),
    ]);

    expect($vendorCode->source)->toBe(Coupon::SOURCE_VENDOR);

    $this->actingAs($user);

    Livewire::test(ActivateRevenueCode::class)->assertDontSee('VENCODE123456');

    $start = app(SpecialCouponPurchaseService::class)->start($user);

    $this->get(route('special-coupon.buy.callback', ['reference' => $start['reference']]))
        ->assertRedirect(route('special-coupon'));

    $bought = Coupon::where('payment_reference', $start['reference'])->firstOrFail();

    // The very next page load turns the callback's flash into the "just issued" panel: the code is
    // readable, marked new, and the vendor code the user typed in is still not echoed back.
    $this->get(route('special-coupon'))
        ->assertOk()
        ->assertSee('Your code is ready')
        ->assertSee($bought->code)
        ->assertDontSee('VENCODE123456');
});

test('settlement routes a revenue-code purchase by its row type', function () {
    fakeKorapayForRevenueCode();

    $user = revenueCodeUser();

    $start = app(SpecialCouponPurchaseService::class)->start($user);

    // The webhook, the callback and the requery sweep all come through the shared router.
    expect(app(ChargeSettlement::class)->finalize($start['reference']))->toBe('completed');
    expect(Coupon::where('payment_reference', $start['reference'])->count())->toBe(1);
});

// ---------------------------------------------------------------------------
// Money safety
// ---------------------------------------------------------------------------

test('an underpaid charge fails without issuing a code', function () {
    fakeKorapayForRevenueCode(['amount' => 100.0]); // the gateway reports ₦100 of ₦30,000

    $user = revenueCodeUser();

    $start = app(SpecialCouponPurchaseService::class)->start($user);

    expect(app(SpecialCouponPurchaseService::class)->finalize($start['reference']))->toBe('failed');

    $transaction = Transaction::where('reference', $start['reference'])->first();

    expect($transaction->status)->toBe('failed');
    expect((float) $transaction->meta['reconcile_error']['amount'])->toBe(100.0);
    expect(Coupon::where('payment_reference', $start['reference'])->count())->toBe(0);
    expect($user->cloudStorageTopUps()->count())->toBe(0);
});

test('a charge the gateway still reports as pending stays pending and issues nothing', function () {
    fakeKorapayForRevenueCode(['status' => 'pending']);

    $user = revenueCodeUser();

    $start = app(SpecialCouponPurchaseService::class)->start($user);

    expect(app(SpecialCouponPurchaseService::class)->finalize($start['reference']))->toBe('pending');
    expect(Transaction::where('reference', $start['reference'])->value('status'))->toBe('pending');
    expect(Coupon::where('payment_reference', $start['reference'])->count())->toBe(0);
});

test('the money is never credited when the plan disappears between checkout and payment', function () {
    fakeKorapayForRevenueCode();

    $user = revenueCodeUser();

    $start = app(SpecialCouponPurchaseService::class)->start($user);

    revenueCodePlan()->update(['is_active' => false]);

    expect(app(SpecialCouponPurchaseService::class)->finalize($start['reference']))->toBe('failed');

    $transaction = Transaction::where('reference', $start['reference'])->first();

    expect($transaction->status)->toBe('failed');
    expect($transaction->meta['manual_review']['reason'])->toBe('entitlement_not_granted');
    expect(Coupon::where('payment_reference', $start['reference'])->count())->toBe(0);
    expect($user->cloudStorageTopUps()->count())->toBe(0);
});

test('the card option is refused when the provider has no key', function () {
    fakeKorapayForRevenueCode();

    config(['services.korapay.secret_key' => null]);

    $user = revenueCodeUser();

    $availability = app(SpecialCouponPurchaseService::class)->availability($user);

    expect($availability['available'])->toBeFalse();
    expect($availability['reason'])->toContain('not available');

    expect(app(SpecialCouponPurchaseService::class)->start($user)['ok'])->toBeFalse();
    expect(Transaction::where('type', TransactionType::SPECIAL_COUPON_PURCHASE->value)->count())->toBe(0);
});

test('a user without cloud calling cannot buy a revenue code', function () {
    fakeKorapayForRevenueCode();

    $user = User::factory()->create(['package_id' => null, 'country' => 'NG']);

    $availability = app(SpecialCouponPurchaseService::class)->availability($user);

    expect($availability['available'])->toBeFalse();
    expect($availability['reason'])->toContain('Royal eSIM');

    expect(app(SpecialCouponPurchaseService::class)->start($user)['ok'])->toBeFalse();
    expect(Transaction::where('type', TransactionType::SPECIAL_COUPON_PURCHASE->value)->count())->toBe(0);
});

test('a plan published for another tier — or an ordinary storage plan — cannot be bought', function () {
    fakeKorapayForRevenueCode();

    $user = revenueCodeUser();
    $purchases = app(SpecialCouponPurchaseService::class);

    $otherTier = CloudStoragePlan::where('tier_level', 1)->first();
    $ordinary = CloudStoragePlan::whereNull('tier_level')->first();

    expect($purchases->availability($user, $otherTier)['available'])->toBeFalse();
    expect($purchases->start($user, $otherTier)['ok'])->toBeFalse();

    expect($purchases->availability($user, $ordinary)['available'])->toBeFalse();
    expect($purchases->start($user, $ordinary)['ok'])->toBeFalse();

    expect(Transaction::where('type', TransactionType::SPECIAL_COUPON_PURCHASE->value)->count())->toBe(0);
});

// ---------------------------------------------------------------------------
// The support fallback: a super admin issues the code manually
// ---------------------------------------------------------------------------

test('a super admin can issue an active revenue code against payment proof', function () {
    $admin = Admin::factory()->super()->create();
    $user = revenueCodeUser();
    $plan = revenueCodePlan();

    $this->actingAs($admin, 'admin');

    $component = Livewire::test(ShowUser::class, ['user' => $user])
        ->assertSee('Issue revenue code')
        ->set('revenuePlanId', $plan->id)
        ->set('revenueReason', 'Paystack ref 1234567 — paid 12 Oct')
        ->call('issueRevenueCode')
        ->assertHasNoErrors();

    $issued = $component->get('issuedRevenueCode');
    expect($issued)->toBeString()->not->toBe('');

    $coupon = Coupon::where('user_id', $user->id)->where('type', CouponType::STORAGE_ACTIVATION->value)->first();

    expect($coupon)->not->toBeNull();
    expect($coupon->code)->toBe($issued);
    expect($coupon->source)->toBe(Coupon::SOURCE_ADMIN);
    expect($coupon->issued_by_admin_id)->toBe($admin->id);
    expect($coupon->issued_reason)->toBe('Paystack ref 1234567 — paid 12 Oct');
    expect($coupon->payment_reference)->toBeNull();
    expect($coupon->used_at)->toBeNull();

    // Same object a bought code is: storage applied now, and it authorises a withdrawal immediately.
    expect(CloudStorageTopUp::where('user_id', $user->id)->where('coupon_id', $coupon->id)->count())->toBe(1);
    expect(app(WithdrawalService::class)->validateAuthorizationCode($user, $coupon->code)->id)->toBe($coupon->id);
});

test('a regular admin neither sees the form nor can issue a code', function () {
    $admin = Admin::factory()->regular()->create();
    $user = revenueCodeUser();

    $this->actingAs($admin, 'admin');

    // The card is not rendered for them at all…
    Livewire::test(ShowUser::class, ['user' => $user])->assertDontSee('Issue revenue code');

    // …and if they reach the action anyway it refuses: the code moves real money. Livewire renders
    // the abort instead of rethrowing it, so the guarantee to assert is that nothing was issued.
    $component = null;

    try {
        $component = Livewire::test(ShowUser::class, ['user' => $user])
            ->set('revenuePlanId', revenueCodePlan()->id)
            ->call('issueRevenueCode');
    } catch (\Throwable) {
        // An abort that does surface as an exception is equally fine — the assertions below are the point.
    }

    if ($component !== null) {
        $component->assertSet('issuedRevenueCode', null);
    }

    expect(Coupon::where('type', CouponType::STORAGE_ACTIVATION->value)->count())->toBe(0);
    expect(CloudStorageTopUp::count())->toBe(0);
});

test('a super admin cannot issue a plan from another tier', function () {
    $admin = Admin::factory()->super()->create();
    $user = revenueCodeUser();

    $this->actingAs($admin, 'admin');

    Livewire::test(ShowUser::class, ['user' => $user])
        ->set('revenuePlanId', CloudStoragePlan::where('tier_level', 1)->first()->id)
        ->call('issueRevenueCode')
        ->assertSet('issuedRevenueCode', null);

    expect(Coupon::where('type', CouponType::STORAGE_ACTIVATION->value)->count())->toBe(0);
});

test('the service itself refuses to issue against a mismatched tier', function () {
    $user = revenueCodeUser();

    expect(fn () => app(RevenueCodeService::class)->issueFor($user, CloudStoragePlan::where('tier_level', 1)->first(), Coupon::SOURCE_ADMIN))
        ->toThrow(\RuntimeException::class);

    expect(Coupon::where('type', CouponType::STORAGE_ACTIVATION->value)->count())->toBe(0);
});

// ---------------------------------------------------------------------------
// The support desk is an admin setting, not a constant
// ---------------------------------------------------------------------------

test('an admin can change the revenue support desk from general settings', function () {
    $this->actingAs(Admin::factory()->super()->create(), 'admin');

    Livewire::test(GeneralSetting::class)
        ->assertSee('Revenue Code Support')
        // A bare handle is accepted and expanded, so nobody has to remember the https://t.me/ prefix.
        ->set('contactTelegramRevenue', '@nextelPayoutDesk')
        ->call('save')
        ->assertHasNoErrors()
        ->assertSet('contactTelegramRevenue', 'https://t.me/nextelPayoutDesk');

    expect(Setting::where('key', 'telegram_revenue_link')->value('value'))->toBe('https://t.me/nextelPayoutDesk');

    // The saved key is wired into the mapper the settings provider actually uses — a setting nobody
    // maps would silently never reach the page.
    $mapped = SettingMapper::toConfigArray(transform_setting(Setting::all()));
    expect($mapped['settings.contact.telegram_revenue'])->toBe('https://t.me/nextelPayoutDesk');
});

test('the page shows whatever support desk the setting resolves to', function () {
    config(['settings.contact.telegram_revenue' => 'https://t.me/nextelPayoutDesk']);

    $this->actingAs(revenueCodeUser())
        ->get(route('special-coupon'))
        ->assertOk()
        ->assertSee('https://t.me/nextelPayoutDesk')
        ->assertDontSee('t.me/nextelSpecialSupport');
});

test('a blank or unusable support link falls back to the built-in desk', function () {
    config(['settings.contact.telegram_revenue' => '']);
    expect(PayoutSupport::telegramRevenue())->toBe(PayoutSupport::DEFAULT_TELEGRAM_REVENUE);

    // A typed-in non-http scheme stays exactly as typed, so the http(s) check rejects it rather than
    // dressing it up as a t.me link.
    config(['settings.contact.telegram_revenue' => 'javascript:alert(1)']);
    expect(PayoutSupport::normalizeTelegramLink('javascript:alert(1)'))->toBe('javascript:alert(1)');
    expect(PayoutSupport::telegramRevenue())->toBe(PayoutSupport::DEFAULT_TELEGRAM_REVENUE);

    // Handles are expanded to a real link…
    expect(PayoutSupport::normalizeTelegramLink('  @nextel  '))->toBe('https://t.me/nextel');
    // …a pasted host gets its scheme back…
    expect(PayoutSupport::normalizeTelegramLink('t.me/nextelDesk'))->toBe('https://t.me/nextelDesk');
    // …and a real link is left untouched.
    expect(PayoutSupport::normalizeTelegramLink('https://t.me/nextel'))->toBe('https://t.me/nextel');
});
