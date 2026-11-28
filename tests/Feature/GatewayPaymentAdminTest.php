<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\AuditLog;
use App\Models\Notification;
use App\Models\PackageAddon;
use App\Models\PaymentIntent;
use App\Models\SubscriptionPlan;
use App\Models\Transaction;
use App\Models\User;
use App\Models\UserPackageAddon;
use App\Models\UserSubscription;
use App\Models\Wallet;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * The console's gateway-payments section: list, revalidate with the provider,
 * settle by hand (which must complete the purchase the payment was for) and
 * reject.
 */
class GatewayPaymentAdminTest extends TestCase
{
    use RefreshDatabase;

    private const SECRET = 'sk_test_admin_gateway';

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('trustlens.payment_gateways.paystack', [
            'secret_key' => self::SECRET,
            'base_url' => 'https://api.paystack.test',
        ]);
        config()->set('trustlens.implemented_payment_gateways', ['manual', 'paystack']);
    }

    public function test_finance_admin_sees_gateway_payments_only(): void
    {
        $intent = $this->pendingIntent($this->user(), amountMinor: 75000);
        $manual = PaymentIntent::query()->create([
            'user_id' => $intent->user_id,
            'purpose' => 'manual_deposit',
            'amount_minor' => 50000,
            'currency' => 'NGN',
            'provider' => 'manual',
            'idempotency_key' => 'manual-'.Str::random(8),
            'status' => 'pending',
        ]);

        $this->actingAs($this->admin('finance'), 'admin')
            ->get(route('admin.gateway-payments.index'))
            ->assertOk()
            ->assertSee($intent->provider_reference)
            ->assertDontSee($manual->idempotency_key);
    }

    public function test_roles_outside_finance_and_admin_are_forbidden(): void
    {
        $this->actingAs($this->admin('moderator'), 'admin')
            ->get(route('admin.gateway-payments.index'))
            ->assertForbidden();
    }

    public function test_revalidate_settles_a_payment_the_provider_confirms(): void
    {
        $intent = $this->pendingIntent($this->user(), amountMinor: 40000);

        Http::fake([
            'api.paystack.test/transaction/verify/*' => Http::response([
                'status' => true,
                'data' => [
                    'status' => 'success',
                    'amount' => 40000,
                    'currency' => 'NGN',
                    'reference' => $intent->provider_reference,
                ],
            ]),
        ]);

        $this->actingAs($this->admin('finance'), 'admin')
            ->post(route('admin.gateway-payments.revalidate', $intent->id))
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertSame('paid', $intent->fresh()->status->value);
        $this->assertSame(40000, (int) $this->topupWallet($intent->user)->balance_minor);
        $this->assertSame(
            'completed',
            Transaction::query()->where('reference', 'payment-intent:'.$intent->id)->value('status'),
        );
        $this->assertDatabaseHas('audit_logs', ['action' => 'gateway_payment.revalidated']);
    }

    public function test_revalidate_reports_pending_without_changing_the_payment(): void
    {
        $intent = $this->pendingIntent($this->user(), amountMinor: 40000);

        Http::fake([
            'api.paystack.test/transaction/verify/*' => Http::response([
                'status' => true,
                'data' => ['status' => 'ongoing', 'reference' => $intent->provider_reference],
            ]),
        ]);

        $this->actingAs($this->admin('finance'), 'admin')
            ->post(route('admin.gateway-payments.revalidate', $intent->id))
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertSame('pending', $intent->fresh()->status->value);
        $this->assertSame(
            'pending',
            Transaction::query()->where('reference', 'payment-intent:'.$intent->id)->value('status'),
        );
    }

    public function test_manual_approval_credits_a_topup_exactly_once(): void
    {
        $intent = $this->pendingIntent($this->user(), amountMinor: 25000);
        $admin = $this->admin('finance');

        $this->actingAs($admin, 'admin')->post(route('admin.gateway-payments.decision', $intent->id), [
            'decision' => 'approved',
            'note' => 'Seen on the Paystack dashboard, receipt 9911.',
            'provider_reference' => 'ps_receipt_9911',
        ])->assertRedirect()->assertSessionHas('success');

        $this->assertSame('paid', $intent->fresh()->status->value);
        $this->assertSame(25000, (int) $this->topupWallet($intent->user)->balance_minor);
        $this->assertSame(
            'completed',
            Transaction::query()->where('reference', 'payment-intent:'.$intent->id)->value('status'),
        );
        $this->assertSame($admin->id, $intent->fresh()->provider_metadata['manual_approval']['approved_by_admin_id'] ?? null);
        $this->assertDatabaseHas('audit_logs', ['action' => 'gateway_payment.manually_approved', 'actor_admin_id' => $admin->id]);
        $this->assertDatabaseHas('notifications', ['user_id' => $intent->user_id, 'type' => 'payment_verified']);

        // A second approval must not credit the wallet twice.
        $this->actingAs($admin, 'admin')->post(route('admin.gateway-payments.decision', $intent->id), [
            'decision' => 'approved',
        ])->assertRedirect()->assertSessionHasErrors('payment');

        $this->assertSame(25000, (int) $this->topupWallet($intent->user)->balance_minor);
    }

    public function test_manual_approval_activates_the_subscription_the_payment_was_for(): void
    {
        $this->seed(\Database\Seeders\SubscriptionPlanSeeder::class);
        $plan = SubscriptionPlan::query()->where('plan_key', 'market-lite')->firstOrFail();
        $user = $this->user();

        $intent = PaymentIntent::query()->create([
            'user_id' => $user->id,
            'purpose' => 'subscription',
            'source_type' => 'subscription_plan',
            'source_id' => $plan->id,
            'amount_minor' => 150000,
            'currency' => 'NGN',
            'provider' => 'korapay',
            'provider_reference' => 'kr-'.Str::random(8),
            'idempotency_key' => 'sub-'.Str::random(8),
            'status' => 'pending',
            'expires_at' => now()->addHours(2),
        ]);
        Transaction::query()->create([
            'user_id' => $user->id,
            'type' => 'subscription_plan',
            'direction' => 'debit',
            'amount' => 1500,
            'fee' => 0,
            'wallet' => null,
            'status' => 'pending',
            'reference' => 'payment-intent:'.$intent->id,
            'description' => 'Subscription package purchase',
            'meta' => ['currency' => 'NGN', 'amount_minor' => 150000],
        ]);

        $this->actingAs($this->admin('super_admin'), 'admin')
            ->post(route('admin.gateway-payments.decision', $intent->id), ['decision' => 'approved'])
            ->assertRedirect()
            ->assertSessionHas('success');

        $subscription = UserSubscription::query()->where('payment_intent_id', $intent->id)->firstOrFail();
        $this->assertSame('active', $subscription->status->value);
        $this->assertSame($plan->id, $subscription->subscription_plan_id);
        $this->assertSame('completed', Transaction::query()->where('reference', 'payment-intent:'.$intent->id)->value('status'));
    }

    public function test_manual_approval_creates_the_package_addon_entitlement(): void
    {
        $this->seed(\Database\Seeders\SubscriptionPlanSeeder::class);
        $plan = SubscriptionPlan::query()->where('plan_key', 'market-lite')->firstOrFail();
        $addon = PackageAddon::query()->create([
            'subscription_plan_id' => $plan->id,
            'addon_key' => 'extra-reviews',
            'name' => 'Extra review slots',
            'price_minor' => 50000,
            'discount' => 0,
            'currency' => 'NGN',
            'billing_interval' => 'monthly',
            'duration_count' => 1,
            'is_active' => true,
        ]);
        $user = $this->user();

        $intent = PaymentIntent::query()->create([
            'user_id' => $user->id,
            'purpose' => 'package_addon',
            'source_type' => 'package_addon',
            'source_id' => $addon->id,
            'amount_minor' => 50000,
            'currency' => 'NGN',
            'provider' => 'flutterwave',
            'provider_reference' => 'flw-'.Str::random(8),
            'idempotency_key' => 'addon-'.Str::random(8),
            'status' => 'pending',
        ]);

        $this->actingAs($this->admin('finance'), 'admin')
            ->post(route('admin.gateway-payments.decision', $intent->id), ['decision' => 'approved'])
            ->assertRedirect();

        $this->assertDatabaseHas('user_package_addons', [
            'payment_intent_id' => $intent->id,
            'package_addon_id' => $addon->id,
            'status' => 'active',
        ]);
        $this->assertSame(1, UserPackageAddon::query()->where('payment_intent_id', $intent->id)->count());
    }

    public function test_rejection_closes_the_payment_and_its_projection(): void
    {
        $intent = $this->pendingIntent($this->user(), amountMinor: 15000);

        $this->actingAs($this->admin('finance'), 'admin')
            ->post(route('admin.gateway-payments.decision', $intent->id), [
                'decision' => 'rejected',
                'note' => 'No matching transaction in the provider dashboard.',
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertSame('failed', $intent->fresh()->status->value);
        $this->assertSame(
            'failed',
            Transaction::query()->where('reference', 'payment-intent:'.$intent->id)->value('status'),
        );
        $this->assertDatabaseHas('notifications', ['user_id' => $intent->user_id, 'type' => 'payment_rejected']);
        $this->assertSame(0, Wallet::query()->where('user_id', $intent->user_id)->count());
    }

    public function test_only_super_admin_can_read_gateway_balances(): void
    {
        Http::fake([
            'api.paystack.test/balance' => Http::response([
                'status' => true,
                'data' => [['currency' => 'NGN', 'balance' => 1250500]],
            ]),
        ]);

        $this->actingAs($this->admin('admin'), 'admin')
            ->get(route('admin.gateway-balances.index'))
            ->assertForbidden();

        $this->actingAs($this->admin('super_admin'), 'admin')
            ->get(route('admin.gateway-balances.index'))
            ->assertOk()
            ->assertSee('12,505.00');
    }

    public function test_admin_api_exposes_the_same_section(): void
    {
        $intent = $this->pendingIntent($this->user(), amountMinor: 15000);
        $admin = $this->admin('finance');

        $this->actingAs($admin, 'sanctum')
            ->getJson('/api/v1/admin/gateway-payments')
            ->assertOk()
            ->assertJsonPath('data.0.id', $intent->id);

        $this->actingAs($this->admin('moderator'), 'sanctum')
            ->getJson('/api/v1/admin/gateway-payments')
            ->assertForbidden();
    }

    public function test_detail_page_renders_the_payment_and_its_history(): void
    {
        $intent = $this->pendingIntent($this->user(), amountMinor: 33000);

        $this->actingAs($this->admin('finance'), 'admin')
            ->get(route('admin.gateway-payments.show', $intent->id))
            ->assertOk()
            ->assertSee($intent->provider_reference)
            ->assertSee('Revalidate with Paystack')
            ->assertSee('Manual decision');
    }

    public function test_wallet_and_manual_payments_cannot_be_settled_here(): void
    {
        $user = $this->user();
        $walletPurchase = PaymentIntent::query()->create([
            'user_id' => $user->id,
            'purpose' => 'subscription',
            'amount_minor' => 150000,
            'currency' => 'NGN',
            'provider' => 'wallet',
            'idempotency_key' => 'wallet-'.Str::random(8),
            'status' => 'paid',
            'paid_at' => now(),
        ]);

        $this->actingAs($this->admin('finance'), 'admin')
            ->post(route('admin.gateway-payments.decision', $walletPurchase->id), ['decision' => 'approved'])
            ->assertNotFound();
    }

    public function test_balance_page_survives_a_provider_error(): void
    {
        config()->set('trustlens.implemented_payment_gateways', ['manual', 'paystack', 'korapay']);
        config()->set('trustlens.payment_gateways.korapay', [
            'secret_key' => 'korapay_test_key',
            'base_url' => 'https://api.korapay.test',
        ]);

        Http::fake([
            'api.paystack.test/balance' => Http::response([
                'status' => true,
                'data' => [['currency' => 'NGN', 'balance' => 500000]],
            ]),
            'api.korapay.test/balances' => Http::response(['message' => 'Unauthorized'], 401),
        ]);

        $this->actingAs($this->admin('super_admin'), 'admin')
            ->get(route('admin.gateway-balances.index'))
            ->assertOk()
            ->assertSee('5,000.00')
            ->assertSee('rejected the balance request');
    }

    private function admin(string $role): Admin
    {
        return Admin::query()->create([
            'full_name' => ucfirst($role).' Admin',
            'email' => $role.'-'.Str::lower(Str::random(6)).'@tlstore.test',
            'password' => 'StrongPassword!2345',
            'role' => $role,
            'status' => 'active',
        ]);
    }

    private function user(): User
    {
        return User::query()->create([
            'full_name' => 'Gateway Payer',
            'username' => 'gateway-'.Str::lower(Str::random(8)),
            'email' => Str::lower(Str::random(8)).'@example.test',
            'preferred_currency' => 'NGN',
            'wallet_currency' => 'NGN',
            'role' => 'reviewer',
            'status' => 'active',
        ]);
    }

    private function pendingIntent(User $user, int $amountMinor): PaymentIntent
    {
        $intent = PaymentIntent::query()->create([
            'user_id' => $user->id,
            'purpose' => 'topup',
            'amount_minor' => $amountMinor,
            'currency' => 'NGN',
            'provider' => 'paystack',
            'provider_reference' => 'ps-'.Str::random(10),
            'idempotency_key' => 'topup-'.Str::random(10),
            'status' => 'pending',
            'expires_at' => now()->addHours(2),
        ]);

        Transaction::query()->create([
            'user_id' => $user->id,
            'type' => 'top_up_fund',
            'direction' => 'credit',
            'amount' => $amountMinor / 100,
            'fee' => 0,
            'wallet' => null,
            'status' => 'pending',
            'reference' => 'payment-intent:'.$intent->id,
            'description' => 'Wallet top-up',
            'meta' => ['currency' => 'NGN', 'currency_minor_units' => 2, 'amount_minor' => $amountMinor],
        ]);

        return $intent;
    }

    private function topupWallet(User|int $user): Wallet
    {
        $userId = $user instanceof User ? $user->id : $user;

        return Wallet::query()->where('user_id', $userId)->where('wallet_type', 'topup_fund')->firstOrFail();
    }
}
