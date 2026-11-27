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
