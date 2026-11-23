<?php

use App\Enums\RewardTargetStatus;
use App\Enums\TransactionType;
use App\Models\RewardTarget;
use App\Models\Transaction;

function rewardProgressUser(): \App\Models\User
{
    $user = activatedUser();

    // A referral-bonus credit with the level meta TargetRewardService::progress() looks for.
    Transaction::factory()->create([
        'user_id' => $user->id,
        'type' => TransactionType::REFERRAL_BONUS,
        'direction' => 'credit',
        'amount' => 25.0,
        'wallet' => 'referral_bonus',
        'meta' => ['level' => 'direct'],
        'created_at' => now()->addMinute(),
    ]);

    return $user;
}

test('rewards screen starts empty for a new user', function () {
    $user = activatedUser();

    $this->actingAs($user, 'sanctum')
        ->getJson('/api/v1/rewards')
        ->assertOk()
        ->assertJsonPath('data.enabled', true)
        ->assertJsonPath('data.today_target', null)
        ->assertJsonPath('data.lifetime_earned_usd', 0);
});

test('a daily target can be set once', function () {
    $user = activatedUser();

    $this->actingAs($user, 'sanctum')
        ->postJson('/api/v1/rewards/target', ['amount' => 20])
        ->assertCreated()
        ->assertJsonPath('data.target.target_amount_usd', 20)
        ->assertJsonPath('data.target.status', 'active');

    $this->actingAs($user, 'sanctum')
        ->postJson('/api/v1/rewards/target', ['amount' => 99])
        ->assertStatus(422);
});

test('target amounts must be positive', function () {
    $user = activatedUser();

    $this->actingAs($user, 'sanctum')
        ->postJson('/api/v1/rewards/target', ['amount' => 0])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['amount']);
});

test('hitting the target unlocks and crediting claims it', function () {
    $user = rewardProgressUser();

    $this->actingAs($user, 'sanctum')
        ->postJson('/api/v1/rewards/target', ['amount' => 20])
        ->assertCreated();

    // Evaluate flips the target to achieved (25 >= 20).
    $response = $this->actingAs($user, 'sanctum')
        ->getJson('/api/v1/rewards')
        ->assertOk()
        ->assertJsonPath('data.today_target.status', 'achieved');

    expect((float) $response->json('data.today_target.progress_usd'))->toBe(25.0);

    $target = RewardTarget::where('user_id', $user->id)->first();

    $this->actingAs($user, 'sanctum')
        ->postJson('/api/v1/rewards/'.$target->id.'/claim')
        ->assertOk();

    // 4% of 20 = 0.8 lands in the reward wallet.
    expect((float) $user->refresh()->reward_balance)->toBe(0.8)
        ->and($target->refresh()->status)->toBe(RewardTargetStatus::CLAIMED);
});

test('claiming an unachieved target is rejected', function () {
    $user = activatedUser();

    $this->actingAs($user, 'sanctum')
        ->postJson('/api/v1/rewards/target', ['amount' => 10])
        ->assertCreated();

    $target = RewardTarget::where('user_id', $user->id)->first();

    $this->actingAs($user, 'sanctum')
        ->postJson('/api/v1/rewards/'.$target->id.'/claim')
        ->assertStatus(422);
});

test('targets belong to their owner', function () {
    $user = activatedUser();
    $stranger = RewardTarget::factory()->create();

    $this->actingAs($user, 'sanctum')
        ->postJson('/api/v1/rewards/'.$stranger->id.'/claim')
        ->assertStatus(404);
});

test('claim all is idempotent when nothing is unlocked', function () {
    $user = activatedUser();

    $this->actingAs($user, 'sanctum')
        ->postJson('/api/v1/rewards/claim-all')
        ->assertOk()
        ->assertJsonPath('data.amount_usd', 0);
});
