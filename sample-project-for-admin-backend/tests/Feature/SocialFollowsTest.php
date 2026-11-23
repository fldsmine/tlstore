<?php

use App\Livewire\Backoffice\SocialFollows\ManageSocialFollows;
use App\Livewire\Dashboard\SocialFollows as UserSocialFollows;
use App\Models\Admin;
use App\Models\SocialFollow;
use App\Models\SocialFollowProof;
use App\Models\SocialHandle;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

function requiredSocialHandle(array $attributes = []): SocialHandle
{
    return SocialHandle::query()->create(array_merge([
        'platform' => 'instagram',
        'name' => 'Nextel on Instagram',
        'handle' => '@nextelconnect',
        'url' => 'https://instagram.com/nextelconnect',
        'description' => 'Follow the official Nextel page.',
        'sort_order' => 0,
        'is_active' => true,
    ], $attributes));
}

function socialFollowReadyUser(array $attributes = []): User
{
    return User::factory()->create(array_merge([
        'package_id' => null,
        'transaction_pin' => bcrypt('1234'),
    ], $attributes));
}

test('pending social pages gate the whole dashboard until each follow is confirmed', function () {
    $user = socialFollowReadyUser();
    $first = requiredSocialHandle(['name' => 'Nextel on Instagram']);
    $second = requiredSocialHandle([
        'platform' => 'telegram',
        'name' => 'Nextel on Telegram',
        'handle' => '@nextelupdates',
        'url' => 'https://t.me/nextelupdates',
        'sort_order' => 1,
    ]);

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSee('Follow our social pages')
        ->assertSee('Nextel on Instagram')
        ->assertSee('Nextel on Telegram')
        ->assertSee('cannot independently verify')
        ->assertDontSee('Close social follows');

    $this->get(route('profile'))->assertRedirect(route('dashboard'));
    $this->get(route('social-follows'))
        ->assertRedirect(route('dashboard', ['showSocialFollows' => 1]));

    Livewire::test(UserSocialFollows::class)
        ->call('close')
        ->assertSet('isOpen', false)
        ->assertSee('Follow our social pages');

    $followModal = Livewire::test(UserSocialFollows::class)
        ->call('confirmFollow', $first->id)
        ->assertHasNoErrors()
        ->assertSee('Follow our social pages');

    expect(SocialFollow::query()->where('user_id', $user->id)->where('social_handle_id', $first->id)->value('followed_at'))
        ->not->toBeNull();

    $this->get(route('dashboard'))->assertOk()->assertSee('Follow our social pages');

    $followModal->call('confirmFollow', $second->id)
        ->assertHasNoErrors()
        ->assertDontSee('Follow our social pages');

    $this->get(route('dashboard'))->assertOk()->assertDontSee('Follow our social pages');
    $this->get(route('profile'))->assertOk();
});

test('optional documentation proofs are stored separately and never grant dashboard access', function () {
    $user = socialFollowReadyUser();
    $handle = requiredSocialHandle();
    Storage::fake('social_proofs');
    $this->actingAs($user);

    Livewire::test(UserSocialFollows::class)
        ->set('proofs.'.$handle->id, UploadedFile::fake()->image('follow-proof.png'))
        ->call('uploadProof', $handle->id)
        ->assertHasNoErrors()
        ->assertSee('Requested for documentation only')
        ->assertSee('Proof on file');

    $proof = SocialFollowProof::query()->sole();
    expect($proof->user_id)->toBe($user->id)
        ->and($proof->social_handle_id)->toBe($handle->id)
        ->and($proof->target_url)->toBe($handle->url)
        ->and(SocialFollow::query()->count())->toBe(0);
    Storage::disk('social_proofs')->assertExists($proof->path);

    $this->get(route('dashboard'))->assertOk()->assertSee('Follow our social pages');

    Livewire::test(UserSocialFollows::class)
        ->call('confirmFollow', $handle->id)
        ->assertHasNoErrors();

    expect(SocialFollow::query()->where('user_id', $user->id)->where('social_handle_id', $handle->id)->value('followed_at'))
        ->not->toBeNull();
    $this->get(route('dashboard'))->assertOk()->assertDontSee('Follow our social pages');
});

test('backoffice documentation uploads are accessible without creating follow confirmations', function () {
    $admin = Admin::factory()->regular()->create();
    $user = socialFollowReadyUser();
    $handle = requiredSocialHandle();
    Storage::fake('social_proofs');

    $file = UploadedFile::fake()->image('follow-proof.png');
    $proof = SocialFollowProof::query()->create([
        'user_id' => $user->id,
        'social_handle_id' => $handle->id,
        'path' => $file->store($user->id.'/'.$handle->id, 'social_proofs'),
        'original_name' => 'follow-proof.png',
        'target_url' => $handle->url,
    ]);

    $this->actingAs($admin, 'admin');

    Livewire::test(ManageSocialFollows::class)
        ->call('setTab', 'proofs')
        ->assertSee($user->username)
        ->assertSee($handle->name)
        ->assertSee('follow-proof.png')
        ->assertSee('do not approve a follow')
        ->call('downloadProof', $proof->id)
        ->assertFileDownloaded('follow-proof.png');

    expect(SocialFollow::query()->count())->toBe(0);
});

