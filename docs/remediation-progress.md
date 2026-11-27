# Remediation Progress Log

**Started:** 2026-11-27
**Plan:** [`remediation-roadmap.md`](./remediation-roadmap.md) · **Findings:** [`project-errors.md`](./project-errors.md)

Work is delivered one phase per pull request. This log records what each phase changed,
how it was verified in the sandbox (see [`workstation-tooling.md`](./workstation-tooling.md)
for the toolchain and its caveats), and what is deliberately left for later phases.

---

## Phase 0 — Emergency Security Containment ✅ (2026-11-27)

| Item | Status | Change |
|---|---|---|
| `ERR-01` public Adminer backdoor | ✅ | Deleted `public/_2KcfSjmX.php`; `.htaccess` now refuses every `public/*.php` except `index.php`/`redirect.php`; `.gitignore` blocks re-adding stray web-root PHP; new [`web-server-hardening.md`](./web-server-hardening.md) adds the equivalent nginx / Caddy configuration for the edge. |
| `ERR-02` committed secrets | ✅ (rotation pending) | `VTPASS_API_KEY/PUBLIC_KEY/SECRET_KEY`, `FACEBOOK_APP_SECRET`, `FACEBOOK_CLIENT_TOKEN`, `TELEGRAM_BOT_TOKEN` and `SYSTEM_ACCOUNT_PASSWORD` blanked in `.env.example`. **The previously committed values are compromised and must be rotated in each provider dashboard** — see the PR description. |
| `ERR-06` production seeding guard | ✅ | `DatabaseSeeder` restores the non-production guard for demo data, seeds `PackageAddonSeeder` exactly once in the configuration block (feature data, all environments), and the demo seeders now refuse weak/missing passwords via `database/seeders/Concerns/ProvidesDemoAccountPassword.php` (`config('app.system_account_password')` no longer defaults to `Password123!`). |
| `ERR-03` admin credentials & admin UI in the reviewer app | ✅ (Dart uncompiled) | Removed `admin@tlstore.test` / `meandmyson` prefill from all 13 admin screens; new `review/lib/config/admin_ui.dart` compiles the ADMIN PAGES section out of reviewer builds (`--dart-define=ENABLE_ADMIN_UI=true` opts an admin build back in). The Flutter/Dart SDK is unavailable in this sandbox, so these Dart edits were reviewed by hand and not compiled. |
| `ERR-32` admin login brute force | ✅ | New `admin-auth` rate limiter (5/min per email+IP, 20/min per IP) applied to both `POST /admin/login` and `POST /api/v1/admin/auth/login`. |

### Verification evidence

| Check | Result |
|---|---|
| `git ls-files public` PHP entries | only `index.php`, `redirect.php` |
| `php -l` over every changed PHP file | clean |
| `pint --test` over changed PHP files | clean (pre-existing violations elsewhere untouched) |
| `POST /api/v1/admin/auth/login` × 7, same email | `422, 422, 422, 422, 422, **429**, 429` |
| Same IP, different email, after the lock-out | still `422` (per-account key, not a global IP lock) |
| `APP_ENV=production db:seed --force` | runs only `SystemSetting`, `NotificationTemplate`, `AboutPageSettings`, `SubscriptionGroup`, `PackageAddon`; admins/users/wallets unchanged (0 demo rows created) |
| `APP_ENV=local`, blank `SYSTEM_ACCOUNT_PASSWORD` | demo seeders generate a one-off password and print it |
| `APP_ENV=staging`, blank `SYSTEM_ACCOUNT_PASSWORD` | demo seeders skip with a warning |
| PHPUnit | still **13 tests / 13 errors** — unchanged from the audit baseline; the `gs()` boot crash is Phase 1 (`ERR-04`/`ERR-07`) |

### Residual risk carried into later phases

- **Credential rotation is external** (Telegram BotFather, Meta console, VTpass dashboard) and
  cannot be done from the repository.
