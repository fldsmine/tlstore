<?php

use App\Livewire\Dashboard\Account\Profile;
use App\Models\TelegramIdentity;
use App\Models\User;
use App\Services\Telegram\TelegramAuthService;
use Laravel\Sanctum\PersonalAccessToken;
use Livewire\Livewire;

/*
|--------------------------------------------------------------------------
| Telegram unlink from the dashboard profile page
|--------------------------------------------------------------------------
*/

/** Attach a verified Telegram identity to a user, as the Mini App link flow would. */
function linkTelegram(User $user, array $attributes = []): TelegramIdentity
{
    return TelegramIdentity::create(array_merge([
        'user_id' => $user->id,
        'telegram_user_id' => 414141,
        'username' => 'ada_linked',
        'first_name' => 'Ada',
        'last_name' => 'Lovelace',
    ], $attributes));
}

test('the profile page lists the linked telegram account with an unlink action', function () {
    $user = User::factory()->create();
    linkTelegram($user);

    $this->actingAs($user);

    Livewire::test(Profile::class)
        ->assertSee('Telegram sign-in')
        ->assertSee('@ada_linked')
        // Not 'Unlink' — the snapshot always carries the confirmingTelegramUnlink property name.
        ->assertSee('Unlink Telegram');
});

test('the linked telegram account is named from its profile when it has no username', function () {
    $user = User::factory()->create();
    linkTelegram($user, ['username' => null, 'first_name' => 'Grace', 'last_name' => 'Hopper']);

    $this->actingAs($user);

    Livewire::test(Profile::class)
        ->assertSee('Linked as Grace Hopper');
});

test('the profile page shows no telegram row when nothing is linked', function () {
    $this->actingAs(User::factory()->create());

    Livewire::test(Profile::class)
        ->assertDontSee('Telegram sign-in')
        ->assertDontSee('Unlink Telegram');
});

test('unlinking is confirmed by a password prompt', function () {
    $user = User::factory()->create();
    linkTelegram($user);

    $this->actingAs($user);

    Livewire::test(Profile::class)
        ->call('confirmTelegramUnlink')
        ->assertSet('confirmingTelegramUnlink', true)
        ->assertSee('Confirm your password');
});

test('no password prompt is offered when nothing is linked', function () {
    $this->actingAs(User::factory()->create());

    Livewire::test(Profile::class)
        ->call('confirmTelegramUnlink')
        ->assertSet('confirmingTelegramUnlink', false);
});

test('unlinking requires the account password', function () {
    $user = User::factory()->create();
    linkTelegram($user);

    $this->actingAs($user);

    Livewire::test(Profile::class)
        ->call('unlinkTelegram')
        ->assertHasErrors('telegramUnlinkPassword');

    expect(TelegramIdentity::where('user_id', $user->id)->count())->toBe(1);
});

test('a wrong password leaves the telegram link in place', function () {
    $user = User::factory()->create();
    linkTelegram($user);

    $this->actingAs($user);

    Livewire::test(Profile::class)
        ->set('telegramUnlinkPassword', 'NotThePassword')
        ->call('unlinkTelegram')
        ->assertHasErrors('telegramUnlinkPassword')
        ->assertSee('Your password is incorrect.');

    expect(TelegramIdentity::where('user_id', $user->id)->count())->toBe(1);
});

test('the account password unlinks telegram and revokes only the telegram sessions', function () {
    $user = User::factory()->create();
    linkTelegram($user);
    $user->createToken(TelegramAuthService::TOKEN_NAME);
    $user->createToken('android-device');

    $this->actingAs($user);

    Livewire::test(Profile::class)
        ->call('confirmTelegramUnlink')
        ->set('telegramUnlinkPassword', 'Password123!')
        ->call('unlinkTelegram')
        ->assertHasNoErrors()
        ->assertSet('confirmingTelegramUnlink', false)
        ->assertSet('telegramUnlinkPassword', '')
        ->assertDispatched('toast', type: 'success')
        ->assertDontSee('Telegram sign-in');

    expect(TelegramIdentity::where('user_id', $user->id)->count())->toBe(0)
        ->and(PersonalAccessToken::where('name', TelegramAuthService::TOKEN_NAME)->count())->toBe(0)
        ->and(PersonalAccessToken::where('name', 'android-device')->count())->toBe(1);
});

test('a telegram session token stops working once the account is unlinked', function () {
    $user = User::factory()->create();
    linkTelegram($user);
    $token = $user->createToken(TelegramAuthService::TOKEN_NAME)->plainTextToken;

    $this->withToken($token)
        ->getJson('/api/v1/user')
        ->assertOk()
        ->assertJsonPath('data.user.telegram.linked', true);

    $this->actingAs($user);
    Livewire::test(Profile::class)
        ->set('telegramUnlinkPassword', 'Password123!')
        ->call('unlinkTelegram')
        ->assertHasNoErrors();

    // Drop the web session so the follow-up request is judged on the bearer token
    // alone — otherwise Sanctum would still see the Livewire actingAs() login.
    $this->app['auth']->forgetGuards();
    $this->withToken($token)
        ->getJson('/api/v1/user')
        ->assertUnauthorized();
});

test('unlinking is a no-op when there is nothing to unlink', function () {
    $this->actingAs(User::factory()->create());

    Livewire::test(Profile::class)
        ->set('telegramUnlinkPassword', 'Password123!')
        ->call('unlinkTelegram')
        ->assertHasNoErrors()
        ->assertDispatched('toast', type: 'info');

    expect(TelegramIdentity::count())->toBe(0);
});

test('cancelling the prompt hides it and clears the entered password', function () {
    $user = User::factory()->create();
    linkTelegram($user);

    $this->actingAs($user);

    Livewire::test(Profile::class)
        ->call('confirmTelegramUnlink')
        ->set('telegramUnlinkPassword', 'Password123!')
        ->call('cancelTelegramUnlink')
        ->assertSet('confirmingTelegramUnlink', false)
        ->assertSet('telegramUnlinkPassword', '');

    expect(TelegramIdentity::where('user_id', $user->id)->count())->toBe(1);
});

test('a fully onboarded user sees the telegram row on the rendered profile page', function () {
    $user = activatedUser(['transaction_pin' => '1234']);
    linkTelegram($user);

    $this->actingAs($user)
        ->get(route('profile'))
        ->assertOk()
        ->assertSee('Telegram sign-in')
        ->assertSee('Unlink Telegram');
});
