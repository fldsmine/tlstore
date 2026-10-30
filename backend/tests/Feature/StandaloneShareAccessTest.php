<?php

use App\Enums\CouponType;
use App\Enums\TransactionType;
use App\Livewire\Dashboard\Agent\AgentDashboard;
use App\Models\AdditionalMinutePlan;
use App\Models\CallRecord;
use App\Models\CallType;
use App\Models\Country;
use App\Models\Coupon;
use App\Models\CouponBatch;
use App\Models\Currency;
use App\Models\MinuteTopUp;
use App\Models\ShareSubscription;
use App\Models\Transaction;
use App\Models\User;
use App\Services\ClaimService;
use App\Services\CouponMintingService;
use App\Services\Payments\ChargeSettlement;
use App\Services\Payments\MinuteTopUpPurchaseService;
use App\Services\Payments\ShareAccessPurchaseService;
use App\Services\ShareSubscriptionService;
use App\Services\TokenRedemptionService;
use App\Support\RegionPricing;
use Illuminate\Http\Client\Request as ClientRequest;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;

beforeEach(function (): void {
    Currency::create([
        'code' => 'NGN',
        'name' => 'Nigerian Naira',
        'symbol' => '₦',
        'usd_exchange_rate' => 1500,
        'is_active' => true,
    ]);
    Country::create([
        'name' => 'Nigeria',
        'code' => 'NG',
        'currency_code' => 'NGN',
        'is_active' => true,
    ]);

    config([
        'settings.payment.package_provider' => 'korapay',
        'settings.minutes.enabled' => true,
        'services.korapay.base_url' => 'https://api.korapay.com',
        'services.korapay.secret_key' => 'sk_test_korapay',
        'services.korapay.currency' => 'NGN',
    ]);
});

function standaloneShareCallType(array $attributes = []): CallType
{
    return CallType::factory()->create(array_merge([
        'price' => 5,
        'agent_price' => 4,
        'foreign_price' => null,
        'foreign_agent_price' => null,
        'region' => 'nigeria',
        'monthly_share_minutes' => 240,
        'share_term_days' => 30,
        'max_daily_shareholder_earning' => 12,
        'claimable_percentage' => 50,
        'is_active' => true,
    ], $attributes));
}

function fakeKorapayForStandaloneShare(): void
{
    $expected = [];

    Http::fake(function (ClientRequest $request) use (&$expected) {
        if (str_contains($request->url(), 'charges/initialize')) {
            $body = $request->data();
            $expected[$body['reference']] = (float) $body['amount'];

            return Http::response([
                'status' => true,
                'data' => [
                    'checkout_url' => 'https://checkout.korapay.com/'.$body['reference'],
                    'reference' => 'kpy_'.$body['reference'],
                ],
            ]);
        }

        $reference = basename((string) parse_url($request->url(), PHP_URL_PATH));

        return Http::response([
            'status' => true,
            'data' => [
                'status' => 'success',
                'amount' => $expected[$reference] ?? 0.0,
                'currency' => 'NGN',
                'reference' => $reference,
            ],
        ]);
    });
}

test('CallType share prices use the configured primary and alternate USD regions with a Nigeria default', function () {
    $nigeriaPrimary = standaloneShareCallType([
        'region' => 'nigeria',
        'price' => 5,
        'agent_price' => 4,
        'foreign_price' => 9,
        'foreign_agent_price' => 8,
    ]);
    $foreignPrimary = standaloneShareCallType([
        'region' => 'foreign',
        'price' => 8,
        'agent_price' => 7,
        'foreign_price' => 6,
        'foreign_agent_price' => 5,
    ]);

    expect(RegionPricing::customer($nigeriaPrimary, Coupon::REGION_NIGERIA))->toBe(5.0)
        ->and(RegionPricing::customer($nigeriaPrimary, Coupon::REGION_FOREIGN))->toBe(9.0)
        ->and(RegionPricing::customer($foreignPrimary, Coupon::REGION_FOREIGN))->toBe(8.0)
        ->and(RegionPricing::customer($foreignPrimary, Coupon::REGION_NIGERIA))->toBe(6.0)
        ->and(RegionPricing::agent($nigeriaPrimary, Coupon::REGION_NIGERIA))->toBe(4.0)
        ->and(RegionPricing::agent($nigeriaPrimary, Coupon::REGION_FOREIGN))->toBe(8.0)
        ->and(RegionPricing::agent($foreignPrimary, Coupon::REGION_FOREIGN))->toBe(7.0)
        ->and(RegionPricing::agent($foreignPrimary, Coupon::REGION_NIGERIA))->toBe(5.0)
        ->and(RegionPricing::forUser(User::factory()->make(['country' => null])))->toBe(Coupon::REGION_NIGERIA);
});

