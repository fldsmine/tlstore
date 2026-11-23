<?php

use App\Enums\LoginChannel;
use App\Livewire\Backoffice\Reports\InboxOpens;
use App\Livewire\Backoffice\Reports\ShowInboxOpens;
use App\Livewire\Backoffice\Reports\ShowSignIns;
use App\Livewire\Backoffice\Reports\SignIns;
use App\Models\Admin;
use App\Models\CloudInboxMessage;
use App\Models\User;
use App\Models\UserLoginEvent;
use App\Services\Auth\LoginService;
use Livewire\Livewire;

function reportsAdmin(): Admin
{
    return Admin::factory()->regular()->create();
}

function reportUser(array $attributes = []): User
{
    return User::factory()->create($attributes);
}

/** A Cloud Inbox message, opened (or not) at a given moment. */
function inboxMessage(User $user, bool $opened = true, array $attributes = []): CloudInboxMessage
{
    return CloudInboxMessage::factory()->create(array_merge([
        'user_id' => $user->id,
        'read_at' => $opened ? now() : null,
    ], $attributes));
}

// ─────────────────────────────────────────────────────────── sign-in recording

test('every sign-in is recorded with the surface it came from', function () {
    $user = reportUser();
    $logins = app(LoginService::class);

    $logins->touchLastLogin($user, LoginChannel::WEB);
    $logins->touchLastLogin($user, LoginChannel::APP);
    $logins->touchLastLogin($user, 'telegram');

    expect(UserLoginEvent::query()->where('user_id', $user->id)->count())->toBe(3)
        ->and(UserLoginEvent::query()->where('user_id', $user->id)->where('channel', 'telegram')->count())->toBe(1)
        ->and($user->fresh()->last_login_at)->not->toBeNull();
});

test('the website login records a website sign-in', function () {
    $user = reportUser();

    // The web login (Livewire Auth\Login) passes the channel explicitly; the service default is
    // the website too, so a missing argument can never record the wrong surface. Asserted here at
    // the service level: the Livewire component calls request()->session()->regenerate(), which
    // has no session store inside Livewire::test().
    app(LoginService::class)->touchLastLogin($user);

    $event = UserLoginEvent::query()->where('user_id', $user->id)->first();

    expect($event)->not->toBeNull()
        ->and($event->channel)->toBe(LoginChannel::WEB)
        ->and($event->channelLabel())->toBe('Website')
        ->and($event->device())->not->toBe('');
});

test('the mobile login endpoint records a mobile-app sign-in', function () {
    $user = reportUser(['password' => 'SuperSecret123!']);

    $this->postJson('/api/v1/login', ['login' => $user->email, 'password' => 'SuperSecret123!'])
        ->assertOk();

    expect(UserLoginEvent::query()->where('user_id', $user->id)->where('channel', 'app')->count())->toBe(1);
});

// ─────────────────────────────────────────────────────────── sign-in report

test('guests cannot reach the report pages', function () {
    $user = reportUser();

    $this->get(route('backoffice.reports.signins.index'))->assertRedirect(route('backoffice.login'));
    $this->get(route('backoffice.reports.signins.show', $user))->assertRedirect(route('backoffice.login'));
    $this->get(route('backoffice.reports.inbox-opens.index'))->assertRedirect(route('backoffice.login'));
    $this->get(route('backoffice.reports.inbox-opens.show', $user))->assertRedirect(route('backoffice.login'));
});

test('the sign-in report shows today and leaves yesterday behind', function () {
    $today = reportUser(['first_name' => 'Tolu', 'last_name' => 'Ade', 'username' => 'toluade']);
    $yesterday = reportUser(['first_name' => 'Bisi', 'last_name' => 'Ojo', 'username' => 'bisiojo']);

    UserLoginEvent::query()->create([
        'user_id' => $today->id,
        'channel' => LoginChannel::TELEGRAM->value,
        'ip_address' => '102.89.34.10',
        'user_agent' => 'Mozilla/5.0 (Linux; Android 14) Telegram-Android',
        'created_at' => now(),
    ]);

    UserLoginEvent::query()->create([
        'user_id' => $yesterday->id,
        'channel' => LoginChannel::WEB->value,
        'ip_address' => '102.89.34.11',
        'user_agent' => 'Mozilla/5.0 (Windows NT 10.0) Chrome/120',
        'created_at' => now()->subDay(),
    ]);

    $this->actingAs(reportsAdmin(), 'admin')
        ->get(route('backoffice.reports.signins.index'))
        ->assertOk()
        ->assertSee('Tolu Ade')
        ->assertSee('Telegram Mini App')
        ->assertSee('102.89.34.10')
        ->assertDontSee('Bisi Ojo');

    Livewire::test(SignIns::class)
        ->assertViewHas('total', 1)
        ->assertViewHas('uniqueUsers', 1)
        ->call('setChannel', 'telegram')
        ->assertViewHas('total', 1)
        ->call('setChannel', 'app')
        ->assertViewHas('total', 0)
        ->call('setChannel', 'all')
        ->call('shiftDay', -1)
        ->assertViewHas('total', 1)
        ->call('today')
        ->assertViewHas('total', 1);
});

