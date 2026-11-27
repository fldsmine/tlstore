<?php

namespace Tests\Feature;

use App\Models\PaymentIntent;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Stale checkouts must not leave phantom `pending` rows in a user's history
 * (ERR-10). Both the scheduled `payments:expire-intents` command and the
 * user-facing cancel endpoint close the intent *and* its projection.
 */
class PaymentIntentExpiryTest extends TestCase
{
    use RefreshDatabase;

    public function test_command_expires_stale_checkouts_and_their_projections(): void
    {
        $user = $this->user();
        $stale = $this->intent($user, status: 'pending', expiresAt: now()->subMinutes(5));

        $this->artisan('payments:expire-intents')->assertExitCode(0);

        $this->assertSame('expired', $stale->fresh()->status->value);
        $this->assertSame(
            'expired',
            Transaction::query()->where('reference', 'payment-intent:'.$stale->id)->firstOrFail()->status,
        );
    }

    public function test_command_leaves_open_paid_and_manual_checkouts_untouched(): void
    {
        $user = $this->user();
        $open = $this->intent($user, status: 'pending', expiresAt: now()->addHour());
        $paid = $this->intent($user, status: 'paid', expiresAt: now()->subDay());
        $manual = $this->intent($user, status: 'pending', expiresAt: now()->subDay(), provider: 'manual', purpose: 'manual_deposit');

        $this->artisan('payments:expire-intents')->assertExitCode(0);

        $this->assertSame('pending', $open->fresh()->status->value);
        $this->assertSame('pending', Transaction::query()->where('reference', 'payment-intent:'.$open->id)->value('status'));

        $this->assertSame('paid', $paid->fresh()->status->value);
        $this->assertSame('pending', Transaction::query()->where('reference', 'payment-intent:'.$paid->id)->value('status'));

        // Manual bank transfers have no checkout window: an admin settles them.
        $this->assertSame('pending', $manual->fresh()->status->value);
        $this->assertSame('pending', Transaction::query()->where('reference', 'payment-intent:'.$manual->id)->value('status'));
    }

    public function test_cancelling_a_checkout_expires_the_intent_and_closes_the_projection(): void
    {
        $user = $this->user();
        $intent = $this->intent($user, status: 'pending', expiresAt: now()->addHours(2));

        $token = $user->createToken('cancel-test')->plainTextToken;

        $this->withToken($token)
            ->postJson('/api/v1/topups/'.$intent->id.'/cancel')
            ->assertOk()
            ->assertJsonPath('data.status', 'expired');

        $this->assertSame('expired', $intent->fresh()->status->value);
        $this->assertSame(
            'expired',
            Transaction::query()->where('reference', 'payment-intent:'.$intent->id)->firstOrFail()->status,
        );
    }

    public function test_expiry_never_downgrades_a_completed_projection(): void
    {
        $user = $this->user();
        $intent = $this->intent($user, status: 'pending', expiresAt: now()->subMinute());
        Transaction::query()->where('reference', 'payment-intent:'.$intent->id)->update(['status' => 'completed']);

        $this->artisan('payments:expire-intents')->assertExitCode(0);

        $this->assertSame('expired', $intent->fresh()->status->value, 'the intent is still stale');
        $this->assertSame(
            'completed',
            Transaction::query()->where('reference', 'payment-intent:'.$intent->id)->firstOrFail()->status,
            'money that moved must never be rewritten to expired',
        );
    }

    private function user(): User
    {
        return User::query()->create([
            'full_name' => 'Expiry User',
            'username' => 'expiry-'.Str::lower(Str::random(8)),
            'email' => Str::lower(Str::random(8)).'@example.test',
            'preferred_currency' => 'NGN',
            'wallet_currency' => 'NGN',
            'role' => 'reviewer',
            'status' => 'active',
        ]);
    }

    private function intent(User $user, string $status, \DateTimeInterface $expiresAt, string $provider = 'paystack', string $purpose = 'topup'): PaymentIntent
    {
        $intent = PaymentIntent::query()->create([
            'user_id' => $user->id,
            'purpose' => $purpose,
            'amount_minor' => 50000,
            'currency' => 'NGN',
            'provider' => $provider,
            'provider_reference' => 'ref-'.Str::lower(Str::random(10)),
            'idempotency_key' => 'expiry:'.$user->id.':'.Str::lower(Str::random(12)),
            'status' => $status,
            'expires_at' => $expiresAt,
            'paid_at' => $status === 'paid' ? now() : null,
        ]);

        Transaction::query()->create([
            'user_id' => $user->id,
            'type' => 'top_up_fund',
            'direction' => 'credit',
            'amount' => 500,
            'fee' => 0,
            'wallet' => null,
            'status' => 'pending',
            'reference' => 'payment-intent:'.$intent->id,
            'description' => 'Wallet top-up',
            'meta' => ['payment_intent_id' => $intent->id, 'currency' => 'NGN', 'currency_minor_units' => 2, 'amount_minor' => 50000],
        ]);

        return $intent;
    }
}
