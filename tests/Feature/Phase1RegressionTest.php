<?php

namespace Tests\Feature;

use App\Enums\TransactionType;
use App\Models\AdShareEvent;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Regression cover for the defects fixed in the Phase 1 stabilisation pass.
 *
 * Each test maps to a specific finding from the remediation audit so a future
 * refactor cannot silently reintroduce a boot-time or ledger-breaking bug.
 */
class Phase1RegressionTest extends TestCase
{
    use RefreshDatabase;

    public function test_general_settings_reads_empty_schema_without_throwing(): void
    {
        // No system settings seeded: the helper must return an empty array,
        // cache it, and never raise on the second (cached) read.
        $this->assertSame([], gs());
        $this->assertSame([], gs());
        $this->assertNull(gs('missing-key'));
        $this->assertSame('fallback', gs('missing-key', 'fallback'));
    }

    public function test_users_get_a_wallet_currency_when_only_the_preferred_currency_is_set(): void
    {
        $user = User::query()->create([
            'full_name' => 'Wallet Currency User',
            'username' => 'wallet-currency-user',
            'email' => 'wallet-currency@example.test',
            'preferred_currency' => 'NGN',
            'role' => 'reviewer',
            'status' => 'active',
        ]);

        $this->assertSame('NGN', $user->fresh()->wallet_currency);

        // The factory supplies the same default, so `User::factory()` (used by
        // TransactionFactory) no longer trips the NOT NULL constraint.
        $this->assertSame('NGN', User::factory()->make()->wallet_currency);
    }

    public function test_transaction_signed_amount_formats_without_a_money_helper(): void
    {
        $credit = new Transaction([
            'type' => TransactionType::APP_REVIEW,
            'direction' => 'credit',
            'amount' => 21,
            'meta' => ['currency' => 'NGN'],
        ]);
        $debit = new Transaction([
            'type' => TransactionType::WITHDRAWAL,
            'direction' => 'debit',
            'amount' => 2.5,
            'meta' => ['currency' => 'USD'],
        ]);

        $this->assertSame('+21.00', $credit->signed_amount);
        $this->assertSame('-2.50', $debit->signed_amount);
    }

    public function test_transaction_type_labels_and_gateway_classification(): void
    {
        $this->assertSame('Campaign order', TransactionType::CAMPAIGN_ORDER->getName());
        $this->assertSame('Rank reward', TransactionType::RANK_EARNING->getName());
        $this->assertSame('Referral reward', TransactionType::REFERRAL_BONUS->getName());

        // VTU purchases are debited from the wallet balance; no gateway charge
        // is ever verified for them.
        $this->assertFalse(TransactionType::VTU_PURCHASE->isGatewayCharge());
        $this->assertTrue(TransactionType::TOP_UP_FUND->isGatewayCharge());
    }

    public function test_account_history_payment_category_includes_campaign_orders(): void
    {
        $user = User::query()->create([
            'full_name' => 'Campaign Order User',
            'username' => 'campaign-order-user',
            'email' => 'campaign-order@example.test',
            'preferred_currency' => 'NGN',
            'role' => 'reviewer',
            'status' => 'active',
        ]);

        Transaction::query()->create([
            'user_id' => $user->id,
            'type' => TransactionType::CAMPAIGN_ORDER,
            'direction' => 'debit',
            'amount' => 250,
            'fee' => 0,
            'wallet' => 'topup_fund',
            'status' => 'completed',
            'reference' => (string) Str::uuid(),
            'description' => 'Campaign order',
            'meta' => ['currency' => 'NGN', 'currency_minor_units' => 2],
        ]);

        $token = $user->createToken('campaign-order-test')->plainTextToken;

        $this->withToken($token)
            ->getJson('/api/v1/me/account-history?category=payment')
            ->assertOk()
            ->assertJsonPath('data.total', 1)
            ->assertJsonPath('data.data.0.type', 'campaign_order');
    }

    public function test_ledger_entries_relations_use_the_morph_alias(): void
    {
        $event = new AdShareEvent();
        $relation = $event->ledgerEntries();

        $this->assertSame('ad_share_event', $event->getMorphClass());
        $this->assertInstanceOf(\Illuminate\Database\Eloquent\Relations\MorphMany::class, $relation);

        // The relation has to constrain on the morph alias, not the FQCN.
        $this->assertStringContainsString('"source_type" = ?', $relation->toSql());
        $this->assertSame(['ad_share_event'], $relation->getBindings());
    }

    public function test_vas_purchase_action_imports_the_ledger_entry_status_enum(): void
    {
        // A missing import here used to fatal the whole VAS purchase flow, so
        // assert the class reference used in the action resolves.
        $this->assertTrue(enum_exists(\App\Enums\LedgerEntryStatus::class));
        $source = file_get_contents(app_path('Application/Actions/Vas/PurchaseVasOrder.php'));

        $this->assertStringContainsString('use App\\Enums\\LedgerEntryStatus;', $source);
    }
}
