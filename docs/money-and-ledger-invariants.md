# Money & Ledger Invariants

**Last Updated:** 2026-11-27 (Phase 2)
**Audit items:** `ERR-10`, `ERR-11`, `ERR-18`, `ERR-23`, `ERR-24`, `ERR-31`, `ERR-33`, `ERR-36`

This document is the contract every money-moving code path has to satisfy. If code
disagrees with this file, the code is wrong.

---

## 1. One source of truth

```
ledger_entries  ──(LedgerEntryObserver)──►  transactions      (user-facing history)
      │
      └──────────► wallets.balance_minor                      (spendable balance)
```

* **`ledger_entries` is the only source of truth.** Every movement of money is a row:
  `direction` (`credit` / `debit`), `amount_minor` (integer minor units), `status`,
  `wallet_id`, `source_type` + `source_id` (the activity that caused it) and a unique
  `idempotency_key`.
* **`wallets.balance_minor` is derived.** It is only ever changed by
  `App\Observers\LedgerEntryObserver` applying the *delta* between an entry's old and new
  impact:
  | ledger direction | ledger status | impact on the balance |
  |---|---|---|
  | `credit` | `posted` | `+amount_minor` |
  | `debit` | `posted` | `−amount_minor` |
  | `debit` | `reserved` | `−amount_minor` (a hold) |
  | anything else (`pending`, `reversed`, `credit` + `reserved`) | | `0` |
  The balance is clamped at zero — a debit can never make a wallet negative.
  Nothing else may write `balance_minor`; `Wallet::$fillable` deliberately excludes it.
* **`transactions` is a projection.** It exists so the app can show a friendly history
  without joining the ledger. It is written by the observer (for ledger-backed rows) or
  by the pre-ledger flow that owns the reference (payment intents, pending review/share
  submissions, campaign orders) — never by anything else.

## 2. Reference keys

A projection row is addressed by `transactions.reference`, which is deterministic:

| source | reference |
|---|---|
| ledger without a known source | `ledger:{entry_id}` |
| review reward | `review-submission:{submission_id}` |
| ad-share reward | `ad-share-event:{event_id}` |
| payment intent (top-up / subscription / add-on / manual deposit) | `payment-intent:{intent_id}` |
| campaign order charge | `campaign-order:{order_id}` |
| campaign order refund | `campaign-order-refund:{order_id}` |

`App\Application\Payments\PaymentIntentLifecycle::transactionReference()` is the only
place that builds the payment-intent prefix.

## 3. Transaction status vocabulary

`App\Enums\TransactionStatus` is the complete set: **`pending`, `completed`, `approved`,
`rejected`, `failed`, `expired`, `cancelled`**.

| situation | transaction status |
|---|---|
| ledger entry `posted` | `completed` |
| ledger entry `pending` or `reserved` | `pending` |
| ledger entry `reversed` | `failed` |
| review / ad-share submission awaiting a decision | `pending` |
| review / ad-share submission validated or approved | `approved` |
| review / ad-share submission rejected | `rejected` |
| manual deposit approved | `approved` |
| manual deposit rejected | `rejected` |
| campaign order awaiting review | `pending` |
| campaign order approved / active | `approved` |
| campaign order rejected | `rejected` |
| campaign order refund credit | `completed` |
| gateway payment failed | `failed` |
| gateway payment cancelled / abandoned | `cancelled` |
| checkout expired (timeout or user cancel) | `expired` |

Legacy spellings (`posted`, `success`, `successful`, `reserved`) are **never written**
anymore. They are still *accepted* on input (the account-history `status` filter maps them
through `TransactionStatus::normalize()`) and the migration
`2026_11_27_090002_normalize_transaction_statuses` rewrote existing rows.

Ledger statuses keep their own vocabulary (`pending`, `posted`, `reserved`, `reversed`);
`App\Enums\TransactionStatus::fromLedger()` is the only translation.

