<?php

namespace App\Application\Payments;

use App\Application\Actions\Payments\VerifyGatewayTopup;
use App\Models\Admin;
use App\Models\AuditLog;
use App\Models\PaymentIntent;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * "Revalidate" from the admin console: ask the gateway what really happened to
 * this checkout and apply the answer.
 *
 * It reuses {@see VerifyGatewayTopup}, so a successful revalidation runs the
 * exact completion path a webhook would (credit, subscription or add-on), and a
 * failed/cancelled answer closes the intent and its projection.
 *
 * Never throws for provider/network problems — the console shows the message
 * and the intent is left untouched.
 */
final class RevalidateGatewayPayment
{
    public function __construct(private readonly VerifyGatewayTopup $verify) {}

    /**
     * @return array{outcome:string,status:string,message:string,intent:PaymentIntent}
     */
    public function handle(Admin $admin, PaymentIntent $intent): array
    {
        if (! in_array($intent->provider, SettleGatewayPaymentManually::GATEWAY_PROVIDERS, true)) {
            throw ValidationException::withMessages([
                'payment' => 'Only gateway payments can be revalidated against a provider.',
            ]);
        }

        $before = $intent->status->value;

        try {
            $fresh = $this->verify->handle($intent)->fresh();
        } catch (\Throwable $exception) {
            report($exception);
            $this->audit($admin, $intent, 'gateway_payment.revalidate_failed', [
                'status' => $before,
            ], [
                'status' => $intent->fresh()->status->value,
                'error' => mb_substr($exception->getMessage(), 0, 500),
            ]);

            return [
                'outcome' => 'error',
                'status' => $intent->fresh()->status->value,
                'message' => 'The provider could not be reached or rejected the verification: '.mb_substr($exception->getMessage(), 0, 300),
                'intent' => $intent->fresh(),
            ];
        }

        $status = $fresh->status->value;
        $outcome = match ($status) {
            'paid' => 'paid',
            'failed', 'cancelled', 'expired' => $status,
            default => 'pending',
        };

        $this->audit($admin, $fresh, 'gateway_payment.revalidated', [
            'status' => $before,
        ], [
            'status' => $status,
            'provider_metadata' => $fresh->provider_metadata,
        ]);

        return [
            'outcome' => $outcome,
            'status' => $status,
            'message' => match ($outcome) {
                'paid' => 'The provider confirmed the payment; the account has been updated.',
                'pending' => 'The provider has not settled this payment yet. Nothing was changed.',
                'failed' => 'The provider reports the payment as failed.',
                'cancelled' => 'The provider reports the payment as cancelled.',
                'expired' => 'The provider reports the checkout as expired.',
                default => 'Revalidation completed.',
            },
            'intent' => $fresh,
        ];
    }

    /**
     * @param  array<string, mixed>  $before
     * @param  array<string, mixed>  $after
     */
    private function audit(Admin $admin, PaymentIntent $intent, string $action, array $before, array $after): void
    {
        AuditLog::query()->create([
            'audit_key' => (string) Str::uuid(),
            'actor_admin_id' => $admin->id,
            'action' => $action,
            'entity_type' => class_basename($intent),
            'entity_id' => (string) $intent->id,
            'before_values' => $before,
            'after_values' => $after,
            'created_at' => now(),
        ]);
    }
}
