<?php

namespace Tests\Feature;

use App\Models\LedgerEntry;
use App\Models\PaymentIntent;
use App\Models\Transaction;
use App\Models\User;
use App\Models\Wallet;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * `ledger:verify` is the machine-checkable form of the Phase 2 exit criteria:
 * one lifecycle per movement and a wallet balance that reconciles with the
 * ledger.
 */
class LedgerIntegrityCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_consistent_ledger_passes(): void
    {
        $user = $this->user();
        $wallet = $this->wallet($user);

        LedgerEntry::query()->create([
            'wallet_id' => $wallet->id,
            'entry_type' => 'review_reward',
            'direction' => 'credit',
            'amount_minor' => 2500,
            'status' => 'posted',
            'idempotency_key' => 'verify-'.Str::random(10),
            'available_at' => now(),
            'posted_at' => now(),
        ]);

        $this->assertSame(2500, (int) $wallet->fresh()->balance_minor);

        $this->artisan('ledger:verify')
            ->expectsOutputToContain('Ledger is consistent.')
            ->assertExitCode(0);
    }

    public function test_a_balance_out_of_sync_with_the_ledger_fails_the_check(): void
    {
        $user = $this->user();
        $wallet = $this->wallet($user);
        $wallet->forceFill(['balance_minor' => 999])->save();

        $this->artisan('ledger:verify')->assertExitCode(1);
    }

    public function test_a_terminal_intent_still_projected_as_pending_fails_the_check(): void
    {
        $user = $this->user();

        $intent = PaymentIntent::query()->create([
            'user_id' => $user->id,
            'purpose' => 'topup',
            'amount_minor' => 50000,
            'currency' => 'NGN',
            'provider' => 'paystack',
            'provider_reference' => 'ref-'.Str::random(8),
            'idempotency_key' => 'verify-'.Str::random(10),
            'status' => 'failed',
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
            'meta' => ['currency' => 'NGN', 'amount_minor' => 50000],
        ]);

        $this->artisan('ledger:verify')->assertExitCode(1);
    }

    public function test_non_canonical_transaction_statuses_fail_the_check(): void
    {
        $user = $this->user();

        Transaction::query()->create([
            'user_id' => $user->id,
            'type' => 'top_up_fund',
            'direction' => 'credit',
            'amount' => 500,
            'fee' => 0,
            'wallet' => null,
            'status' => 'posted', // legacy spelling
            'reference' => 'legacy-'.Str::random(8),
            'description' => 'Legacy row',
            'meta' => ['currency' => 'NGN'],
        ]);

        $this->artisan('ledger:verify')->assertExitCode(1);
    }

    private function user(): User
    {
        return User::query()->create([
            'full_name' => 'Ledger User',
            'username' => 'ledger-'.Str::lower(Str::random(8)),
            'email' => Str::lower(Str::random(8)).'@example.test',
            'preferred_currency' => 'NGN',
            'wallet_currency' => 'NGN',
            'role' => 'reviewer',
            'status' => 'active',
        ]);
    }

    private function wallet(User $user): Wallet
    {
        return Wallet::query()->create([
            'user_id' => $user->id,
            'wallet_type' => 'review',
            'currency' => 'NGN',
            'status' => 'active',
        ]);
    }
}
