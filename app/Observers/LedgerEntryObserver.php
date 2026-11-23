<?php

namespace App\Observers;

use App\Application\Money\CurrencyMinorUnits;
use App\Models\LedgerEntry;
use App\Models\Transaction;
use App\Models\Wallet;
use Illuminate\Support\Facades\DB;

final class LedgerEntryObserver
{
    public function created(LedgerEntry $entry): void
    {
        $this->applyBalanceDelta($entry, 0, $this->balanceImpact($entry->direction->value, $entry->status->value, (int) $entry->amount_minor));
        $this->sync($entry);
    }

    public function updated(LedgerEntry $entry): void
    {
        $oldDirection = (string) $entry->getRawOriginal('direction');
        $oldStatus = (string) $entry->getRawOriginal('status');
        $oldAmount = (int) $entry->getRawOriginal('amount_minor');
        $newDirection = $entry->direction->value;
        $newStatus = $entry->status->value;
        $newAmount = (int) $entry->amount_minor;
        $this->applyBalanceDelta(
            $entry,
            $this->balanceImpact($oldDirection, $oldStatus, $oldAmount),
            $this->balanceImpact($newDirection, $newStatus, $newAmount),
        );
        $this->sync($entry);
    }

    private function balanceImpact(string $direction, string $status, int $amount): int
    {
        if ($direction === 'credit' && $status === 'posted') return $amount;
        if ($direction === 'debit' && in_array($status, ['posted', 'reserved'], true)) return -$amount;
        return 0;
    }

    private function applyBalanceDelta(LedgerEntry $entry, int $oldImpact, int $newImpact): void
    {
        $delta = $newImpact - $oldImpact;
        if ($delta === 0) return;

        DB::transaction(function () use ($entry, $delta): void {
            $wallet = Wallet::query()->whereKey($entry->wallet_id)->lockForUpdate()->first();
            if (! $wallet) return;
            $wallet->balance_minor = max(0, (int) $wallet->balance_minor + $delta);
            $wallet->saveQuietly();
        });
    }

    private function sync(LedgerEntry $entry): void
    {
        $entry->loadMissing(['wallet', 'source']);
        $wallet = $entry->wallet;
        if (! $wallet) {
            return;
        }

        $currency = strtoupper($wallet->currency);
        $type = match (true) {
            str_contains($entry->entry_type, 'review') => 'app_review',
            str_contains($entry->entry_type, 'ad_share') => 'ads_share',
            str_contains($entry->entry_type, 'referral') => 'referral_bonus',
            str_contains($entry->entry_type, 'social_follow') => 'social_follow_reward',
            str_contains($entry->entry_type, 'withdrawal') => 'withdrawal',
            str_contains($entry->entry_type, 'subscription') => 'subscription_plan',
            str_contains($entry->entry_type, 'addons') => 'package_addon',
            str_contains($entry->entry_type, 'rank') => 'rank_earning',
            str_contains($entry->entry_type, 'vtu') => 'vtu_purchase',
            str_contains($entry->entry_type, 'transfer') => 'fund_transfer',
            str_contains($entry->entry_type, 'topup') || str_contains($entry->entry_type, 'deposit') => 'top_up_fund',
            str_contains($entry->entry_type, 'campaign_order') => 'campaign_order',
            default => 'fund_transfer',
        };

        $transactionStatus = match (true) {
            str_contains($entry->entry_type, 'vtu') && $entry->status->value === 'reserved' => 'pending',
            str_contains($entry->entry_type, 'vtu') && $entry->status->value === 'posted' => 'completed',
            str_contains($entry->entry_type, 'vtu') && $entry->status->value === 'reversed' => 'failed',
            default => $entry->status->value,
        };

        $reference = match (true) {
            $entry->entry_type === 'review_reward' && $entry->source_type === 'review_submission'
                => 'review-submission:'.$entry->source_id,
            $entry->entry_type === 'ad_share_reward' && $entry->source_type === 'ad_share_event'
                => 'ad-share-event:'.$entry->source_id,
            $entry->source_type === 'payment_intent'
                => 'payment-intent:'.$entry->source_id,
            $entry->source_type === 'user_campaign_order' && ! str_contains($entry->entry_type, 'refund')
                => 'campaign-order:'.$entry->source_id,
            default => 'ledger:'.$entry->id,
        };
        $existing = Transaction::query()->where('reference', $reference)->first();

        $activityStatus = match ($entry->source_type) {
            'review_submission' => $entry->source?->status?->value ?? $entry->source?->status ?? $transactionStatus,
            'ad_share_event' => match ($entry->source?->status?->value ?? $entry->source?->status) {
                'validated' => 'approved',
                'rejected' => 'rejected',
                'pending_validation' => 'pending',
                default => $transactionStatus,
            },
            'user_campaign_order' => $entry->source?->status ?? $transactionStatus,
            'payment_intent' => ($entry->source?->provider === 'manual')
                ? match ($entry->source?->status?->value ?? $entry->source?->status) {
                    'paid' => 'approved',
                    'failed', 'cancelled', 'expired' => 'rejected',
                    default => 'pending',
                }
                : $transactionStatus,
            default => $transactionStatus,
        };

        $withdrawal = $entry->source_type === 'withdrawal_request' ? $entry->source : null;
        $withdrawalFeeMinor = $withdrawal ? (int) $withdrawal->fee_minor : 0;
        $withdrawalAmountMinor = $withdrawal ? (int) $withdrawal->amount_minor : (int) $entry->amount_minor;
        $withdrawalPayoutMinor = max(0, $withdrawalAmountMinor - $withdrawalFeeMinor);
        $transactionAmountMinor = $entry->source_type === 'withdrawal_request'
            ? $withdrawalAmountMinor
            : (int) $entry->amount_minor;

        Transaction::query()->updateOrCreate(
            ['reference' => $reference],
            [
                'user_id' => $wallet->user_id,
                'type' => $type,
                'direction' => $entry->direction->value,
                'amount' => CurrencyMinorUnits::fromMinor($transactionAmountMinor, $currency),
                'fee' => CurrencyMinorUnits::fromMinor($withdrawalFeeMinor, $currency),
                'wallet' => $wallet->wallet_type->value,
                'status' => $activityStatus,
                'description' => $entry->source_type === 'withdrawal_request'
                    ? 'Withdrawal request'
                    : ($existing?->description ?: $entry->description),
                'meta' => array_merge($existing?->meta ?? [], $entry->metadata ?? [], [
                    'ledger_entry_id' => $entry->id,
                    'currency' => $currency,
                    'currency_minor_units' => CurrencyMinorUnits::precision($currency),
                    'amount_minor' => $entry->amount_minor,
                    'entry_type' => $entry->entry_type,
                    'source_type' => $entry->source_type,
                    'source_id' => $entry->source_id,
                    ...($entry->source_type === 'withdrawal_request' ? [
                        'withdrawal_amount_minor' => $withdrawalAmountMinor,
                        'fee_minor' => $withdrawalFeeMinor,
                        'payout_minor' => $withdrawalPayoutMinor,
                    ] : []),
                ]),
            ],
        );
    }
}
