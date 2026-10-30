<?php

use App\Enums\CouponType;
use App\Enums\WithdrawalStatus;
use App\Models\Bank;
use App\Models\BankAccount;
use App\Models\CloudStoragePlan;
use App\Models\Coupon;
use App\Models\Package;
use App\Models\User;
use App\Models\Withdrawal;
use App\Services\WithdrawalService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    // Create packages
    Package::create([
        'name' => 'Diamond eSIM',
        'tier_level' => 1,
        'enable_cloud' => true,
        'cloud_calls' => false,
        'cloud_sms' => true,
        'cloud_contact_limit' => 0,
    ]);

    Package::create([
        'name' => 'Royal eSIM',
        'tier_level' => 2,
        'enable_cloud' => true,
        'cloud_calls' => true,
        'cloud_sms' => true,
        'cloud_contact_limit' => 10,
    ]);

    // Create tier-specific cloud storage plans
    CloudStoragePlan::create([
        'name' => 'Tier 1 Special Storage',
        'extra_contacts' => 4,
        'tier_level' => 1,
        'is_active' => true,
    ]);

    CloudStoragePlan::create([
        'name' => 'Tier 2 Special Storage',
        'extra_contacts' => 7,
        'tier_level' => 2,
        'is_active' => true,
    ]);

    // Create a bank for testing
    $bank = Bank::create([
        'name' => 'Test Bank',
        'code' => 'TESTBANK',
        'tag' => 'test',
    ]);
});

function cloudUserWithCoupon(float $cloudCallBalance = 50.0, float $cloudSmsBalance = 0.0): User
{
    $package = Package::where('tier_level', 2)->where('cloud_calls', true)->first();
    $user = User::factory()->create([
        'package_id' => $package->id,
        'cloud_call_balance' => $cloudCallBalance,
        'cloud_sms_balance' => $cloudSmsBalance,
        'transaction_pin' => bcrypt('1234'),
        'country' => 'NG',
    ]);

    BankAccount::create([
        'user_id' => $user->id,
        'bank_id' => Bank::first()->id,
        'account_number' => '1234567890',
        'account_name' => 'Test Account',
    ]);

    // Create and activate a special coupon for the user
    $tier2Plan = CloudStoragePlan::where('tier_level', 2)->first();
    $coupon = Coupon::create([
        'code' => 'WITHDRAWCODE',
        'code_hash' => Coupon::hashCode('WITHDRAWCODE'),
        'type' => CouponType::STORAGE_ACTIVATION,
        'cloud_storage_plan_id' => $tier2Plan->id,
        'expires_at' => now()->addDays(90),
        'user_id' => $user->id, // Already bound to user
    ]);

    return $user;
}

test('withdrawal requires code for cloud_call wallet', function () {
    $user = cloudUserWithCoupon(50.0);
    $service = app(WithdrawalService::class);

    // Missing withdrawal code for cloud wallet
    expect(fn () => $service->request($user, 'cloud_call', 10.0, null))
        ->toThrow(\Illuminate\Validation\ValidationException::class);
});

test('withdrawal requires code for cloud_sms wallet', function () {
    $user = cloudUserWithCoupon(0.0, 50.0);
    $service = app(WithdrawalService::class);

    // Missing withdrawal code for cloud wallet
    expect(fn () => $service->request($user, 'cloud_sms', 10.0, null))
        ->toThrow(\Illuminate\Validation\ValidationException::class);
});

test('withdrawal does not require code for non-cloud wallets', function () {
    $user = User::factory()->create([
        'package_id' => Package::where('tier_level', 2)->first()->id,
        'shareholder_balance' => 50.0,
        'transaction_pin' => bcrypt('1234'),
        'country' => 'NG',
    ]);

    BankAccount::create([
        'user_id' => $user->id,
        'bank_id' => Bank::first()->id,
        'account_number' => '1234567890',
        'account_name' => 'Test Account',
    ]);

    $service = app(WithdrawalService::class);

    // Should not throw for non-cloud wallet without code
    $withdrawal = $service->request($user, 'shareholder', 10.0, null);
    expect($withdrawal)->not->toBeNull();
});