test('a share-only account can reach plans and shareholder access without eSIM activation', function () {
    config(['settings.minutes.enabled' => true]);
    standaloneShareCallType();
    $user = User::factory()->create([
        'package_id' => null,
        'country' => 'NG',
        'transaction_pin' => '1234',
    ]);

    $this->actingAs($user)
        ->get(route('plans'))
        ->assertOk()
        ->assertSee('Activate share access first');

    $this->get(route('shareholder'))
        ->assertOk()
        ->assertSee('No active share term')
        ->assertSee('eSIM activation is not required');

    $this->get(route('earnings'))->assertRedirect(route('shareholder'));
});

test('a distinct share activation coupon unlocks one CallType without activating eSIM', function () {
    $callType = standaloneShareCallType();
    $otherType = standaloneShareCallType(['price' => 7]);
    $user = User::factory()->create(['package_id' => null, 'country' => 'NG']);
    $coupon = Coupon::factory()->create([
        'type' => CouponType::SHARE_ACTIVATION,
        'call_type_id' => $callType->id,
        'region' => Coupon::REGION_NIGERIA,
        'price' => 5,
    ]);

    $subscription = app(TokenRedemptionService::class)->redeemShareAccess($user, $callType->id, $coupon->code);

    expect($user->refresh()->package_id)->toBeNull()
        ->and($subscription->package_id)->toBeNull()
        ->and($subscription->call_type_id)->toBe($callType->id)
        ->and((float) $subscription->allocated_minutes)->toBe(240.0)
        ->and((float) $subscription->daily_earning_cap)->toBe(12.0)
        ->and($subscription->expires_at->equalTo($subscription->starts_at->copy()->addDays(30)))->toBeTrue()
        ->and($coupon->refresh()->used_at)->not->toBeNull();

    $claims = app(ClaimService::class);
    expect($claims->inPackage($user, $callType))->toBeTrue()
        ->and($claims->inPackage($user, $otherType))->toBeFalse();

    $record = CallRecord::factory()->create([
        'call_type_id' => $callType->id,
        'duration_minutes' => 5,
        'earnings' => 2,
    ]);

    expect($claims->claim($user, $record))->toBe(1.0)
        ->and($user->refresh()->package_id)->toBeNull()
        ->and($claims->claim($user, CallRecord::factory()->create([
            'call_type_id' => $otherType->id,
            'duration_minutes' => 5,
            'earnings' => 2,
        ])))->toBe(0.0);
});

test('share activation coupons are minted as a distinct CallType-bound product', function () {
    $callType = standaloneShareCallType();
    $agent = User::factory()->agent()->create(['agent_balance' => 20, 'country' => 'NG']);

    $options = app(CouponMintingService::class)->planOptions($agent);
    expect($options->firstWhere('key', 'shares:'.$callType->id)['group'])->toBe('Share Access');

    $result = app(CouponMintingService::class)->mint($agent, 'shares:'.$callType->id, 1);
    $batch = $result['batch']->refresh();
    $coupon = $batch->coupons()->sole();

    expect($batch->type)->toBe(CouponType::SHARE_ACTIVATION)
        ->and($batch->call_type_id)->toBe($callType->id)
        ->and($coupon->type)->toBe(CouponType::SHARE_ACTIVATION)
        ->and($coupon->call_type_id)->toBe($callType->id)
        ->and((float) $agent->refresh()->agent_balance)->toBe(16.0);
});

test('the agent dashboard mints standalone share products with their CallType and coupon type', function () {
    $callType = standaloneShareCallType();
    $agent = User::factory()->agent()->create(['agent_balance' => 20, 'country' => 'NG']);

    $this->actingAs($agent);
    Livewire::test(AgentDashboard::class)
        ->set('planKey', 'shares:'.$callType->id)
        ->set('quantity', 1)
        ->call('generate')
        ->assertHasNoErrors();

    $batch = CouponBatch::query()->where('agent_id', $agent->id)->sole();
    $coupon = $batch->coupons()->sole();

    expect($batch->type)->toBe(CouponType::SHARE_ACTIVATION)
        ->and($batch->call_type_id)->toBe($callType->id)
        ->and($coupon->type)->toBe(CouponType::SHARE_ACTIVATION)
        ->and($coupon->call_type_id)->toBe($callType->id);
});

