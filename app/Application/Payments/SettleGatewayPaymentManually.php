<?php

namespace App\Application\Payments;

use App\Application\Actions\Payments\ProcessGatewayWebhook;
use App\Enums\PaymentIntentStatus;
use App\Models\Admin;
use App\Models\AuditLog;
use App\Models\Notification;
use App\Models\PaymentIntent;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Lets a finance admin settle a *gateway* payment by hand, when the provider
 * has taken the money but the webhook never arrived (or arrived broken).
 *
 * Approving is not "just flipping a status": it replays the same completion the
 * webhook would have run, so the intended action actually happens —
 *  - `topup`          → ledger credit into the top-up-fund wallet,
 *  - `subscription`   → the plan is activated,
 *  - `package_addon`  → the add-on entitlement is created.
 *
 * Every approval is audited with the administrator, the note and the provider
 * reference, and refuses to run twice.
 */
final class SettleGatewayPaymentManually
{
    /** Providers that only ever exist as a real gateway checkout. */
    public const GATEWAY_PROVIDERS = ['korapay', 'paystack', 'flutterwave', 'bachs'];

    public function __construct(
        private readonly ProcessGatewayWebhook $processor,
        private readonly PaymentIntentLifecycle $lifecycle,
    ) {}

    /**
     * @param  'approved'|'rejected'  $decision
     */
    public function handle(
        Admin $admin,
        PaymentIntent $intent,
        string $decision,
        ?string $note = null,
        ?string $providerReference = null,
    ): PaymentIntent {
        if (! in_array($intent->provider, self::GATEWAY_PROVIDERS, true)) {
            throw ValidationException::withMessages([
                'payment' => 'Only gateway payments can be settled here. Wallet purchases and manual bank deposits have their own flows.',
            ]);
        }

        if (! in_array($decision, ['approved', 'rejected'], true)) {
            throw ValidationException::withMessages(['decision' => 'Decision must be approved or rejected.']);
        }

        return $decision === 'approved'
            ? $this->approve($admin, $intent, $note, $providerReference)
            : $this->reject($admin, $intent, $note, $providerReference);
    }

    private function approve(Admin $admin, PaymentIntent $intent, ?string $note, ?string $providerReference): PaymentIntent
    {
        if ($intent->status === PaymentIntentStatus::Paid) {
            throw ValidationException::withMessages([
                'payment' => 'This payment has already been settled — no second credit was made.',
            ]);
        }

        if (in_array($intent->status, [PaymentIntentStatus::Refunded], true)) {
            throw ValidationException::withMessages(['payment' => 'A refunded payment cannot be approved.']);
        }

        $settled = DB::transaction(function () use ($admin, $intent, $note, $providerReference): PaymentIntent {
            $locked = PaymentIntent::query()->lockForUpdate()->findOrFail($intent->id);
            if ($locked->status === PaymentIntentStatus::Paid) {
                throw ValidationException::withMessages(['payment' => 'This payment has already been settled.']);
            }

            $snapshot = [
                'manual_approval' => [
                    'approved_by_admin_id' => $admin->id,
                    'approved_by' => $admin->email,
                    'approved_at' => now()->toIso8601String(),
                    'note' => $note,
                    'provider_reference' => $providerReference,
                    'previous_status' => $locked->status->value,
                ],
                'verified_source' => 'admin_manual_verification',
            ];

            // Record the reference the admin reconciled against before the
            // completion actions lock/fill the intent.
            $metadata = array_merge($locked->provider_metadata ?? [], $snapshot);
            if (filled($providerReference) && blank($locked->provider_reference)) {
                $metadata['admin_provider_reference'] = $providerReference;
            }
            $locked->forceFill(['provider_metadata' => $metadata])->save();

            $reconciledReference = filled($providerReference) ? $providerReference : $locked->provider_reference;
            $transactionId = 'admin-manual:'.$admin->id.':'.($reconciledReference ?: $locked->id);

            match ($locked->purpose) {
                'subscription' => $this->processor->completeVerifiedSubscription($locked, $transactionId, $snapshot),
                'package_addon' => $this->processor->completeVerifiedPackageAddon($locked, $transactionId, $snapshot),
                default => $this->processor->creditVerifiedTopup($locked, $transactionId, $snapshot),
            };

            $fresh = $locked->fresh();
            $this->notify($fresh, 'approved', $note);
            $this->audit($admin, $fresh, 'gateway_payment.manually_approved', [
                'status' => $locked->status->value,
                'provider_metadata' => $locked->provider_metadata,
            ], [
                'status' => $fresh->status->value,
                'provider_metadata' => $fresh->provider_metadata,
                'note' => $note,
                'provider_reference' => $providerReference,
            ]);

            return $fresh;
        });

        return $settled;
    }

    private function reject(Admin $admin, PaymentIntent $intent, ?string $note, ?string $providerReference): PaymentIntent
    {
        $previous = $intent->status->value;
        $moved = $this->lifecycle->markTerminal($intent, PaymentIntentStatus::Failed, [
            'rejected_by_admin_id' => $admin->id,
            'rejection_note' => $note,
            'provider_reference' => $providerReference,
            'rejected_at' => now()->toIso8601String(),
        ]);

        if (! $moved) {
            throw ValidationException::withMessages([
                'payment' => 'Only a pending payment can be rejected; this one is already '.$intent->fresh()->status->value.'.',
            ]);
        }

        $fresh = $intent->fresh();
        $this->notify($fresh, 'rejected', $note);
        $this->audit($admin, $fresh, 'gateway_payment.manually_rejected', [
            'status' => $previous,
        ], [
            'status' => $fresh->status->value,
            'note' => $note,
            'provider_reference' => $providerReference,
        ]);

        return $fresh;
    }

    private function notify(PaymentIntent $intent, string $outcome, ?string $note): void
    {
        $amount = number_format($intent->amount_minor / 100, 2);
        $reference = $intent->provider_reference ?: (string) $intent->id;

        Notification::query()->firstOrCreate(
            ['notification_key' => 'gateway-payment-manual-'.$intent->id],
            [
                'user_id' => $intent->user_id,
                'type' => $outcome === 'approved' ? 'payment_verified' : 'payment_rejected',
                'title' => $outcome === 'approved' ? 'Payment verified' : 'Payment not verified',
                'body' => $outcome === 'approved'
                    ? 'We verified your payment of '.$amount.' '.$intent->currency.' ('.$reference.'). Your account has been updated.'
                    : 'We could not verify your payment of '.$amount.' '.$intent->currency.' ('.$reference.').'.($note ? ' '.$note : ' Contact support with your receipt.'),
                'data' => [
                    'payment_intent_id' => $intent->id,
                    'provider' => $intent->provider,
                    'status' => $intent->status->value,
                    'amount_minor' => $intent->amount_minor,
                    'currency' => $intent->currency,
                    'reference' => $intent->provider_reference,
                ],
            ],
        );
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
