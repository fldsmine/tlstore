<?php

namespace App\Enums;

/**
 * Canonical vocabulary for `transactions.status`.
 *
 * `transactions` is a *projection* of the ledger (and of pre-ledger payment
 * intents), so its status must be derivable, never guessed at the call site.
 * The previous code mixed three vocabularies (`posted`, `completed`,
 * `success`), which is why some paths showed "pending" forever.
 *
 * Rules (see docs/money-and-ledger-invariants.md):
 *  - a ledger entry that is `posted`  -> `completed`
 *  - a ledger entry that is `pending` / `reserved` -> `pending`
 *  - a ledger entry that is `reversed` -> `failed`
 *  - `approved` / `rejected` are reserved for manually reviewed flows
 *    (manual deposits, campaign orders, moderation decisions)
 *  - `posted` is a *ledger* status and is never written to a transaction.
 */
enum TransactionStatus: string
{
    case Pending = 'pending';
    case Completed = 'completed';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case Failed = 'failed';
    case Expired = 'expired';
    case Cancelled = 'cancelled';

    /**
     * Values older clients (and rows written before this enum existed) still
     * send to the API. They are accepted on input and normalised on use.
     *
     * @return array<string, self>
     */
    public static function legacyAliases(): array
    {
        return [
            'posted' => self::Completed,
            'success' => self::Completed,
            'successful' => self::Completed,
            // `reserved` is a *ledger* status: a hold on the balance, which is
            // `pending` from the user's point of view.
            'reserved' => self::Pending,
        ];
    }

    /** Normalise any historical value to its canonical form. */
    public static function normalize(string $status): string
    {
        $status = strtolower(trim($status));

        return (self::legacyAliases()[$status] ?? null)?->value ?? $status;
    }

    public static function fromLedger(LedgerEntryStatus $status): self
    {
        return match ($status) {
            LedgerEntryStatus::Posted => self::Completed,
            LedgerEntryStatus::Pending, LedgerEntryStatus::Reserved => self::Pending,
            LedgerEntryStatus::Reversed => self::Failed,
        };
    }

    /** Money that has actually landed in (or left) the wallet. */
    public function isSettled(): bool
    {
        return in_array($this, [self::Completed, self::Approved], true);
    }

    /** No further transition is possible from here. */
    public function isTerminal(): bool
    {
        return ! in_array($this, [self::Pending], true);
    }

    /**
     * Statuses the account-history endpoint treats as "money moved".
     *
     * @return array<int, string>
     */
    public static function settledValues(): array
    {
        return [self::Completed->value, self::Approved->value];
    }
}
