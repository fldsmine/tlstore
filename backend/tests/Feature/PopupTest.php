<?php

use App\Livewire\Backoffice\Popups\ListPopups;
use App\Livewire\Dashboard\Popup as DashboardPopup;
use App\Models\Admin;
use App\Models\Popup;
use App\Models\PopupView;
use App\Models\User;
use Livewire\Livewire;

/*
|--------------------------------------------------------------------------
| Dashboard popups
|--------------------------------------------------------------------------
|
| The backoffice manager (Marketing → Popups) and the dialog the dashboard
| opens: a random order, one at a time, recursive every visit or spent the
| first time it is shown.
|
*/

function popupAdmin(): Admin
{
    return Admin::factory()->regular()->create();
}

function popupUser(array $attributes = []): User
{
    return User::factory()->create($attributes);
}

// ─────────────────────────────────────────────────────── the backoffice manager

test('guests cannot reach the popup manager', function () {
    $this->get(route('backoffice.popups.index'))->assertRedirect(route('backoffice.login'));
});

test('the manager lists popups with the state of each one', function () {
    $this->actingAs(popupAdmin(), 'admin');

    Popup::factory()->create(['heading' => 'Every-visit offer']);
    Popup::factory()->inactive()->once()->create(['heading' => 'Retired offer']);

    $this->get(route('backoffice.popups.index'))
        ->assertOk()
        ->assertSee('Every-visit offer')
        ->assertSee('Retired offer')
        ->assertSee('Every visit')
        ->assertSee('Once only')
        ->assertSee('Disabled');
});

test('the manager creates a popup from nothing but a message', function () {
    $this->actingAs(popupAdmin(), 'admin');

    Livewire::test(ListPopups::class)
        ->call('openCreate')
        ->set('body', 'Stay connected, discover more ways to earn.')
        ->call('save')
        ->assertHasNoErrors()
        ->assertSet('showModal', false);

    $popup = Popup::query()->sole();

    expect($popup->body)->toBe('Stay connected, discover more ways to earn.')
        ->and($popup->title)->toBeNull()
        ->and($popup->heading)->toBeNull()
        ->and($popup->header_image)->toBeNull()
        ->and($popup->background_image)->toBeNull()
        ->and($popup->image_caption)->toBeNull()
        ->and($popup->primary_label)->toBeNull()
        ->and($popup->primary_url)->toBeNull()
        ->and($popup->secondary_label)->toBeNull()
        ->and($popup->secondary_url)->toBeNull()
        ->and($popup->footer_text)->toBeNull()
        ->and($popup->is_active)->toBeTrue()
        ->and($popup->is_recursive)->toBeTrue();
});

test('the popup form demands a message and a label for every link', function () {
    $this->actingAs(popupAdmin(), 'admin');

    Livewire::test(ListPopups::class)
        ->call('openCreate')
        ->call('save')
        ->assertHasErrors(['body' => 'required']);

    Livewire::test(ListPopups::class)
        ->set('body', 'Hello')
        ->set('primaryUrl', 'https://nextel.example/app')
        ->call('save')
        ->assertHasErrors(['primaryLabel' => 'required_with']);

    Livewire::test(ListPopups::class)
        ->set('body', 'Hello')
        ->set('secondaryLabel', 'Tap me')
        ->set('secondaryUrl', 'javascript:alert(1)')
        ->call('save')
        ->assertHasErrors(['secondaryUrl' => 'regex']);

    expect(Popup::query()->count())->toBe(0);
});

test('an admin edits, disables, makes one-off and deletes a popup', function () {
    $this->actingAs(popupAdmin(), 'admin');

    $popup = Popup::factory()->create(['body' => 'Original message']);

    Livewire::test(ListPopups::class)
        ->call('edit', $popup->id)
        ->assertSet('body', 'Original message')
        ->assertSet('isActive', true)
        ->set('heading', 'A better headline')
        ->call('save')
        ->assertHasNoErrors();

    expect($popup->fresh()->heading)->toBe('A better headline');

    Livewire::test(ListPopups::class)->call('toggleActive', $popup->id);
    expect($popup->fresh()->is_active)->toBeFalse();

    Livewire::test(ListPopups::class)->call('toggleRecursive', $popup->id);
    expect($popup->fresh()->is_recursive)->toBeFalse();

    Livewire::test(ListPopups::class)->call('delete', $popup->id);
    expect(Popup::query()->count())->toBe(0);
});

// ───────────────────────────────────────────────────────── the dashboard popup

test('an active popup opens on the dashboard and is recorded as seen', function () {
    $user = popupUser();

    $popup = Popup::factory()->create([
        'heading' => 'Take Nextel with you.',
        'body' => 'Stay connected wherever you go.',
    ]);

    $this->actingAs($user);

    Livewire::test(DashboardPopup::class)
        ->assertSet('open', true)
        ->assertSee('Take Nextel with you.')
        ->assertSee('Stay connected wherever you go.');

    $view = PopupView::query()->where('popup_id', $popup->id)->where('user_id', $user->id)->sole();

    expect($view->shown_count)->toBe(1)
        ->and($view->dismissed_at)->toBeNull()
        ->and($view->dismissed_count)->toBe(0);
});