test('the sign-in report falls back to the last-known sign-in before the log existed', function () {
    // No event row: the user signed in before this log was deployed.
    $legacy = reportUser(['first_name' => 'Kemi', 'last_name' => 'Bello', 'username' => 'kemibello', 'last_login_at' => now()]);

    $this->actingAs(reportsAdmin(), 'admin')
        ->get(route('backoffice.reports.signins.index'))
        ->assertOk()
        ->assertSee('Kemi Bello')
        ->assertSee('before per-sign-in history was recorded');

    expect(UserLoginEvent::query()->count())->toBe(0)
        ->and($legacy->fresh()->last_login_at)->not->toBeNull();
});

test('the sign-in detail page lists every recorded sign-in for one user', function () {
    $admin = reportsAdmin();
    $user = reportUser(['first_name' => 'Ada', 'last_name' => 'Okafor', 'username' => 'adaokafor']);
    reportUser(['username' => 'someoneelse']);

    foreach ([LoginChannel::WEB, LoginChannel::APP, LoginChannel::TELEGRAM] as $i => $channel) {
        UserLoginEvent::query()->create([
            'user_id' => $user->id,
            'channel' => $channel->value,
            'ip_address' => '10.0.0.'.($i + 1),
            'user_agent' => 'Mozilla/5.0 (Linux; Android 14) Chrome/120',
            'created_at' => now(),
        ]);
    }

    $this->actingAs($admin, 'admin')
        ->get(route('backoffice.reports.signins.show', $user))
        ->assertOk()
        ->assertSee('Ada Okafor')
        ->assertSee('Website')
        ->assertSee('Mobile app')
        ->assertSee('Telegram Mini App');

    Livewire::test(SignIns::class)
        ->assertViewHas('total', 3);

    Livewire::test(ShowSignIns::class, ['user' => $user])
        ->assertViewHas('total', 3)
        ->assertViewHas('today', 3)
        ->set('channel', 'telegram')
        ->assertViewHas('total', 1);
});

// ─────────────────────────────────────────────────────────── Cloud Inbox report

test('the Cloud Inbox report lists only users who actually opened their SMS', function () {
    $opener = reportUser(['first_name' => 'Ngozi', 'last_name' => 'Eze', 'username' => 'ngozieze']);
    $ignorer = reportUser(['first_name' => 'Ife', 'last_name' => 'Dara', 'username' => 'ifedara']);

    inboxMessage($opener, opened: true);
    inboxMessage($opener, opened: false);
    inboxMessage($ignorer, opened: false);

    $this->actingAs(reportsAdmin(), 'admin')
        ->get(route('backoffice.reports.inbox-opens.index'))
        ->assertOk()
        ->assertSee('Ngozi Eze')
        ->assertDontSee('Ife Dara');

    Livewire::test(InboxOpens::class)
        ->assertViewHas('openers', 1)
        ->assertViewHas('openedMessages', 1)
        ->assertViewHas('deliveredMessages', 3);

    // Nobody opened today in the "today" window? They did — read_at is 5 minutes ago.
    Livewire::test(InboxOpens::class)
        ->call('setRange', 'today')
        ->assertViewHas('openers', 1)
        ->call('setClaim', 'claimed')
        ->assertViewHas('openers', 0);
});

test('the Cloud Inbox detail page lists the messages and flags the unopened ones', function () {
    $user = reportUser(['first_name' => 'Bola', 'last_name' => 'Ibrahim', 'username' => 'bolaibrahim']);

    inboxMessage($user, opened: true, attributes: [
        'type' => CloudInboxMessage::TYPE_SPONSORED,
        'sender_name' => 'QuickMart',
        'subject' => 'Weekend offer',
        'earn_rate' => 0.25,
        'claimed_at' => now()->subMinute(),
    ]);
    inboxMessage($user, opened: false, attributes: ['sender_name' => 'Nextel', 'subject' => 'Unread note']);

    $this->actingAs(reportsAdmin(), 'admin')
        ->get(route('backoffice.reports.inbox-opens.show', $user))
        ->assertOk()
        ->assertSee('Bola Ibrahim')
        ->assertSee('QuickMart')
        ->assertSee('Unread note');

    Livewire::test(ShowInboxOpens::class, ['user' => $user])
        ->assertViewHas('received', 2)
        ->assertViewHas('opened', 1)
        ->assertViewHas('unread', 1)
        ->assertViewHas('claimed', 1)
        ->set('state', 'unread')
        ->assertSee('Unread note')
        ->assertDontSee('Weekend offer');
});

test('the reports page links to both focused reports', function () {
    $this->actingAs(reportsAdmin(), 'admin')
        ->get(route('backoffice.reports.index'))
        ->assertOk()
        ->assertSee('Sign-ins')
        ->assertSee('Cloud Inbox opens')
        ->assertSee(route('backoffice.reports.signins.index'), escape: false)
        ->assertSee(route('backoffice.reports.inbox-opens.index'), escape: false);
});
