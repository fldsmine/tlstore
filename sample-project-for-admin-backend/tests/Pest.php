<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Models\Country;
use App\Models\Package;
use App\Models\User;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind different classes or traits.
|
*/

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

function something()
{
    // ..
}

/** Create a persisted user (email verified, active). */
/*
|--------------------------------------------------------------------------
| API test helpers
|--------------------------------------------------------------------------
*/

function apiUser(array $attributes = []): User
{
    return User::factory()->create($attributes);
}

/** Create a user who has activated a package (past the onboarding gate). */
function activatedUser(array $attributes = []): User
{
    $package = Package::factory()->create();

    return apiUser(array_merge(['package_id' => $package->id], $attributes));
}

/** Create a user with an explicitly configured active share term for claims tests. */
function shareEntitledUser(array $attributes = [], array $packageAttributes = []): User
{
    $package = Package::factory()->create(array_merge([
        'monthly_share_minutes' => 600,
        'share_term_days' => 30,
        'max_daily_shareholder_earning' => 100,
    ], $packageAttributes));

    $user = apiUser(array_merge(['package_id' => $package->id], $attributes));
    $startsAt = now();

    \App\Models\ShareSubscription::query()->create([
        'user_id' => $user->id,
        'package_id' => $package->id,
        'allocated_minutes' => round((float) $package->monthly_share_minutes * (int) $package->share_term_days / 30, 2),
        'daily_earning_cap' => $package->max_daily_shareholder_earning,
        'starts_at' => $startsAt,
        'expires_at' => $startsAt->copy()->addDays($package->share_term_days),
        'status' => 'active',
    ]);

    return $user;
}

/** Seed the countries table with Nigeria (most flows expect it). */
function seedNigeria(): Country
{
    return Country::factory()->create([
        'name' => 'Nigeria',
        'code' => 'NG',
        'currency_code' => 'NGN',
    ]);
}
