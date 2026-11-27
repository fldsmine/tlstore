# Bachs virtual accounts — feasibility, requirements and integration plan

**Question:** can TLStore create a virtual account for each user, so a user can fund their
wallet by bank transfer to their own account number?

**Verdict:** yes, but not per user in the way the question implies — Bachs issues **one virtual
account per Bachs account, per currency**. A number *per TLStore user* requires TLStore to become
a Bachs **platform** (Connect) and to hold each user's KYC (name + BVN). What is implementable
now, with the codebase as it stands, is a **single permanent NGN account number for TLStore**
whose deposits are matched to users by a deposit code in the narration, by known sender account
number, or by an admin review queue.

Sources: [create a virtual account](https://docs.bachs.io/api-reference/virtual-accounts/create-a-virtual-account),
[virtual accounts guide](https://docs.bachs.io/guides/virtual-accounts/overview),
[Connect overview](https://docs.bachs.io/connect/overview),
[become a platform](https://docs.bachs.io/connect/become-a-platform),
[how payments work](https://docs.bachs.io/for-you/payins-payouts).

---

## 1. What the endpoint actually does

```
POST https://api.bachs.io/v1/virtual-accounts
Authorization: Bearer sk_live_…            # scope: virtual_accounts:write
X-Account-Id: acct_…                        # optional: issue the number for a connected account
Content-Type: application/json

{ "currency": "NGN", "bank_code": "090645" }   # bank_code optional: 090645 Nombank MFB | 110072 Bank78 MFB
```

| Item | Detail |
|---|---|
| Currency | `NGN` only today; anything else → `VIRTUAL_ACCOUNT_CURRENCY_NOT_SUPPORTED` |
| Bank | optional; omit (or `""`) and Bachs picks, with failover. A bank you name is never swapped — if it is down you get `VIRTUAL_ACCOUNT_REFUSED` / `VIRTUAL_ACCOUNT_BANK_UNAVAILABLE` and **nothing is created** |
| Idempotency | safe to retry: a second `POST` returns the **same** number, and the bank cannot be changed afterwards |
| Reads | `GET /v1/virtual-accounts?currency=NGN` (scope `virtual_accounts:read`), `404` when there is none |
| Response | `id va_…`, `currency`, `account_number`, `account_name`, `bank_name`, `bank_code`, `status`, `created_at` |
| Capability | the account must hold `virtual_accounts`; before it is active the call returns `403 FORBIDDEN`. Listen for `capability.updated` |
| Number per account | **one per account per currency.** "There is no number per customer." |

Deposits arrive as a `collection.succeeded` webhook. A deposit is distinguishable from a checkout
payment because `checkout_id` is `null` and `payment_method_details.bank_transfer.virtual_account.type`
is `permanent`:

```json
{
  "id": "evt_…", "type": "collection.succeeded",
  "data": {
    "charge_id": "ch_…", "checkout_id": null, "reference": null,
    "status": "SUCCEEDED", "amount": "250000.00", "currency": "NGN",
    "settlement_amount": "249700.00", "processing_fee": "300.00", "fee_bearer": "merchant",
    "customer": { "id": null },
    "payment_method_details": { "type": "bank_transfer", "bank_transfer": {
      "sender_name": "JANE ADEYEMI", "sender_bank": "Guaranty Trust Bank",
      "sender_bank_code": "058", "sender_account_number": "2294879124",
      "session_id": "000013260922104115000821734502", "narration": "INV-1042",
      "virtual_account": { "id": "va_…", "account_number": "9902847361",
                           "bank_name": "Nombank MFB", "type": "permanent", "expires_at": null }
    } },
    "metadata": {}
  }
}
```

Facts that shape the design:

- **A deposit carries no reference to an order.** `reference` and `metadata` are empty; matching a
  deposit to a customer is explicitly *your* job.
- **Every sender field can be `null`** (sending banks omit them, `narration` most often).
- **Fees come off first:** 1% capped at ₦300 per deposit (`settlement_amount` already nets it).
  Bank-transfer *checkout* is 1.5% capped at ₦2,000.
- **Deposit limits:** default ₦5,000,000 per deposit and per UTC day. A deposit over the limit is
  **not refused** — it is silently held for review, with no flag in the event and no event on release.
- **Deposits cannot be refunded through the API** (`is_refundable: false`); mistakes go to Bachs support.
- **Sandbox numbers cannot receive money** (they start with `90`, bank `Sandbox Test Bank`), and
  `bachs trigger collection.succeeded` produces a *checkout-shaped* event. Deposit handling must
  therefore be tested with fixture payloads, not end-to-end.

## 2. Two possible models

### Option A — one shared TLStore account number (recommended now)

TLStore owns the number; every user transfers to the same account and includes a TLStore deposit
code in the narration.

| Needs | Nothing beyond TLStore's own verified Bachs business account, its representative's name +
BVN on file, and a key with `virtual_accounts:read/write` |
| Money lands in | the platform's NGN Bachs balance; TLStore credits the user's `topup_fund` wallet |
| Matching | deposit code in `narration` → known sender account number → admin review queue |
| Effort | ~6–8 engineering days (detail in §5) |
| Compliance | none beyond what TLStore already has; TLStore never stores customer BVNs |

### Option B — a number per user (connected accounts)

Each TLStore user becomes a Bachs connected account, and the number is issued there with
`X-Account-Id`.

| Needs | `connect` capability (platform must be a **registered business**, one-way upgrade, business
compliance + review), then per user: `POST /v1/accounts` with `display_name`, `country: NG`,
`configuration.recipient.capabilities.virtual_accounts.requested = true`, **plus a representative
person with the user's own first name, last name and BVN** (`relationship.representative: true`),
then onboarding until the capability is `active`, then `POST /v1/virtual-accounts` with `X-Account-Id` |
| Money lands in | that **user's own** connected-account balance, not TLStore's. Getting it into a
TLStore wallet needs a transfer back to the platform (or destination charges) |
| Compliance | TLStore becomes a payment platform: BVN collection/storage (NDPR), name-vs-BVN
verification failures, per-user KYC support, Bachs review of the platform |
| Effort | +2–3 weeks engineering **and** a compliance/onboarding project |
| Verdict | not viable for a wallet top-up feature today; revisit only if TLStore wants to be a
marketplace that pays sellers directly |

**Recommendation: build Option A now.** It gives users the behaviour they actually asked for
("one account number I can always send money to"), reuses the existing top-up ledger pipeline, and
keeps TLStore out of holding customer KYC. The schema should carry a nullable `user_id` on the
virtual-account table so Option B can be added later without a rewrite.

## 3. What TLStore must obtain before any code runs

1. **Bachs live account as a registered business** with NGN balance; a representative with first
   name, last name and **BVN** on file (this is what turns the `virtual_accounts` capability on).
2. **API key scopes:** `virtual_accounts:read` and `virtual_accounts:write`. Existing keys do *not*
   gain scopes automatically — they are added in the Developer Portal.
3. **`BACHS_WEBHOOK_SECRET`** and the webhook URL registered in the Bachs dashboard (the app already
   verifies `x-bachs-signature-v2` with a 300 s timestamp window).
4. **Capability check:** `GET /v1/accounts/me` → `enabled_capabilities` must contain
   `virtual_accounts`; otherwise `POST` returns `403`. Poll/watch `capability.updated`.
5. **Deposit limits** confirmed with Bachs (default ₦5m single/daily) and a named contact for held
   deposits.
6. **Settlement destination** for withdrawing the NGN balance to TLStore's bank account (`payouts`
   capability; first payout is held for a one-time review).
7. Operational answer for held deposits and mis-sent transfers (no API refunds).

## 4. Integration design (Option A)

### 4.1 Data model

| Table | Purpose |
|---|---|
| `bachs_virtual_accounts` | `provider_account_id` (nullable — `X-Account-Id` used), `va_id` unique, `currency`, `account_number`, `account_name`, `bank_name`, `bank_code`, `status`, `capability_state`, `last_synced_at`, `meta`. Unique (`provider_account_id`, `currency`). `user_id` nullable, reserved for Option B. |
| `bachs_deposits` | One row per `charge_id` (unique): `event_id`, `va_id`, `currency`, `amount_minor`, `fee_minor`, `settled_amount_minor`, `sender_name/bank/bank_code/account_number` (all nullable), `session_id`, `narration`, `status` (`unmatched`, `matched`, `credited`, `ignored`, `held`), `matched_user_id`, `payment_intent_id`, `matched_by_admin_id`, `matched_at`, `raw` JSON, timestamps. |
| `bachs_deposit_payers` | `sender_account_number` → `user_id`, `confirmations`, `last_seen_at`. The "known payer" memory the docs recommend. |
| `users.deposit_code` (or a `deposit_codes` table) | Short, checksummed code shown in the app (`TLS-4KQ7-2`), unique, stable. A checksum stops a typo crediting the wrong person. |

### 4.2 Backend pieces (following the existing Application/Feature split)

| Class | Responsibility |
|---|---|
| `App\Application\Payments\VirtualAccounts\EnsureVirtualAccount` | Read `GET /virtual-accounts?currency=NGN`; on `404` `POST` it (optionally with `bank_code`); map `403` (capability not active), `VIRTUAL_ACCOUNT_REFUSED`, `VIRTUAL_ACCOUNT_BANK_UNAVAILABLE` into a typed result; persist/refresh the row, cache it. Never throws into a page. |
| `App\Application\Payments\VirtualAccounts\RecordBachsDeposit` | Idempotent ingestion from the webhook: dedupe on `charge_id`, convert decimal strings with `CurrencyMinorUnits::toMinor`, store the row, then attempt auto-match. |
| `App\Application\Payments\VirtualAccounts\MatchBachsDeposit` | Match order: (1) `TLS-…` code in `narration`; (2) known payer account number; (3) single open top-up request for the exact amount; (4) leave `unmatched`. Never guesses into a wallet. |
| `App\Application\Payments\VirtualAccounts\SettleBachsDeposit` | Creates the `PaymentIntent` (provider `bachs`, purpose `topup`, `provider_reference = charge_id`, idempotency `bachs-va:sha256(charge_id)`) and credits the `topup_fund` wallet; records fee metadata; notifies the user; audits when an admin matched it. |
| `App\Application\Payments\SettleTopupIntent` (refactor) | Extract the private `creditTopup()` currently inside `ProcessGatewayWebhook` so checkout webhooks, VA deposits and the admin console (Phase 3 `SettleGatewayPaymentManually`) share **one** posting path. |
| `BachsDepositController` (web + API admin) | Unmatched queue: list, search by sender/narration/amount, "match to user", "ignore", replay the raw payload. Same shape as the Phase 3 gateway-payments console. |

### 4.3 Webhook changes (`ProcessGatewayWebhook`)

Two concrete defects to fix on the way in:

1. **Event type**: the handler reads `event` / `event.type`; Bachs sends `type`
   (`collection.succeeded`), so `payment_webhook_events.event_type` is currently stored as
   `unknown`.
2. **Deposits are dropped**: there is an early `if ($provider === 'bachs' && ! is_string($data['checkout_id']))`
   that marks the event `ignored`. A VA deposit has `checkout_id: null`, so today every deposit
   would be silently discarded.

New flow for `bachs` + `type = collection.succeeded` + `checkout_id = null` +
`payment_method_details.type = bank_transfer` + `virtual_account.type = permanent`:

1. signature already verified (`x-bachs-signature-v2`, 300 s window) → keep;
2. dedupe on event `id` (existing `payment_webhook_events`) **and** on `charge_id`;
3. `RecordBachsDeposit` → auto-match → settle when matched;
4. always answer `2xx` quickly; on failure store `processing_error` so Bachs' replay
   (`POST /v1/webhooks/replay`) is safe;
5. also handle `capability.updated` (store `virtual_accounts` state, alert admins when it flips).

Optional defence in depth: confirm the deposit with `GET /v1/payments/{charge_id}` before crediting
(the signed webhook is already the source of truth; the fetch also returns sender details that the
payments *list* omits).

### 4.4 Accounting policy (must be decided before coding)

A ₦250,000 deposit settles ₦249,700 after the 1% (₦300 cap) fee. Two consistent choices:

- **Credit what the user sent (gross)** and book the ₦300 as a platform expense in
  `transactions.meta.processing_fee` / ledger metadata — consistent with how checkout top-ups are
  credited today (`$intent->amount_minor`), and what users expect. The Bachs balance then sits below
  the sum of credits by the accumulated fees, so a **fee reconciliation report** is required.
- **Credit the net settlement** and tell the user the fee explicitly — keeps ledger cash-exact but
  contradicts existing checkout behaviour.

Recommendation: **gross credit + recorded fee + a daily reconciliation report** (see §4.6), documented
as a new section of `docs/money-and-ledger-invariants.md`. Deposits are credited once; the ledger key
is `payment-intent:{id}` as for every other top-up, and `bachs-va:sha256(charge_id)` makes the
webhook/whole pipeline idempotent under replay.

### 4.5 API and UX

| Surface | Behaviour |
|---|---|
| `GET /api/v1/topups/bank-transfer-account` (auth) | Returns `{account_number, account_name, bank_name, deposit_code, instructions}`; lazily calls `EnsureVirtualAccount`; `503` + "bank transfer is temporarily unavailable" when the capability is not active. |
| `GET /api/v1/me/bank-deposits` (auth) | Recent deposits attributed to the user and their state (`received`, `credited`, `under review`). |
| Fund-wallet screen (Flutter) | "Pay by bank transfer" panel: account number + bank + copy buttons, the user's `deposit_code` with an explicit "put this in the narration" instruction, and a short explainer that credits appear after the deposit is confirmed. |
| Storefront/help | Same instructions on the web (`/contact` already exists for "money sent but not credited"). |
| Admin console | "Bank deposits" page (finance + super admin): unmatched queue, per-deposit detail with sender fields, match/ignore actions, and a link to the webhook event. Add to the Phase 3 nav pattern in `resources/views/admin/layout.blade.php`. |
| Notifications | `deposit_received` ("₦250,000 received — credited"), `deposit_unmatched` (to admins), `deposit_review` (to the sender when held/unmatched). |

Deposit codes are the load-bearing UX detail: **"include `TLS-4KQ7` in the transfer description"**.
Banks drop narration often enough that the code cannot be the only path, hence known payers and the
review queue.

### 4.6 Operations

- `bachs:ensure-virtual-account` — idempotent create/refresh (deploy-time and cron safety net).
- `bachs:reconcile-deposits` (daily) — Bachs NGN balance and `GET /v1/payments` (deposits have
  `payment_method.type = ngn_bank_transfer` and no `source.checkout_id`) vs `bachs_deposits` rows and
  ledger credits; report unmatched deposits older than 24 h and fee drift.
- Extend the existing balances page (`GatewayBalances` already reads Bachs `/balances`) with the
  virtual account card and capability state.
- Alerts when `capability.updated` reports `virtual_accounts` as not active, or when a deposit is
  held for review (no event is sent on release, so polling the balance is the only signal).

## 5. Delivery plan

| Phase | Work | Days |
|---|---|---|
| 0. External | Bachs business verification, representative BVN, key scopes, webhook URL, deposit limits, payout destination | business-side, blocking |
| 1. Foundation | config (`virtual_account_path`, feature flag), migrations, models, `EnsureVirtualAccount`, `bachs:ensure-virtual-account`, admin "account details" card | 1–1.5 |
| 2. Ingestion | webhook branch (event type fix + deposit branch), `RecordBachsDeposit`, `SettleTopupIntent` extraction, fee policy, notifications, feature tests with fixture payloads | 1.5–2 |
| 3. Matching | deposit codes, payer memory, auto-match, admin queue with match/ignore, audit trail | 1–1.5 |
| 4. UX | API endpoints, Flutter fund-wallet panel, deposit history, help copy | 1 |
| 5. Hardening | reconciliation command, replay drill, sandbox capability test, live smoke with a small real deposit, docs | 1 |

Total ≈ 6–8 engineering days after the Bachs account work, versus 2–3 weeks + compliance for
Option B.

## 6. Test strategy given the sandbox limits

- Sandbox numbers cannot receive money and `bachs trigger collection.succeeded` sends a
  *checkout-shaped* event — so deposit logic is tested with the documented deposit payload as a
  fixture (`Http::fake` + `Mail::fake`), asserting: correct minor-unit conversion, `null`-safe sender
  fields, fee/settlement handling, idempotency under replayed events and duplicate `charge_id`s.
- Signature tests already exist for `x-bachs-signature-v2`; add a deposit-shaped signed request.
- Matching tests: code in narration, known payer, exact-amount ambiguity (must go to review, never
  auto-credit), unmatched queue actions by finance/super admin only, and "second credit refused".
- One live smoke test with a real ₦100 transfer before announcing the feature.

## 7. Risks and open questions

| Risk | Mitigation |
|---|---|
| A user transfers without the code and the sender fields are null | Review queue + known-payer memory; admin matches manually; nothing is ever auto-credited on a guess |
| Deposit over ₦5m is held, no event, no flag | Poll balance; document limits; support script keyed on `charge_id` |
| Two users, same amount, same minute | Never auto-match by amount alone when more than one candidate exists |
| Fees make Bachs balance < sum of credits | Fee metadata + daily reconciliation report |
| Bachs number goes `inactive` | It disappears from reads but still receives; alert admins, never re-issue a different number silently |
| Webhook replay double-credit | `payment_webhook_events` dedupe + `charge_id` unique + `payment-intent:{id}` ledger key |
| Capability not yet active in production | Endpoint returns `503` with a clear message; admins see the state on the balances page |

**Open questions for the business:** (1) do we credit gross or net of the 1% fee? (2) is a single
shared number acceptable, or is a per-user number a product requirement worth the Connect/KYC
project? (3) which deposit limits and review thresholds do we set, and who owns the unmatched queue
(SLA)? (4) do we also offer the existing 30-minute bank-transfer checkout for one-off top-ups
(already supported by `InitializeGatewayTopup`), and how do we present the two options without
confusing users?