test('valid withdrawal code allows cloud_call withdrawal', function () {
    $user = cloudUserWithCoupon(50.0);
    $service = app(WithdrawalService::class);

    $withdrawal = $service->request($user, 'cloud_call', 10.0, 'WITHDRAWCODE');

    expect($withdrawal)->not->toBeNull();
    expect($withdrawal->wallet)->toBe('cloud_call');
    expect((float) $withdrawal->amount)->toBe(10.0);

    // Check balance was deducted
    expect((float) $user->refresh()->cloud_call_balance)->toBe(40.0);

    // Check coupon was marked as used
    $coupon = Coupon::where('code_hash', Coupon::hashCode('WITHDRAWCODE'))->first();
    expect($coupon->used_at)->not->toBeNull();
});

test('valid withdrawal code allows cloud_sms withdrawal', function () {
    $user = cloudUserWithCoupon(0.0, 50.0);
    $service = app(WithdrawalService::class);

    $withdrawal = $service->request($user, 'cloud_sms', 10.0, 'WITHDRAWCODE');

    expect($withdrawal)->not->toBeNull();
    expect($withdrawal->wallet)->toBe('cloud_sms');

    // Check balance was deducted
    expect((float) $user->refresh()->cloud_sms_balance)->toBe(40.0);

    // Check coupon was marked as used
    $coupon = Coupon::where('code_hash', Coupon::hashCode('WITHDRAWCODE'))->first();
    expect($coupon->used_at)->not->toBeNull();
});

test('invalid withdrawal code is rejected', function () {
    $user = cloudUserWithCoupon(50.0);
    $service = app(WithdrawalService::class);

    expect(fn () => $service->request($user, 'cloud_call', 10.0, 'INVALIDCODE'))
        ->toThrow(\Illuminate\Validation\ValidationException::class);
});

test('used withdrawal code cannot be reused', function () {
    $user = cloudUserWithCoupon(50.0);
    $service = app(WithdrawalService::class);

    // First withdrawal succeeds
    $service->request($user, 'cloud_call', 10.0, 'WITHDRAWCODE');

    // Second withdrawal with same code should fail
    expect(fn () => $service->request($user, 'cloud_call', 10.0, 'WITHDRAWCODE'))
        ->toThrow(\Illuminate\Validation\ValidationException::class);
});

test('expired withdrawal code is rejected', function () {
    $user = cloudUserWithCoupon(50.0);

    // Expire the coupon
    $coupon = Coupon::where('code_hash', Coupon::hashCode('WITHDRAWCODE'))->first();
    $coupon->update(['expires_at' => now()->subDay()]);

    $service = app(WithdrawalService::class);

    expect(fn () => $service->request($user, 'cloud_call', 10.0, 'WITHDRAWCODE'))
        ->toThrow(\Illuminate\Validation\ValidationException::class);
});

test('cross-user withdrawal code is rejected', function () {
    $user1 = cloudUserWithCoupon(50.0);
    $user2 = User::factory()->create([
        'package_id' => Package::where('tier_level', 2)->first()->id,
        'cloud_call_balance' => 50.0,
        'transaction_pin' => bcrypt('1234'),
        'country' => 'NG',
    ]);

    BankAccount::create([
        'user_id' => $user2->id,
        'bank_id' => Bank::first()->id,
        'account_number' => '0987654321',
        'account_name' => 'Test Account 2',
    ]);

    $service = app(WithdrawalService::class);

    // User2 tries to use User1's code
    expect(fn () => $service->request($user2, 'cloud_call', 10.0, 'WITHDRAWCODE'))
        ->toThrow(\Illuminate\Validation\ValidationException::class);
});

test('failed withdrawal does not consume code', function () {
    $user = cloudUserWithCoupon(10.0);
    $service = app(WithdrawalService::class);

    // A withdrawal already processing from this wallet blocks a second request — the
    // service rejects it before the coupon is locked or consumed.
    Withdrawal::create([
        'user_id' => $user->id,
        'reference' => Withdrawal::generateReference(),
        'wallet' => 'cloud_call',
        'status' => WithdrawalStatus::PROCESSING,
        'amount' => 5.0,
        'fee' => 0.1,
        'amount_paid' => 4.9,
        'meta' => [],
    ]);

    expect(fn () => $service->request($user, 'cloud_call', 5.0, 'WITHDRAWCODE'))
        ->toThrow(\Illuminate\Validation\ValidationException::class);

    // Code should still be usable
    $coupon = Coupon::where('code_hash', Coupon::hashCode('WITHDRAWCODE'))->first();
    expect($coupon->used_at)->toBeNull();
});

