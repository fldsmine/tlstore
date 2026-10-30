<?php

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

test('users can update their profile', function () {
    $user = apiUser();

    $this->actingAs($user, 'sanctum')
        ->putJson('/api/v1/profile', [
            'first_name' => 'Grace',
            'last_name' => 'Hopper',
            'phone' => '08055555555',
            'telegram' => 'gracehopper',
            'biography' => 'Compiler pioneer.',
        ])
        ->assertOk()
        ->assertJsonPath('data.user.first_name', 'Grace');

    expect($user->refresh()->biography)->toBe('Compiler pioneer.');
});

test('profile update rejects another user\'s phone number', function () {
    $other = apiUser(['phone' => '08011111111']);
    $user = apiUser();

    $this->actingAs($user, 'sanctum')
        ->putJson('/api/v1/profile', ['first_name' => 'X', 'phone' => '08011111111'])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['phone']);
});

test('profile update validates required first name', function () {
    $user = apiUser();

    $this->actingAs($user, 'sanctum')
        ->putJson('/api/v1/profile', [])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['first_name']);
});

test('users can upload an avatar', function () {
    Storage::fake(config('filesystems.default'));

    $user = apiUser();

    $this->actingAs($user, 'sanctum')
        ->post('/api/v1/profile/avatar', [
            'photo' => UploadedFile::fake()->image('avatar.jpg', 600, 600),
        ], ['Accept' => 'application/json'])
        ->assertOk()
        ->assertJsonPath('success', true);

    expect($user->refresh()->avatar)->not->toBeNull();
});

test('avatar upload rejects non-images', function () {
    $user = apiUser();

    $this->actingAs($user, 'sanctum')
        ->post('/api/v1/profile/avatar', [
            'photo' => UploadedFile::fake()->create('evil.php', 10, 'text/x-php'),
        ], ['Accept' => 'application/json'])
        ->assertStatus(422);
});