test('a disabled popup never opens', function () {
    $this->actingAs(popupUser());

    Popup::factory()->inactive()->create(['heading' => 'Not this one']);

    Livewire::test(DashboardPopup::class)
        ->assertSet('open', false)
        ->assertDontSee('Not this one');
});

test('a one-off popup is spent the first time it is shown', function () {
    $user = popupUser();

    Popup::factory()->once()->create(['heading' => 'Only once']);

    $this->actingAs($user);

    Livewire::test(DashboardPopup::class)
        ->assertSee('Only once')
        ->call('dismiss');

    // Next visit it is not due any more, even though it is still enabled.
    Livewire::test(DashboardPopup::class)
        ->assertSet('open', false)
        ->assertDontSee('Only once');
});

test('a recursive popup comes back on the next visit', function () {
    $user = popupUser();

    Popup::factory()->create(['heading' => 'Every visit']);

    $this->actingAs($user);

    Livewire::test(DashboardPopup::class)->assertSee('Every visit')->call('dismiss');
    Livewire::test(DashboardPopup::class)->assertSee('Every visit');

    $view = PopupView::query()->sole();

    expect($view->shown_count)->toBe(2)
        ->and($view->dismissed_count)->toBe(1)
        ->and($view->dismissed_at)->not->toBeNull();
});

test('every popup due slides through once per visit', function () {
    $user = popupUser();

    $popups = collect(['Alpha offer', 'Beta offer', 'Gamma offer'])
        ->map(fn (string $heading): Popup => Popup::factory()->create(['heading' => $heading]));

    $this->actingAs($user);

    $component = Livewire::test(DashboardPopup::class);
    $component->assertViewHas('queued', 3)->assertViewHas('following', 2);

    $order = [];

    foreach (range(1, 3) as $step) {
        $html = $component->html();
        $shown = $popups->first(fn (Popup $popup): bool => str_contains($html, $popup->heading));

        expect($shown)->not->toBeNull('step '.$step.' showed none of the queued popups');

        $order[] = $shown->id;
        $component->call('dismiss');
    }

    // The queue is a random permutation: three different popups, each exactly once, and the
    // last dismissal closes the dialog.
    expect(array_unique($order))->toHaveCount(3)
        ->and($component->get('open'))->toBeFalse()
        ->and(PopupView::query()->where('user_id', $user->id)->where('shown_count', 1)->count())->toBe(3)
        ->and(PopupView::query()->where('user_id', $user->id)->whereNotNull('dismissed_at')->count())->toBe(3);
});

test('a popup with only a message leaves the optional pieces out', function () {
    $this->actingAs(popupUser());

    Popup::factory()->create([
        'title' => null,
        'heading' => null,
        'image_caption' => null,
        'header_image' => null,
        'background_image' => null,
        'primary_label' => null,
        'primary_url' => null,
        'secondary_label' => null,
        'secondary_url' => null,
        'footer_text' => null,
        'body' => 'Just the message.',
    ]);

    $html = Livewire::test(DashboardPopup::class)->html();

    expect($html)->toContain('Just the message.')
        ->not->toContain('<h2')
        ->not->toContain('<img')
        ->not->toContain('underline');
});

test('a fully built popup renders every piece', function () {
    $this->actingAs(popupUser());

    Popup::factory()->create([
        'title' => 'Your Nextel experience, upgraded',
        'heading' => 'Take Nextel with you.',
        'body' => 'Stay connected, discover more ways to earn.',
        'image_caption' => 'More ways to connect',
        'header_image' => 'popup-images/header.jpg',
        'background_image' => 'popup-images/background.jpg',
        'primary_label' => 'Download the app',
        'primary_url' => 'https://nextel.example/app',
        'secondary_label' => 'Explore the Nextel app',
        'secondary_url' => '/dashboard/my-website',
        'footer_text' => 'Using iPhone? See iOS availability',
    ]);

    Livewire::test(DashboardPopup::class)
        ->assertSee('Your Nextel experience, upgraded')
        ->assertSee('Take Nextel with you.')
        ->assertSee('More ways to connect')
        ->assertSee('Download the app')
        ->assertSee('https://nextel.example/app')
        ->assertSee('Explore the Nextel app')
        ->assertSee('Using iPhone? See iOS availability');
});

test('the dashboard page opens the popup for an onboarded user', function () {
    $user = activatedUser(['transaction_pin' => '1234']);

    Popup::factory()->create(['heading' => 'Dashboard promo']);
    Popup::factory()->inactive()->create(['heading' => 'Retired promo']);

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSee('Dashboard promo')
        ->assertDontSee('Retired promo');
});