test('walletRequiresAuthorizationCode identifies cloud wallets', function () {
    $service = app(WithdrawalService::class);

    expect($service->walletRequiresAuthorizationCode('cloud_call'))->toBeTrue();
    expect($service->walletRequiresAuthorizationCode('cloud_sms'))->toBeTrue();
    expect($service->walletRequiresAuthorizationCode('shareholder'))->toBeFalse();
    expect($service->walletRequiresAuthorizationCode('referral_bonus'))->toBeFalse();
});

test('validateAuthorizationCode accepts a valid bound code without consuming it', function () {
    $user = cloudUserWithCoupon(50.0);
    $service = app(WithdrawalService::class);

    $coupon = $service->validateAuthorizationCode($user, 'WITHDRAWCODE');

    expect($coupon)->not->toBeNull();
    expect($coupon->user_id)->toBe($user->id);

    // Pre-validation must NOT consume the code — it is still usable for a real withdrawal.
    expect($coupon->refresh()->used_at)->toBeNull();

    $withdrawal = $service->request($user, 'cloud_call', 10.0, 'WITHDRAWCODE');
    expect($withdrawal)->not->toBeNull();
});

test('validateAuthorizationCode rejects an unknown code', function () {
    $user = cloudUserWithCoupon(50.0);
    $service = app(WithdrawalService::class);

    expect(fn () => $service->validateAuthorizationCode($user, 'NOSUCHCODE'))
        ->toThrow(\Illuminate\Validation\ValidationException::class);
});

test('validateAuthorizationCode rejects a code bound to another user', function () {
    $user1 = cloudUserWithCoupon(50.0);
    $user2 = User::factory()->create([
        'package_id' => Package::where('tier_level', 2)->first()->id,
        'cloud_call_balance' => 50.0,
        'transaction_pin' => bcrypt('1234'),
        'country' => 'NG',
    ]);

    $service = app(WithdrawalService::class);

    expect(fn () => $service->validateAuthorizationCode($user2, 'WITHDRAWCODE'))
        ->toThrow(\Illuminate\Validation\ValidationException::class);

    // The rightful owner can still use it.
    expect($service->validateAuthorizationCode($user1, 'WITHDRAWCODE'))->not->toBeNull();
});

test('validateAuthorizationCode rejects an unactivated (unbound) special coupon with the activation hint', function () {
    $user = cloudUserWithCoupon(50.0);

    // A fresh special coupon that was never activated (not bound to any user).
    $tier2Plan = CloudStoragePlan::where('tier_level', 2)->first();
    Coupon::create([
        'code' => 'FRESHCODE',
        'code_hash' => Coupon::hashCode('FRESHCODE'),
        'type' => CouponType::STORAGE_ACTIVATION,
        'cloud_storage_plan_id' => $tier2Plan->id,
        'expires_at' => now()->addDays(90),
        'user_id' => null,
    ]);

    $service = app(WithdrawalService::class);

    try {
        $service->validateAuthorizationCode($user, 'FRESHCODE');
        $this->fail('Expected a ValidationException.');
    } catch (\Illuminate\Validation\ValidationException $e) {
        // The shared message lets the web view attach the "activate it" link.
        expect($e->errors()['withdrawal_code'][0])->toBe(WithdrawalService::CODE_NOT_LINKED_MESSAGE);
    }
});

test('validateAuthorizationCode rejects a used code', function () {
    $user = cloudUserWithCoupon(50.0);

    $coupon = Coupon::where('code_hash', Coupon::hashCode('WITHDRAWCODE'))->first();
    $coupon->update(['used_at' => now()]);

    $service = app(WithdrawalService::class);

    expect(fn () => $service->validateAuthorizationCode($user, 'WITHDRAWCODE'))
        ->toThrow(\Illuminate\Validation\ValidationException::class);
});

