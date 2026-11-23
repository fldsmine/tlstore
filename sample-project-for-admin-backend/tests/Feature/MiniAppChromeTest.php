<?php

use App\Models\Package;
use App\Models\User;

/*
|--------------------------------------------------------------------------
| Telegram Mini App chrome over the Livewire pages
|--------------------------------------------------------------------------
*/

test('handoff flags the web session as a mini-app session', function () {
    $user = User::factory()->create();
    $token = $user->createToken('mini-app')->plainTextToken;

    $this->postJson('/api/v1/web-session', [], [
        'Authorization' => 'Bearer '.$token,
        'Accept' => 'application/json',
    ])->assertOk()->assertSessionHas('mini_app', true);
});

test('logout returns mini-app users to the mini-app login screen', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->withSession(['mini_app' => true])
        ->post(route('logout'))
        ->assertRedirect(route('mini-app', ['logged_out' => 1]));
});

test('logout keeps regular web users on the existing flow', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('logout'))
        ->assertRedirect(route('home'));
});

test('mini-app mode hides the app bar and moves its actions to the bottom nav', function () {
    $user = User::factory()->create();
    $user->forceFill(['package_id' => Package::factory()->create()->id])->save();

    $this->actingAs($user);

    // Regular web chrome: app bar present, footer is links only.
    $this->view('components.app.app-bar', ['title' => 'Chrome'])
        ->assertSee('Open menu')
        ->assertSee('open-app-menu');

    $this->view('components.app.bottom-nav')
        ->assertDontSee('Open menu')
        ->assertDontSee('open-app-menu');

    // Telegram Mini App chrome: app bar gone, menu + search in the footer.
    $this->session(['mini_app' => true]);

    $this->view('components.app.app-bar', ['title' => 'Chrome'])
        ->assertDontSee('open-app-menu');

    $this->view('components.app.bottom-nav')
        ->assertSee('open-app-menu')
        ->assertSee('open-search')
        ->assertSee('Menu')
        ->assertSee('Search');
});
