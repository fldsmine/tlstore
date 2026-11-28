<?php

namespace Tests\Feature;

use App\Models\LedgerEntry;
use App\Models\Transaction;
use App\Models\User;
use App\Models\UserCampaignOrder;
use App\Models\Wallet;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * The ledger is the source of truth; `transactions` is its projection. These
 * tests pin the mapping rules documented in docs/money-and-ledger-invariants.md
 * (ERR-10 / ERR-11 / ERR-33).
 */
class TransactionProjectionTest extends TestCase
{
    use RefreshDatabase;

    public function test_posted_ledger_entries_project_to_completed_not_posted(): void
    {
        $user = $this->user();
        Wallet::query()->create([
            'user_id' => $user->id, 'wallet_type' => 'review', 'currency' => 'NGN',
            'balance_minor' => 0, 'status' => 'active',
        ]);
        $wallet = Wallet::query()->where('user_id', $user->id)->where('wallet_type', 'review')->firstOrFail();

        LedgerEntry::query()->create([
            'wallet_id' => $wallet->id,
            'entry_type' => 'review_reward',
            'direction' => 'credit',
            'amount_minor' => 2100,
            'status' => 'posted',
            'idempotency_key' => 'projection-'.Str::random(10),
            'available_at' => now(),
            'posted_at' => now(),
        ]);

        $transaction = Transaction::query()->latest('id')->firstOrFail();
        $this->assertSame('completed', $transaction->status);
        $this->assertSame('app_review', $transaction->type->value);
        $this->assertSame(2100, (int) $wallet->fresh()->balance_minor);
    }

    public function test_reserved_entries_are_pending_until_posted(): void
    {
        $user = $this->user();
        // `balance_minor` is not fillable: balances only ever change through
        // the ledger, so the fixture seeds it explicitly.
        $wallet = Wallet::query()->create([
            'user_id' => $user->id, 'wallet_type' => 'topup_fund', 'currency' => 'NGN',
            'status' => 'active',
        ])->forceFill(['balance_minor' => 10000]);
        $wallet->save();

        $entry = LedgerEntry::query()->create([
            'wallet_id' => $wallet->id,
            'entry_type' => 'vtu_purchase',
            'direction' => 'debit',
            'amount_minor' => 5000,
            'status' => 'reserved',
            'idempotency_key' => 'projection-vtu-'.Str::random(10),
            'available_at' => now(),
        ]);

        $transaction = Transaction::query()->latest('id')->firstOrFail();
        $this->assertSame('pending', $transaction->status);
        $this->assertSame(5000, (int) $wallet->fresh()->balance_minor, 'a reservation holds funds');

        $entry->update(['status' => 'posted', 'posted_at' => now()]);

        $this->assertSame('completed', $transaction->fresh()->status);
    }

    public function test_campaign_refund_is_a_separate_completed_projection(): void
    {
        $user = $this->user();
        $wallet = Wallet::query()->create([
            'user_id' => $user->id, 'wallet_type' => 'topup_fund', 'currency' => 'NGN',
            'status' => 'active',
        ])->forceFill(['balance_minor' => 50000]);
        $wallet->save();

        $order = UserCampaignOrder::query()->create([
            'user_id' => $user->id,
            'campaign_name' => 'Refunded campaign',
            'platform' => 'tiktok',
            'action' => 'followers',
            'quantity' => 100,
            'target_url' => 'https://example.test/profile',
            'currency' => 'NGN',
            'unit_price_minor' => 100,
            'subtotal_minor' => 10000,
            'service_fee_percent' => 10,
            'service_fee_minor' => 1000,
            'total_minor' => 11000,
            'debited_amount_minor' => 11000,
            'debited_currency' => 'NGN',
            'status' => 'rejected',
            'idempotency_key' => 'order-'.Str::random(10),
        ]);

        // The original charge.
        LedgerEntry::query()->create([
            'wallet_id' => $wallet->id,
            'entry_type' => 'campaign_order_debit',
            'direction' => 'debit',
            'amount_minor' => 11000,
            'status' => 'posted',
            'source_type' => 'user_campaign_order',
            'source_id' => $order->id,
            'idempotency_key' => 'campaign-order:'.$order->id,
            'available_at' => now(),
            'posted_at' => now(),
        ]);

        // The refund credit created by `DecideCampaignOrder`.
        LedgerEntry::query()->create([
            'wallet_id' => $wallet->id,
            'entry_type' => 'campaign_order_refund',
            'direction' => 'credit',
            'amount_minor' => 11000,
            'status' => 'posted',
            'source_type' => 'user_campaign_order',
            'source_id' => $order->id,
            'idempotency_key' => 'campaign-order:'.$order->id.':refund',
            'available_at' => now(),
            'posted_at' => now(),
        ]);

        $charge = Transaction::query()->where('reference', 'campaign-order:'.$order->id)->firstOrFail();
        $refund = Transaction::query()->where('reference', 'campaign-order-refund:'.$order->id)->firstOrFail();

        $this->assertSame('rejected', $charge->status);
        $this->assertSame('debit', $charge->direction);

        $this->assertSame('completed', $refund->status, 'money returned to the user is settled');
        $this->assertSame('credit', $refund->direction);
        $this->assertSame('campaign_order', $refund->type->value);

        $this->assertSame(50000, (int) $wallet->fresh()->balance_minor, 'charge then refund nets to the original balance');
    }

    public function test_balance_never_goes_negative_when_a_debit_exceeds_the_wallet(): void
    {
        $user = $this->user();
        $wallet = Wallet::query()->create([
            'user_id' => $user->id, 'wallet_type' => 'topup_fund', 'currency' => 'NGN',
            'status' => 'active',
        ])->forceFill(['balance_minor' => 1000]);
        $wallet->save();

        LedgerEntry::query()->create([
            'wallet_id' => $wallet->id,
            'entry_type' => 'fund_transfer',
            'direction' => 'debit',
            'amount_minor' => 5000,
            'status' => 'posted',
            'idempotency_key' => 'overdraft-'.Str::random(10),
            'available_at' => now(),
            'posted_at' => now(),
        ]);

        $this->assertSame(0, (int) $wallet->fresh()->balance_minor);
    }

    private function user(): User
    {
        return User::query()->create([
            'full_name' => 'Projection User',
            'username' => 'projection-'.Str::lower(Str::random(8)),
            'email' => Str::lower(Str::random(8)).'@example.test',
            'preferred_currency' => 'NGN',
            'wallet_currency' => 'NGN',
            'role' => 'reviewer',
            'status' => 'active',
        ]);
    }
}