- `public/web/main.dart.js` (the prebuilt Flutter web bundle) does **not** contain the admin
  credentials (verified), but it is still the stale `gosmm.com` build — `ERR-17`, Phase 3.
- The admin screens remain *compiled* into a reviewer binary even when the nav is gated;
  splitting them into a separate admin entry point is a Phase 5 UX decision.
- Removing the Adminer file is containment, not remediation: the production database
  credentials it exposed should be treated as compromised until rotated.

---

## Phase 1 — Boot & test-suite stabilisation ✅ (2026-11-27)

Goal: the framework boots against a fresh database, `gs()` can never take the app down,
and the existing PHPUnit suite goes from *failing before it starts* to green.

| Item | Status | Change |
|---|---|---|
| `ERR-04` `gs()` helper | ✅ | Rewritten as a keyed accessor: `gs()` returns every public setting (`[]` when the table or cache is empty), `gs('key', $default)` returns one value; reads are schema-guarded (`Schema::hasTable`), filtered with SQL-level `where('is_public', true)`, cached for 30 minutes and never throw — a `database` cache store with no `cache` table used to abort `artisan` itself. |
| `ERR-07` stale `GeneralSetting` cache | ✅ | `SystemSetting::saved()` now forgets the `GeneralSetting` key, so an edited setting is visible immediately instead of after the TTL. The `Cache` calls are wrapped so a cache outage is a miss, not a 500. |
| `ERR-08` `users.wallet_currency` NOT NULL without default | ✅ | New migration `2026_11_27_090001_add_default_to_users_wallet_currency` (default `NGN`); `User::booted()` fills the attribute from `preferred_currency` before insert; `UserFactory` supplies it. Every "insert a user" path (tests, seeders, admin API) stopped failing with an integrity-constraint violation. |
| `ERR-09` plan-key drift | ✅ | The seeder is the source of truth (`market-lite` / `market-premium` / `market-gold`); `SubscriptionPackageGroupsTest` and `GroupADailyShareRevenueTest` were still asserting the retired `demo-market-*` keys and now match the seeded data. |
| `ERR-05` VAS purchase fatal | ✅ | Missing `use App\Enums\LedgerEntryStatus;` in `PurchaseVasOrder` made **every** VTU purchase die with `Class not found`. |
| `ERR-19` ad-share auto-approval TypeError | ✅ | `AutoApproveAdShares` passed a sixth argument to `DecideAdShare::handle()` (five parameters), so every auto-approval run crashed; the now-unused `$subscription` capture was dropped too. |
| `ERR-12` ledger relations | ✅ | `AdShareEvent`, `PaymentIntent`, `ReferralAttribution`, `ReviewSubmission` and `WithdrawalRequest` now declare `morphMany(LedgerEntry::class, 'source')` instead of `hasMany(...)->where('source_type', '…')`; the three that lacked `TrustLensMorphMap` now use it, so the alias is always registered. Query bindings are unchanged (`ad_share_event`, `payment_intent`, …). |
| `ERR-13` dead immutability guard | ✅ | `AdminConsoleController` referenced `ReviewQuestion` without importing it, so the "answered questions cannot be edited" check could never run. |
| `ERR-14` review-step defaults | ✅ | The `FrontendController` closure that builds review steps now captures `$configured`; without it every request touching the step defaults raised an undefined-variable error. |
| `ERR-15` `money()` helper | ✅ | `Transaction::getSignedAmountAttribute()` called a `money()` function that does not exist; it now formats using the transaction currency's minor units. |
| `ERR-11` / `ERR-33` transaction taxonomy | ✅ | `campaign_order` joined the `payment` history category and the "other" exclusion list; `VTU_PURCHASE` is no longer treated as a gateway charge (it is paid from the wallet balance); `Salary Salary`/`Referal Reward` labels corrected and the unreachable `default` arms removed. |
| `HasFactory` | ✅ | `App\Models\User` never used the trait although `UserFactory` and `TransactionFactory` (which calls `User::factory()`) exist — both were unusable. |