test('validateAuthorizationCode rejects an expired code', function () {
    $user = cloudUserWithCoupon(50.0);

    $coupon = Coupon::where('code_hash', Coupon::hashCode('WITHDRAWCODE'))->first();
    $coupon->update(['expires_at' => now()->subDay()]);

    $service = app(WithdrawalService::class);

    expect(fn () => $service->validateAuthorizationCode($user, 'WITHDRAWCODE'))
        ->toThrow(\Illuminate\Validation\ValidationException::class);
});

test('validateAuthorizationCode rejects a non-special coupon type', function () {
    $user = cloudUserWithCoupon(50.0);

    // A regular CLOUD coupon bound to the user but unused should not authorize withdrawals.
    $regularPlan = CloudStoragePlan::create([
        'name' => 'Regular Storage',
        'extra_contacts' => 5,
        'tier_level' => null,
        'is_active' => true,
    ]);

    Coupon::create([
        'code' => 'REGULARCODE',
        'code_hash' => Coupon::hashCode('REGULARCODE'),
        'type' => CouponType::CLOUD,
        'cloud_storage_plan_id' => $regularPlan->id,
        'user_id' => $user->id,
    ]);

    $service = app(WithdrawalService::class);

    expect(fn () => $service->validateAuthorizationCode($user, 'REGULARCODE'))
        ->toThrow(\Illuminate\Validation\ValidationException::class);

    // And the consumption path rejects it too.
    expect(fn () => $service->request($user, 'cloud_call', 10.0, 'REGULARCODE'))
        ->toThrow(\Illuminate\Validation\ValidationException::class);
});

test('walletRequiresAuthorizationCode respects the feature setting', function () {
    $service = app(WithdrawalService::class);

    // Default: the feature is on — cloud wallets need a code, others don't.
    expect($service->walletRequiresAuthorizationCode('cloud_call'))->toBeTrue();
    expect($service->walletRequiresAuthorizationCode('cloud_sms'))->toBeTrue();
    expect($service->walletRequiresAuthorizationCode('shareholder'))->toBeFalse();

    // Feature switched off in Backoffice → Settings → Features: no wallet needs a code.
    config(['settings.withdrawal.require_authorization_code' => false]);

    expect($service->walletRequiresAuthorizationCode('cloud_call'))->toBeFalse();
    expect($service->walletRequiresAuthorizationCode('cloud_sms'))->toBeFalse();
    expect($service->walletRequiresAuthorizationCode('shareholder'))->toBeFalse();
});

test('cloud withdrawal works without a code when the setting is disabled', function () {
    config(['settings.withdrawal.require_authorization_code' => false]);

    $user = cloudUserWithCoupon(50.0);
    $service = app(WithdrawalService::class);

    $withdrawal = $service->request($user, 'cloud_call', 10.0, null);

    expect($withdrawal)->not->toBeNull();
    expect((float) $user->refresh()->cloud_call_balance)->toBe(40.0);

    // No coupon was consumed — the bound code is untouched.
    expect(Coupon::whereNotNull('used_at')->count())->toBe(0);
});

test('cloud withdrawal still requires a code when the setting is explicitly enabled', function () {
    config(['settings.withdrawal.require_authorization_code' => true]);

    $user = cloudUserWithCoupon(50.0);
    $service = app(WithdrawalService::class);

    expect(fn () => $service->request($user, 'cloud_call', 10.0, null))
        ->toThrow(\Illuminate\Validation\ValidationException::class);
});

test('non-cloud withdrawals never need a code even when the setting is on', function () {
    config(['settings.withdrawal.require_authorization_code' => true]);

    $user = User::factory()->create([
        'package_id' => Package::where('tier_level', 2)->first()->id,
        'shareholder_balance' => 50.0,
        'transaction_pin' => bcrypt('1234'),
        'country' => 'NG',
    ]);

    BankAccount::create([
        'user_id' => $user->id,
        'bank_id' => Bank::first()->id,
        'account_number' => '1234567890',
        'account_name' => 'Test Account',
    ]);

    $withdrawal = app(WithdrawalService::class)->request($user, 'shareholder', 10.0, null);

    expect($withdrawal)->not->toBeNull();
});
