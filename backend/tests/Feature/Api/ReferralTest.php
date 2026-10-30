<?php

test('overview exposes the referral code, counts and earnings', function () {
    $referrer = activatedUser(['referral_bonus_balance' => 2.5]);
    $child = activatedUser(['referrer_id' => $referrer->id]);
    activatedUser(['referrer_id' => $child->id]); // grandchild → indirect

    $response = $this->actingAs($referrer, 'sanctum')
        ->getJson('/api/v1/referrals')
        ->assertOk();

    expect($response->json('data.code'))->toBe($referrer->username)
        ->and($response->json('data.direct_count'))->toBe(1)
        ->and($response->json('data.indirect_count'))->toBe(1)
        ->and((float) $response->json('data.earned_usd'))->toBe(2.5)
        ->and(str_contains($response->json('data.link'), $referrer->username))->toBeTrue();
});

test('direct and indirect members are listed with their level', function () {
    $referrer = activatedUser();
    $child = activatedUser(['referrer_id' => $referrer->id]);
    $grandchild = activatedUser(['referrer_id' => $child->id]);

    $response = $this->actingAs($referrer, 'sanctum')
        ->getJson('/api/v1/referrals')
        ->assertOk();

    $directIds = array_column($response->json('data.direct'), 'id');
    $indirectIds = array_column($response->json('data.indirect'), 'id');

    expect($directIds)->toBe([$child->id])
        ->and($indirectIds)->toBe([$grandchild->id])
        ->and($response->json('data.direct.0.level'))->toBe('direct')
        ->and($response->json('data.indirect.0.level'))->toBe('indirect');
});

test('referrals never leak across users', function () {
    $referrer = activatedUser();
    activatedUser(['referrer_id' => $referrer->id]);
    $other = activatedUser();

    $this->actingAs($other, 'sanctum')
        ->getJson('/api/v1/referrals')
        ->assertOk()
        ->assertJsonPath('data.direct_count', 0)
        ->assertJsonPath('data.indirect_count', 0);
});

test('search filters members by name or username', function () {
    $referrer = activatedUser();
    $child = activatedUser(['referrer_id' => $referrer->id, 'username' => 'unique_kid']);
    activatedUser(['referrer_id' => $referrer->id, 'username' => 'someone_else']);

    $this->actingAs($referrer, 'sanctum')
        ->getJson('/api/v1/referrals?search=unique_kid')
        ->assertOk()
        ->assertJsonPath('data.direct_count', 2) // counts stay global…
        ->assertJsonCount(1, 'data.direct'); // …but the list is filtered
});