### Verification evidence

| Check | Result |
|---|---|
| PHPUnit (full suite) | **20 tests / 133 assertions — OK** (start of phase: 13 tests, 9 errors, and before the sandbox workaround, no output at all) |
| `php artisan migrate` on a deliberately broken store | green on an empty SQLite file, on the developer database, and with a `database` cache store whose `cache` table is missing |
| Morph-map regression probe | all five relations return `MorphMany` and bind the same aliases as before (`ad_share_event`, `payment_intent`, `referral_attribution`, `review_submission`, `withdrawal_request`) |
| `php -l` over every changed PHP file | clean |
| New `tests/Feature/Phase1RegressionTest.php` | 7 tests / 22 assertions covering `gs()` (empty schema, cached reads, defaults), the wallet-currency default, `signed_amount`, transaction labels/gateway classification, `campaign_order` in the payment category, the morph alias bindings and the VAS enum import |
| PHPStan | **not reproducible as stated in the audit** — see caveats below |

### Test fixtures adjusted (not weakened)

- `SubscriptionPackageGroupsTest` / `GroupADailyShareRevenueTest`: `demo-market-*` → `market-*`.
  The seeded rows are `market-*`; nothing else in either test changed.
- `AccountHistoryControllerTest`: spending statistics are computed from **paid
  `withdrawal_requests`** (a rejected reservation is not spending), so the fixture now
  creates the settled request instead of only its transaction projection. The assertions
  are untouched.

### Caveats and residual risk

- **PHPStan was not part of `composer.json`.** The audit reported "PHPStan Level 5 → 0
  errors", but without `larastan` the tool reports 800+ Eloquent magic-property false
  positives across the project, so that number cannot be reproduced from the repository as
  committed. PHPStan 2.3.7 was fetched as a phar to run it here; on the files this phase
  touched the only findings are the same pre-existing magic-property/`View` contract
  notices. **Recommendation: add `larastan` + a baseline in the Phase 6 CI work** so the
  gate is real rather than aspirational.
