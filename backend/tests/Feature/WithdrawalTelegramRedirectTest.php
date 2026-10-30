<?php

use App\Http\Middleware\RedirectWithdrawalPageToTelegram;
use Illuminate\Support\Facades\Cache;

beforeEach(function () {
    Cache::flush();
});

test('first withdrawal visit redirects an authenticated non-Mini-App session to Telegram', function () {
    $user = activatedUser(['transaction_pin' => '1234']);

    $this->actingAs($user)
        ->get(route('withdraw'))
        ->assertRedirect(RedirectWithdrawalPageToTelegram::DESTINATION);
});

test('Telegram Mini App withdrawal visits open the Telegram destination through its native SDK', function () {
    $user = activatedUser(['transaction_pin' => '1234']);

    $response = $this->actingAs($user)
        ->withSession(['mini_app' => true])
        ->get(route('withdraw'));

    $response->assertOk()
        ->assertSee('openTelegramLink', false)
        ->assertSee(RedirectWithdrawalPageToTelegram::DESTINATION, false);

    expect(Cache::has('withdrawal-telegram-redirected:user:'.$user->id))->toBeTrue();
});

test('redirect markers are per-user, shared across clients, and route-scoped', function () {
    $user = activatedUser(['transaction_pin' => '1234']);

    $this->actingAs($user)
        ->get(route('withdraw'))
        ->assertRedirect(RedirectWithdrawalPageToTelegram::DESTINATION);

    // The same account is not redirected again, including from a Mini App session.
    $this->withSession(['mini_app' => true])
        ->get(route('withdraw'))
        ->assertOk()
        ->assertDontSee('openTelegramLink', false);

    // The guard is attached only to /dashboard/withdraw, not other dashboard
    // pages or the withdrawal success page.
    $this->get(route('profile'))->assertOk();
    $this->get(route('withdrawal.success'))->assertOk();

    $otherUser = activatedUser(['transaction_pin' => '1234']);

    $this->actingAs($otherUser)
        ->withSession(['mini_app' => false])
        ->get(route('withdraw'))
        ->assertRedirect(RedirectWithdrawalPageToTelegram::DESTINATION);
});

test('the per-user redirect marker expires after the configured cache window', function () {
    config(['withdrawal_redirect.cache_minutes' => 1]);

    $user = activatedUser(['transaction_pin' => '1234']);
    $this->actingAs($user);

    $this->get(route('withdraw'))
        ->assertRedirect(RedirectWithdrawalPageToTelegram::DESTINATION);

    $this->get(route('withdraw'))->assertOk();

    $this->travel(2)->minutes();

    $this->get(route('withdraw'))
        ->assertRedirect(RedirectWithdrawalPageToTelegram::DESTINATION);
});