## 4. Payment intent lifecycle

```
                 ┌──────────── paid ──────────────┐
pending ─────────┤                                │
   │  ▲          └─────────► refunded             │
   │  │                                          │
   │  └── processing ──► paid / failed  ◄─────────┘
   │
   ├── failed     (provider reports failed/declined/reversed)
   ├── cancelled  (provider reports cancelled/abandoned/voided)
   └── expired    (checkout window passed, or the user cancelled)
```

* **One writer:** `App\Application\Payments\PaymentIntentLifecycle::markTerminal()` moves an
  intent **and** its `payment-intent:{id}` projection together, and is idempotent — a
  duplicated webhook, a late "check payment" poll and the expiry job can race safely.
  It never overwrites a projection that already reached `completed`.
* Sources of terminal transitions:
  | trigger | entry point |
  |---|---|
  | provider webhook reporting failure/cancellation | `ProcessGatewayWebhook` |
  | user polling "check payment" | `VerifyGatewayTopup` |
  | user cancelling the checkout | `POST /api/v1/topups/{intent}/cancel` → `TopupController::cancel` |
  | checkout window elapsed | `php artisan payments:expire-intents` (scheduled every 5 min) |
  | gateway checkout could not be initialised | `InitializeGatewayTopup` |
* Manual (bank-transfer) deposits are excluded from the expiry job: they have no checkout
  window and are settled by an admin (`DecideManualDeposit`).
* A webhook whose provider status is neither successful nor terminal (e.g. `ongoing`) is
  recorded as `ignored`; the checkout stays open.

## 5. Currency rules

* **Rewards** (`CreditUserWallet`) are converted into the user's `wallet_currency` at the
  time of credit. The entry's `metadata` carries `source_amount_minor`, `source_currency`,
  `credited_amount_minor`, `credited_currency` and `exchange_rate`, and the projection
  inherits them.
* **Gateway top-ups are never converted.** The user paid a currency; the wallet that is
  credited holds exactly that currency and exactly that amount. A USD payment by a
  NGN-wallet user creates a `topup_fund` wallet in USD — the ledger stays truthful about
  what was received.
* **Wallet-funded purchases** (subscription, add-on) debit the chosen top-up wallet in
  *its* currency and record the listed price and the exchange rate in `metadata`.
* **Withdrawals** debit `amount_minor` and expose `fee_minor`/`payout_minor` in the
  projection, so the shown debit always equals the request amount.

## 6. Refunds

A rejected campaign order produces **two** projection rows:

* `campaign-order:{id}` — the original debit, moved to `rejected` by `DecideCampaignOrder`;
* `campaign-order-refund:{id}` — the credit that returns the money, `completed`, with
  `meta.refund = true`.

## 7. Operational checks

| command | purpose |
|---|---|
| `php artisan payments:expire-intents` | close stale checkouts and their projections (scheduled every 5 min) |
| `php artisan ledger:verify` | prove `wallets.balance_minor` equals the ledger, that no non-canonical status exists, and that no terminal intent is still `pending` (scheduled nightly; exits non-zero on drift) |

Both are read-only except the expiry, which only touches rows that are already stale.

## 8. Adding a new money movement — checklist

1. Create the **ledger entry** with a unique `idempotency_key`, an explicit `status`, and
   `source_type` + `source_id`.
2. Give it a deterministic **reference** (extend the table in §2) if the projection should
   not be `ledger:{id}`.
3. Do **not** write `wallets.balance_minor` and do **not** hand-write the projection status
   for a ledger-backed row — the observer derives both.
4. If the flow has a pre-ledger lifecycle (a pending intent, a submission awaiting
   review), write the initial projection with `status = pending` and let the terminal
   transition go through `PaymentIntentLifecycle` (payments) or the deciding action.
5. Add a test that asserts the ledger entry, the projection status and the wallet balance
   together, then run `php artisan ledger:verify`.