test('direct share access is pending until the configured gateway verifies payment', function () {
    fakeKorapayForStandaloneShare();

    $callType = standaloneShareCallType();
    $otherType = standaloneShareCallType(['price' => 7]);
    $user = User::factory()->create(['package_id' => null, 'country' => 'NG']);

    $start = app(ShareAccessPurchaseService::class)->start($user, $callType);
    expect($start['ok'])->toBeTrue();

    $repeat = app(ShareAccessPurchaseService::class)->start($user, $callType);
    $differentProduct = app(ShareAccessPurchaseService::class)->start($user, $otherType);
    expect($repeat['reference'])->toBe($start['reference'])
        ->and($differentProduct['ok'])->toBeFalse()
        ->and(Transaction::query()->where('type', TransactionType::SHARE_ACCESS_PURCHASE->value)->count())->toBe(1);

    $transaction = Transaction::query()->where('reference', $start['reference'])->firstOrFail();
    expect($transaction->type)->toBe(TransactionType::SHARE_ACCESS_PURCHASE)
        ->and($transaction->status)->toBe('pending')
        ->and($transaction->meta['call_type_id'])->toBe($callType->id)
        ->and((float) $transaction->meta['amount_ngn'])->toBe(7500.0)
        ->and(ShareSubscription::query()->where('user_id', $user->id)->count())->toBe(0);

    expect(app(ChargeSettlement::class)->finalize($start['reference']))->toBe('completed');

    $subscription = app(ShareSubscriptionService::class)->current($user->refresh());
    expect($subscription)->not->toBeNull()
        ->and($subscription->call_type_id)->toBe($callType->id)
        ->and($subscription->package_id)->toBeNull()
        ->and($user->package_id)->toBeNull()
        ->and($transaction->refresh()->status)->toBe('completed');
});

test('a verified share purchase is flagged instead of granting access if its CallType was removed', function () {
    fakeKorapayForStandaloneShare();

    $callType = standaloneShareCallType();
    $user = User::factory()->create(['package_id' => null, 'country' => 'NG']);
    $start = app(ShareAccessPurchaseService::class)->start($user, $callType);
    expect($start['ok'])->toBeTrue();

    $callType->delete();

    expect(app(ChargeSettlement::class)->finalize($start['reference']))->toBe('failed')
        ->and(Transaction::query()->where('reference', $start['reference'])->value('status'))->toBe('failed')
        ->and(Transaction::query()->where('reference', $start['reference'])->firstOrFail()->meta['manual_review']['reason'])->toBe('entitlement_not_granted')
        ->and(ShareSubscription::query()->where('user_id', $user->id)->count())->toBe(0);
});

test('direct share-minute top-ups are granted only after verification and never grant eSIM allowances to share-only users', function () {
    fakeKorapayForStandaloneShare();

    $user = User::factory()->create(['package_id' => null, 'country' => 'NG']);
    $callType = standaloneShareCallType();
    $subscription = app(ShareSubscriptionService::class)->startForCallType($user, $callType, Coupon::REGION_NIGERIA);
    $plan = AdditionalMinutePlan::factory()->create([
        'price' => 2,
        'daily_call_minutes' => 0,
        'share_minutes' => 50,
        'duration_days' => 7,
        'call_id_bonus' => 0,
        'is_active' => true,
    ]);

    $start = app(MinuteTopUpPurchaseService::class)->start($user, $plan);
    expect($start['ok'])->toBeTrue()
        ->and(MinuteTopUp::query()->where('user_id', $user->id)->count())->toBe(0);

    expect(app(ChargeSettlement::class)->finalize($start['reference']))->toBe('completed');

    $topUp = MinuteTopUp::query()->where('user_id', $user->id)->sole();
    expect($topUp->share_subscription_id)->toBe($subscription->id)
        ->and((float) $topUp->share_minutes)->toBe(50.0)
        ->and((float) $topUp->daily_call_minutes)->toBe(0.0)
        ->and($topUp->share_expires_at->isFuture())->toBeTrue()
        ->and($topUp->share_expires_at->lessThan($subscription->expires_at))->toBeTrue()
        ->and($user->refresh()->package_id)->toBeNull()
        ->and(app(ClaimService::class)->allocatedMinutes($user, $subscription))->toBe(290.0)
        ->and(Transaction::query()->where('reference', $start['reference'])->value('status'))->toBe('completed');
});
