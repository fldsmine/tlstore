<?php

use App\Models\Announcement;
use App\Models\Country;

test('countries are public, active and sorted by name', function () {
    Country::factory()->create(['name' => 'Zambia', 'code' => 'ZM']);
    Country::factory()->create(['name' => 'Algeria', 'code' => 'DZ']);
    Country::factory()->create(['name' => 'Hidden', 'code' => 'XX', 'is_active' => false]);

    $response = $this->getJson('/api/v1/countries')->assertOk();

    $names = array_column($response->json('data.countries'), 'name');

    expect($names)->toBe(['Algeria', 'Zambia']);
});

test('announcements are public and only live ones show', function () {
    $live = Announcement::factory()->create();
    Announcement::factory()->create(['is_active' => false]);
    Announcement::factory()->create(['starts_at' => now()->addDay()]);
    Announcement::factory()->create(['ends_at' => now()->subDay()]);

    $response = $this->getJson('/api/v1/announcements')->assertOk();

    $ids = array_column($response->json('data.announcements'), 'id');

    expect($ids)->toBe([$live->id]);
});

test('announcement payloads expose action fields', function () {
    Announcement::factory()->create([
        'title' => 'Update the app',
        'action_label' => 'Download',
        'action_url' => 'https://example.com/app',
    ]);

    $this->getJson('/api/v1/announcements')
        ->assertOk()
        ->assertJsonPath('data.announcements.0.title', 'Update the app')
        ->assertJsonPath('data.announcements.0.action_label', 'Download')
        ->assertJsonPath('data.announcements.0.action_url', 'https://example.com/app');
});
