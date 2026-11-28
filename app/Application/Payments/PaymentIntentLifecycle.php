<?php

namespace App\Application\Payments;

use App\Enums\PaymentIntentStatus;
use App\Enums\TransactionStatus;
use App\Models\PaymentIntent;
use App\Models\Transaction;
use Illuminate\Support\Facades\DB;

/**
 * The single writer for terminal {@see PaymentIntent} transitions.
 *
 * Before this class, four call sites (`ProcessGatewayWebhook`,
 * `VerifyGatewayTopup`, `TopupController::cancel`, and nothing at all for
 * idleness) each moved payment intents into `failed` / `expired` while leaving
 * the `payment-intent:{id}` transaction projection at `pending` for ever.
 *
 * Every terminal transition now goes through {@see markTerminal()}, which keeps
 * the intent and its projection in lock-step and is idempotent: a duplicate
 * webhook, a late "check payment" poll and the expiry job can all race without
 * double-writing.
 */
final class PaymentIntentLifecycle
{
    public const TRANSACTION_REFERENCE_PREFIX = 'payment-intent:';

    /** Provider statuses that mean "the payment will never settle". */
    private const FAILED_STATUSES = ['failed', 'failure', 'declined', 'error', 'reversed'];

    private const CANCELLED_STATUSES = ['cancelled', 'canceled', 'abandoned', 'voided'];

    private const EXPIRED_STATUSES = ['expired', 'timed_out', 'timedout', 'timeout'];

    public static function transactionReference(PaymentIntent|int $intent): string
    {
        return self::TRANSACTION_REFERENCE_PREFIX.($intent instanceof PaymentIntent ? $intent->id : $intent);
    }

    /**
     * Translate a raw provider status into the terminal intent status it
     * implies, or null when the provider is still waiting for the payer.
     */
    public static function terminalStatusFor(?string $providerStatus): ?PaymentIntentStatus
    {
        $status = strtolower(trim((string) $providerStatus));

        return match (true) {
            in_array($status, self::FAILED_STATUSES, true) => PaymentIntentStatus::Failed,
            in_array($status, self::CANCELLED_STATUSES, true) => PaymentIntentStatus::Cancelled,
            in_array($status, self::EXPIRED_STATUSES, true) => PaymentIntentStatus::Expired,
            default => null,
        };
    }

    /**
     * Move an intent and its transaction projection to a terminal status.
     *
     * @param  array<string, mixed>  $meta  merged into `provider_metadata`
     * @return bool true when this call performed the transition
     */
    public function markTerminal(PaymentIntent $intent, PaymentIntentStatus $status, array $meta = []): bool
    {
        if ($status === PaymentIntentStatus::Paid) {
            throw new \InvalidArgumentException('Use the completion actions to settle a paid intent.');
        }

        return DB::transaction(function () use ($intent, $status, $meta): bool {
            $locked = PaymentIntent::query()->lockForUpdate()->find($intent->id);
            if (! $locked) {
                return false;
            }

            if (! in_array($locked->status, [PaymentIntentStatus::Pending, PaymentIntentStatus::Processing], true)) {
                // Already settled, already terminal, or refunded - never overwrite.
                return false;
            }

            $locked->forceFill([
                'status' => $status->value,
                'provider_metadata' => array_merge($locked->provider_metadata ?? [], $meta, [
                    'terminal_status' => $status->value,
                    'terminal_at' => now()->toIso8601String(),
                ]),
            ])->save();

            // Only a still-pending projection is rewritten; a paid projection
            // must never be downgraded by a late or duplicated event.
            Transaction::query()
                ->where('reference', self::transactionReference($locked))
                ->where('status', TransactionStatus::Pending->value)
                ->update(['status' => $status->value, 'updated_at' => now()]);

            $intent->refresh();

            return true;
        });
    }

    /**
     * Expire pending intents whose checkout window has passed.
     *
     * Manual (bank transfer) deposits are excluded: they have no checkout
     * window and are settled by an admin, not by the clock.
     *
     * @return int number of intents expired
     */
    public function expireStale(?\DateTimeInterface $now = null): int
    {
        $now ??= now();
        $expired = 0;

        PaymentIntent::query()
            ->whereIn('status', [PaymentIntentStatus::Pending->value, PaymentIntentStatus::Processing->value])
            ->whereNotNull('expires_at')
            ->where('expires_at', '<=', $now)
            ->where('provider', '!=', 'manual')
            ->orderBy('id')
            ->chunkById(200, function ($intents) use (&$expired): void {
                foreach ($intents as $intent) {
                    if ($this->markTerminal($intent, PaymentIntentStatus::Expired, ['expired_by' => 'payments:expire-intents'])) {
                        $expired++;
                    }
                }
            });

        return $expired;
    }
}