test('the social follows shortcut opens a closable modal on the dashboard when nothing is pending', function () {
    $user = socialFollowReadyUser();
    $handle = requiredSocialHandle();
    $this->actingAs($user);

    Livewire::test(UserSocialFollows::class)->call('confirmFollow', $handle->id);

    $this->get(route('social-follows'))
        ->assertRedirect(route('dashboard', ['showSocialFollows' => 1]));

    $this->get(route('dashboard', ['showSocialFollows' => 1]))
        ->assertOk()
        ->assertSee('Your social follows')
        ->assertSee('Close social follows')
        ->assertSee('Confirmed pages');

    Livewire::test(UserSocialFollows::class)
        ->dispatch('open-social-follows')
        ->assertSet('isOpen', true)
        ->assertSee('Your social follows')
        ->call('close')
        ->assertSet('isOpen', false)
        ->assertDontSee('Your social follows');
});

test('disabled social pages do not gate dashboard access', function () {
    $user = socialFollowReadyUser();
    requiredSocialHandle(['is_active' => false]);

    $this->actingAs($user)->get(route('dashboard'))->assertOk()->assertDontSee('Follow our social pages');
});

test('a user can mark a follow as removed and must confirm again to unlock the dashboard', function () {
    $user = socialFollowReadyUser();
    $handle = requiredSocialHandle();
    $this->actingAs($user);

    Livewire::test(UserSocialFollows::class)->call('confirmFollow', $handle->id);
    $this->get(route('dashboard'))->assertOk();

    Livewire::test(UserSocialFollows::class)
        ->call('markUnfollowed', $handle->id)
        ->assertHasNoErrors();

    $follow = SocialFollow::query()->where('user_id', $user->id)->where('social_handle_id', $handle->id)->sole();
    expect($follow->unfollowed_at)->not->toBeNull()
        ->and($follow->unfollowed_by_admin_id)->toBeNull();

    $this->get(route('dashboard'))->assertOk()->assertSee('Follow our social pages');

    Livewire::test(UserSocialFollows::class)->call('confirmFollow', $handle->id);
    expect($follow->fresh()->unfollowed_at)->toBeNull();
    $this->get(route('dashboard'))->assertOk()->assertDontSee('Follow our social pages');
});

test('backoffice admins can create pages, review follow records, reset a confirmation and disable a requirement', function () {
    $admin = Admin::factory()->regular()->create();
    $user = socialFollowReadyUser();
    $this->actingAs($admin, 'admin');

    Livewire::test(ManageSocialFollows::class)
        ->call('openCreate')
        ->set('platform', 'instagram')
        ->set('name', 'Nextel Connect Instagram')
        ->set('handle', '@nextelconnect')
        ->set('url', 'https://instagram.com/nextelconnect')
        ->set('description', 'Official updates')
        ->set('sortOrder', '1')
        ->set('isActive', true)
        ->call('save')
        ->assertHasNoErrors()
        ->assertSet('showModal', false);

    $handle = SocialHandle::query()->sole();
    expect($handle->created_by_admin_id)->toBe($admin->id)
        ->and($handle->is_active)->toBeTrue();

    $follow = SocialFollow::query()->create([
        'user_id' => $user->id,
        'social_handle_id' => $handle->id,
        'followed_at' => now(),
    ]);

    Livewire::test(ManageSocialFollows::class)
        ->call('setTab', 'follows')
        ->assertSee($user->username)
        ->assertSee($handle->name)
        ->call('resetFollow', $follow->id)
        ->assertHasNoErrors();

    expect($follow->fresh()->unfollowed_at)->not->toBeNull()
        ->and($follow->fresh()->unfollowed_by_admin_id)->toBe($admin->id);

    $this->actingAs($user)->get(route('dashboard'))->assertOk()->assertSee('Follow our social pages');

    $this->actingAs($admin, 'admin');
    Livewire::test(ManageSocialFollows::class)->call('toggleActive', $handle->id)->assertHasNoErrors();
    expect($handle->fresh()->is_active)->toBeFalse();

    $this->actingAs($user)->get(route('dashboard'))->assertOk()->assertDontSee('Follow our social pages');
});

test('changing a required page target resets its existing confirmations', function () {
    $admin = Admin::factory()->regular()->create();
    $user = socialFollowReadyUser();
    $handle = requiredSocialHandle();
    $follow = SocialFollow::query()->create([
        'user_id' => $user->id,
        'social_handle_id' => $handle->id,
        'followed_at' => now(),
    ]);

    $this->actingAs($admin, 'admin');

    Livewire::test(ManageSocialFollows::class)
        ->call('edit', $handle->id)
        ->set('url', 'https://instagram.com/nextelconnectofficial')
        ->call('save')
        ->assertHasNoErrors();

    expect($follow->fresh()->unfollowed_at)->not->toBeNull()
        ->and($follow->fresh()->unfollowed_by_admin_id)->toBe($admin->id);

    $this->actingAs($user)->get(route('dashboard'))->assertOk()->assertSee('Follow our social pages');
});

test('backoffice only accepts safe public http social page URLs', function () {
    $this->actingAs(Admin::factory()->regular()->create(), 'admin');

    Livewire::test(ManageSocialFollows::class)
        ->call('openCreate')
        ->set('platform', 'instagram')
        ->set('name', 'Unsafe link')
        ->set('url', 'javascript:alert(1)')
        ->call('save')
        ->assertHasErrors(['url']);

    expect(SocialHandle::query()->count())->toBe(0);
});
