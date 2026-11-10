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
        $entry->loadMissing('wallet');
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
            str_contains($entry->entry_type, 'location') => 'location_addons',
            str_contains($entry->entry_type, 'rank') => 'rank_earning',
            str_contains($entry->entry_type, 'transfer') => 'fund_transfer',
            str_contains($entry->entry_type, 'topup') || str_contains($entry->entry_type, 'deposit') => 'top_up_fund',
            default => 'fund_transfer',
        };

        Transaction::query()->updateOrCreate(
            ['reference' => 'ledger:'.$entry->id],
            [
                'user_id' => $wallet->user_id,
                'type' => $type,
                'direction' => $entry->direction->value,
                'amount' => CurrencyMinorUnits::fromMinor($entry->amount_minor, $currency),
                'fee' => 0,
                'wallet' => $wallet->wallet_type->value,
                'status' => $entry->status->value,
                'description' => $entry->description,
                'meta' => [
                    'ledger_entry_id' => $entry->id,
                    'currency' => $currency,
                    'currency_minor_units' => CurrencyMinorUnits::precision($currency),
                    'amount_minor' => $entry->amount_minor,
                    'entry_type' => $entry->entry_type,
                    'source_type' => $entry->source_type,
                    'source_id' => $entry->source_id,
                ],
            ],
        );
    }
}
