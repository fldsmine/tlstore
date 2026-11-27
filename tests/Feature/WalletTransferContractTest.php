<?php

namespace Tests\Feature;

use App\Application\Actions\Wallets\TransferTopupFunds;
use App\Enums\WalletType;
use App\Models\LedgerEntry;
use App\Models\User;
use App\Models\Wallet;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/**
 * ERR-25: `WalletController::transfer()` advertised a `review` target that
 * `TransferTopupFunds` rejects, so the API promised a transfer it could never
 * complete (and the error copy talked about a wallet the action did not take).
 *
 * The target list now comes from `WalletType::transferTargets()`.
 */
class WalletTransferContractTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_advertised_targets_exclude_the_legacy_review_wallet(): void
    {
        $this->assertSame([
            'review_group_b',
            'review_group_c',
            'share',
            'referral',
            'bonus',
        ], array_map(static fn (WalletType $type): string => $type->value, WalletType::transferTargets()));
    }

    public function test_legacy_review_wallet_is_rejected_at_the_api_boundary(): void
    {
        $user = $this->user();
        $this->fundedTopupWallet($user, 50_000);

        $this->withToken($user->createToken('transfer')->plainTextToken)
            ->postJson('/api/v1/wallets/topup-transfer', [
                'amount_minor' => 10_000,
                'target_wallet' => 'review',
                'idempotency_key' => 'legacy-review-target',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('target_wallet');

        $this->assertSame(0, LedgerEntry::query()->count());
        $this->assertSame(0, Wallet::query()->where('wallet_type', 'review')->count());
    }

    public function test_topup_fund_cannot_be_a_transfer_target(): void
    {
        $user = $this->user();
        $this->fundedTopupWallet($user, 50_000);

        $this->withToken($user->createToken('transfer')->plainTextToken)
            ->postJson('/api/v1/wallets/topup-transfer', [
                'amount_minor' => 10_000,
                'target_wallet' => 'topup_fund',
                'idempotency_key' => 'same-wallet-target',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('target_wallet');
    }

    public function test_every_advertised_target_can_receive_funds(): void
    {
        $user = $this->user();
        $source = $this->fundedTopupWallet($user, 200_000);
        $token = $user->createToken('transfer-every-target')->plainTextToken;
        $expectedSourceBalance = 200_000;

        foreach (WalletType::transferTargets() as $target) {
            $this->withToken($token)
                ->postJson('/api/v1/wallets/topup-transfer', [
                    'amount_minor' => 20_000,
                    'target_wallet' => $target->value,
                    'idempotency_key' => 'target-'.$target->value,
                ])
                ->assertOk();

            $expectedSourceBalance -= 20_000;

            $credited = Wallet::query()
                ->where('user_id', $user->id)
                ->where('wallet_type', $target->value)
                ->firstOrFail();

            $this->assertSame(20_000, (int) $credited->balance_minor, $target->value.' was not credited');
            $this->assertSame($expectedSourceBalance, (int) $source->fresh()->balance_minor, $target->value.' did not debit the top-up wallet');
            $credits = LedgerEntry::query()
                ->where('wallet_id', $credited->id)
                ->where('entry_type', 'wallet_transfer')
                ->where('direction', 'credit')
                ->get();
            $this->assertCount(1, $credits, $target->value.' did not write exactly one credit');
            $this->assertSame(20_000, (int) $credits->first()->amount_minor);
        }
    }

    public function test_a_replayed_request_never_double_debits(): void
    {
        $user = $this->user();
        $source = $this->fundedTopupWallet($user, 50_000);
        $token = $user->createToken('transfer-replay')->plainTextToken;

        $payload = [
            'amount_minor' => 15_000,
            'target_wallet' => 'bonus',
            'idempotency_key' => 'replayed-transfer',
        ];

        $this->withToken($token)->postJson('/api/v1/wallets/topup-transfer', $payload)->assertOk();
        $this->withToken($token)->postJson('/api/v1/wallets/topup-transfer', $payload)->assertOk();

        $this->assertSame(35_000, (int) $source->fresh()->balance_minor);
        $this->assertSame(2, LedgerEntry::query()->count());
    }

    public function test_the_action_reports_the_supported_targets(): void
    {
        $user = $this->user();
        $this->fundedTopupWallet($user, 50_000);

        try {
            app(TransferTopupFunds::class)->handle($user, 1_000, 'review', 'action-review-target');
            $this->fail('The legacy review wallet should not be a transfer target.');
        } catch (ValidationException $exception) {
            $message = $exception->errors()['target_wallet'][0] ?? '';
            $this->assertStringContainsString('Group B/Group C review', $message);
            $this->assertStringContainsString('bonus', $message);
        }
    }

    private function user(): User
    {
        return User::query()->create([
            'full_name' => 'Wallet Transfer User',
            'username' => 'wallet-transfer-'.strtolower(bin2hex(random_bytes(4))),
            'email' => 'wallet-transfer-'.bin2hex(random_bytes(4)).'@example.test',
            'preferred_currency' => 'NGN',
            'wallet_currency' => 'NGN',
            'role' => 'reviewer',
            'status' => 'active',
        ]);
    }

    private function fundedTopupWallet(User $user, int $balanceMinor): Wallet
    {
        $wallet = Wallet::query()->create([
            'user_id' => $user->id,
            'wallet_type' => 'topup_fund',
            'currency' => 'NGN',
            'status' => 'active',
        ]);
        $wallet->forceFill(['balance_minor' => $balanceMinor])->save();

        return $wallet->fresh();
    }
}