- The PHP-WASM runtime cannot run `PendingCommand` (PHPUnit's `RefreshDatabase`) because the
  command is executed from the destructor. `.tools/apply-sandbox-patches.php` appends the
  missing `->run()` **in `vendor/` only**; it is a sandbox workaround, never a production
  change, and has to be re-applied after any `composer install` in the sandbox.
- `AccountHistoryController` still converts `SUM(amount)` per currency with
  `CurrencyMinorUnits::toMinor()` in PHP; a mixed-precision currency would need the
  SQL-side rewrite tracked under `ERR-10`/`ERR-11` (Phase 2).

---

## Phase 2 — Financial, ledger, payment & webhook integrity ✅ (2026-11-27)

Goal: one deterministic source of truth for balances and history, and no payment path that
can leave a phantom `pending` row. The rules are now written down in
[`money-and-ledger-invariants.md`](./money-and-ledger-invariants.md).

| Item | Status | Change |
|---|---|---|
| `ERR-10` duplicate/permanently pending top-ups | ✅ | New `App\Enums\TransactionStatus` (the canonical vocabulary) and `App\Application\Payments\PaymentIntentLifecycle`, the single idempotent writer that moves an intent **and** its `payment-intent:{id}` projection together. Gateway webhooks that report failure/cancellation now close the intent instead of being marked `ignored`; `POST /topups/{intent}/cancel` closes both rows; new `payments:expire-intents` command (scheduled every 5 min) expires stale checkouts. `VerifyGatewayTopup`, `InitializeGatewayTopup` and the cancel endpoint all route through the same writer. |
| `ERR-11` / `ERR-33` conflicting projections | ✅ | The observer now maps ledger statuses one-to-one onto the canonical vocabulary (`posted` → `completed`) with a dedicated `activityStatus()` for review/share/campaign workflows, distinguishes `campaign-order-refund:{id}` from `campaign-order:{id}` and flags `meta.refund`. The account-history `status` filter normalises legacy spellings (`posted`, `success`, `reserved`) and now accepts `completed`/`approved`/`expired`. |
| `ERR-18` Bachs verification | ✅ | `VerifyGatewayTopup` reads `payment_status` (falling back to `charge.status`) for Bachs instead of the envelope `status`, matching the webhook. |
| `ERR-23` `email:rfc,dns` | ✅ | All three checkout validators (`TopupController`, `InitializeGatewayTopup`, `SubscriptionController`, `PackageAddonController`) use `email:rfc`; reserved/test domains no longer block a payment and checkout no longer depends on outbound DNS. |
| `ERR-24` seeder contradictions | ✅ | Group A plans seed `withdrawal_days` from one constant (`['Monthly 28th']`) for both the column and the customer-facing `features` block, and the review plans' `task_referral_percent` now equals `task_referral_tiers['1']` (5%), which is what `CreditReferralReward` actually pays. |
| `ERR-31` Korapay bank resolve | ✅ | The payout resolver maps `banks.country` → ISO-4217 currency through `countries.default_currency` (with an inline fallback) and rejects unknown countries instead of calling the provider. **Also fixed:** `account_validation.provider` was read with `->value('value')`, bypassing the model's array cast, so every deployment silently used Paystack — a configured Korapay/Flutterwave/Bachs provider was ignored. |
| `ERR-36` `env()` outside config | ✅ | `config/trustlens.php` resolves gateway credentials once into `$paymentGateways`; `implemented_payment_gateways` derives from that array (no second set of `env()` calls). `DemoAdminSeeder` no longer calls `env()` (it already did not at this commit). `php artisan config:cache` verified. |
| new: `ledger:verify` | ✅ | Read-only command that proves the Phase 2 exit criteria: every wallet balance equals its ledger impact sum, every transaction status is canonical, and no terminal intent is still projected as `pending`. Scheduled nightly; exits non-zero on drift. |
| new: data migration | ✅ | `2026_11_27_090002_normalize_transaction_statuses` rewrites legacy `posted` / `success` / `successful` / `reserved` rows to the canonical vocabulary. |

### Verification evidence

| Check | Result |
|---|---|
| PHPUnit (full suite) | **51 tests / 238 assertions — OK** (was 20 / 133 at the end of Phase 1) |
| New webhook suite `PaymentWebhookLifecycleTest` | success, duplicate replay (no double credit), failure, cancellation, non-terminal provider status, cross-currency top-up, and a reserved-domain checkout — all through the real endpoint with a valid HMAC signature |
| New `PaymentIntentExpiryTest` | expiry job expires stale checkouts only (open/paid/manual untouched, completed projections never downgraded), cancel endpoint closes both rows |
| New `PayoutAccountResolutionTest` | Korapay receives `NGN` (not `NG`), unknown countries are rejected before any HTTP call, Paystack path unaffected |
| New `TransactionProjectionTest` | posted → `completed`, reserved → `pending` (and a hold on the balance), refund row separate + `completed`, balance never negative |
| New `LedgerIntegrityCommandTest` + `WithdrawalScheduleTest` | 12 command/parser tests (unrecognised schedule tokens fail open, mixed tokens still block) |
| `php artisan ledger:verify` on the developer database | 109 wallets, **0 balance discrepancies**, 0 non-canonical statuses, 0 dangling intents |
| `php artisan config:cache` / `config:clear` | both succeed with the rewritten config |

### Deliberate decisions

- **Top-ups are never converted.** A USD payment credits a USD `topup_fund` wallet; rewards
  are converted into `wallet_currency`. Converting a top-up would make the history disagree
  with what the provider actually charged.
- **`posted` is a ledger status, not a transaction status.** Legacy clients keep working:
  the API normalises `posted`/`success`/`reserved` on input, and the nightly checks report
  any non-canonical row.
- **A reservation is a `pending` transaction with a real balance hold**, which matches what
  the wallet does.

### Residual risk moved to later phases

- Wallet-funded subscription/add-on purchases still write their projection directly (the
  observer rewrites the same reference immediately afterwards). Converging them on the
  observer is a Phase 6 refactor; behaviour is already consistent and covered.
- `PaymentGatewaySettings` reads `SystemSetting` on every call; caching it is a Phase 4/6
  performance item.
- Provider payload shapes (Bachs `charge.status`, Korapay signature canonicalisation) are
  covered by unit-level fakes here, not by live sandbox calls — the sandbox has no outbound
  network. Validate against provider sandboxes before release.

---

## Phase 2 follow-up — admin console for gateway payments ✅ (2026-11-27)

Requested addition: finance had no way to reconcile deposits taken by Korapay / Paystack /
Flutterwave / Bachs, and nobody could see the money sitting at a provider.

| Item | Status | Change |
|---|---|---|
| Gateway payments console | ✅ | `/admin/gateway-payments` lists every gateway intent (filters: gateway, purpose, status, date range, reference / idempotency key / user). Detail page shows the amount, provider reference, ledger entries, provider receipts, the transaction projection and the raw provider metadata. Roles: `super_admin`, `admin`, `finance`. |
| Revalidate payment request | ✅ | "Revalidate" asks the provider what happened and applies the answer through the normal completion path (`VerifyGatewayTopup`): paid → credit/activate, failed/cancelled/expired → close the intent and its projection, still pending → no change. Provider/network failures are surfaced in the console and audited, never 500. |
| Manually verified payment | ✅ | Approving a payment marks the intent paid, records a provider receipt, runs the **intended action** (top-up credit / subscription activation / add-on entitlement), notifies the user and writes an audit entry with the administrator, note and reconciled reference. A second approval is refused, so a payment cannot be credited twice. Rejecting closes intent + projection and notifies the user. |
| Super-admin live balances | ✅ | `/admin/gateway-balances` (super admin only) reads each enabled gateway's balance API live: Paystack `/balance`, Korapay `/balances`, Flutterwave `/balances/{currency}`, Bachs `/balances`. Endpoints are configurable per environment (`*_BALANCE_PATH`); missing credentials or provider errors show as "Unavailable" instead of failing the page. |
| Mobile admin parity | ✅ | `GET/POST /api/v1/admin/gateway-payments*` and `GET /api/v1/admin/gateway-balances` expose the same operations and role rules for the mobile admin app. |
| **Bug found while building it** | ✅ | Gateway **subscription** checkouts were fatally broken: `InitializeGatewayTopup` and `ProcessGatewayWebhook` wrote the plan's product type (`marketplace_access`) into `transactions.type`, which is cast to `TransactionType` → `ValueError` on insert. Both now write `subscription_plan` / `package_addon`, and the flow is covered end-to-end (checkout → webhook → active subscription). |

### Verification evidence

| Check | Result |
|---|---|
| PHPUnit (full suite) | **65 tests / 298 assertions — OK** |
| `GatewayPaymentAdminTest` (13 tests) | finance role sees only gateway intents; moderator forbidden; revalidate settles / leaves pending; manual approval credits exactly once (second attempt refused, wallet unchanged); approval activates a subscription and creates an add-on entitlement; rejection closes intent + projection and notifies; detail page renders; wallet/manual intents 404 here; super-admin-only balances; a provider 401 shows "Unavailable" and the page still renders; API parity + role enforcement |
| `PaymentWebhookLifecycleTest` | new end-to-end subscription checkout → webhook → `user_subscriptions` active (guards the `transactions.type` regression) |
| `php artisan route:list --path=admin` | web + API routes registered for all five endpoints |

---

## Public contact form / guest support ✅ (2026-11-27)

Requested fix: the Flutter landing page ("Get in touch" → *Message us*) posted to
`POST /api/v1/guest-support`, which did not exist, and its client helper expected a login
response with a Sanctum token — so the form either 404'd or tried to sign the visitor in.

| Item | Status | Change |
|---|---|---|
| `POST /api/v1/guest-support` | ✅ | Public endpoint (`GuestSupportController`) validated through a shared `ContactFormRules` contract, answering `201` with the ticket reference. No token is issued and no account is required. |
| Persistence | ✅ | Migration `2026_11_27_110001_add_guest_fields_to_support_tickets` makes `support_tickets.user_id` nullable and adds `guest_name`, `guest_email`, `source`, `ip_address`, `user_agent`. Guest tickets reuse the existing admin inbox instead of a parallel table. |
| Emails | ✅ | `app/Mail/Support/*`: acknowledgement to the visitor, notification to `SUPPORT_EMAIL` (with the matching account's name when the address belongs to a user), and admin replies emailed to the guest. They are dispatched with `defer()`, so a slow or broken SMTP host neither delays the visitor's response nor loses the ticket (errors are reported, headers are newline-sanitised). |
| Spam & abuse | ✅ | Honeypot field (`website`) answered with a quiet `201`; `guest-support` limiter (4/min per IP, 6/hour per email address); a duplicate email + subject inside five minutes returns the existing ticket so a double-tapped send does not open two. |
| Storefront form | ✅ | `GET /contact` (branded Blade page, CSRF, inline validation and success state) posts to the same action; the landing footer now links to it instead of `mailto:support@`. |
| Admin handling | ✅ | Support inbox lists guest tickets with a *Guest form* badge, search covers guest name/email, the ticket page shows the source and IP, and replying emails the guest (no `Notification` row for a non-account). Admin API list rows carry a `guest` block. |
| Client | ✅ (Dart uncompiled) | `AuthApi.submitGuestSupport()` posts without a token; `ContactUs` shows the ticket reference in a snackbar and stays on the page. `isUser = widget.user.isNotEmpty` restores the `+ Ticket` route for signed-in reviewers. |

### Verification evidence

| Check | Result |
|---|---|
| PHPUnit (full suite) | **78 tests / 397 assertions — OK** |
| `GuestSupportContactTest` | 13 tests / 101 assertions |
| `GuestSupportContactTest` coverage | store + acknowledge, registered email not attached to the account, 422 validation, honeypot stores nothing, duplicate submit reuses the ticket, per-IP 429 after 4 posts, storefront page renders/submits/validates, admin web list+search+detail+reply-by-email, admin API list+reply, signed-in tickets still notify the user, all three emails render with user input escaped, broken mailer still stores the ticket |
| Migration | applied and rolled back against a copy of the developer database; `user_id` foreign key preserved, existing rows untouched |
| Live sandbox check | `GET /contact` → 200; `POST /api/v1/guest-support` → `201` with `TKT-…` in 0.22 s while SMTP was unreachable (the two delivery failures were logged *after* the response); stored row had `user_id = NULL`, `source = public_form`, one `guest` message |

### Deliberate decisions / residual risk

- **A guest ticket is never claimed by an account.** Typing a registered email address does not
  put the ticket in that user's list, so a stranger cannot post into someone else's thread. The
  support inbox still shows the matching account name to help the agent.
- **Flutter source only.** `public/web/main.dart.js` is still the stale prebuilt bundle
  (`ERR-17`); a rebuild is needed before the web app picks up the new client code. The API
  endpoint already matches the shipped (`guest-support`) path, so the old bundle stops 404ing
  and stops trying to sign visitors in even without a rebuild.
- Guests cannot revisit their own ticket; the reference is emailed and the inbox notification
  carries the same number. A public "track my ticket" view would need a signed token and is a
  follow-up, not part of this fix.
