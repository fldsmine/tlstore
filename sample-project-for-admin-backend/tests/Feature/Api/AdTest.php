<?php

use App\Models\Ad;

test('ads are listed newest-first with earning state', function () {
    $user = activatedUser();
    $older = Ad::factory()->create(['reward' => 0.05]);
    $newer = Ad::factory()->create(['reward' => 0.10]);
    Ad::factory()->inactive()->create();

    $response = $this->actingAs($user, 'sanctum')
        ->getJson('/api/v1/ads')
        ->assertOk();

    $ids = array_column($response->json('data.ads'), 'id');

    expect($ids)->toBe([$newer->id, $older->id])
        ->and($response->json('data.enabled'))->toBeTrue()
        ->and($response->json('data.lifetime_usd'))->toEqual(0.0);
});

test('sharing an ad pays the ads revenue wallet exactly once', function () {
    $user = activatedUser();
    $ad = Ad::factory()->create(['reward' => 0.10]);

    $this->actingAs($user, 'sanctum')
        ->postJson('/api/v1/ads/'.$ad->id.'/share')
        ->assertOk()
        ->assertJsonPath('data.amount_usd', 0.1);

    expect((float) $user->refresh()->ads_revenue_balance)->toBe(0.10);

    // The list now marks it as earned…
    $this->actingAs($user, 'sanctum')
        ->getJson('/api/v1/ads')
        ->assertOk()
        ->assertJsonPath('data.today_usd', 0.1);

    // …and a second share earns nothing.
    $this->actingAs($user, 'sanctum')
        ->postJson('/api/v1/ads/'.$ad->id.'/share')
        ->assertStatus(422);

    expect((float) $user->refresh()->ads_revenue_balance)->toBe(0.10);
});

test('inactive ads cannot be shared', function () {
    $user = activatedUser();
    $inactive = Ad::factory()->inactive()->create();

    $this->actingAs($user, 'sanctum')
        ->postJson('/api/v1/ads/'.$inactive->id.'/share')
        ->assertStatus(404);
});

test('sharing requires an activated account', function () {
    $user = apiUser(); // no package
    $ad = Ad::factory()->create();

    $this->actingAs($user, 'sanctum')
        ->postJson('/api/v1/ads/'.$ad->id.'/share')
        ->assertStatus(403);
});
