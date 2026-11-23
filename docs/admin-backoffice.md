# TrustLens Admin Backoffice

Server-rendered administration UI for the TrustLens (tlstore) platform, built with Laravel 13 + Livewire 4 + Tailwind CSS 4 + Alpine.js. It is **additive only**: the Flutter app, the public site, `/api/*`, `/payments/*` and the existing admin REST API are untouched. It reuses the target codebase's models, actions, enums and audit trail — it does not introduce a parallel security boundary or a parallel money ledger.

> **Read this before deploying.** Almost everything here was implemented without a running PHP runtime in the authoring environment. Dependencies were not installed, migrations were not run, and the test suite was written but never executed. See [Verification status](#verification-status) — do not point real users at an unverified deploy.

---

## 1. What it does

| Area | Screen | Notes |
|---|---|---|
| Overview | Dashboard | Aggregate counts, money owed/available grouped **per currency**, pending moderation tiles, recent admin actions |
| People | Users list & detail | Search, status filter, order/sort, full 360° of one user. Status changes, bans, wallet adjustments (ledger write), password resets, soft delete/restore |
| Moderation | Review queue | Review submissions awaiting decision; approve/reject with mandatory reason on reject |
| Moderation | Share queue | Ad-share events pending validation; approve/reject |
| Moderation | Manual deposits | Bank-transfer deposits submitted for verification; approve credits via the existing `DecideManualDeposit` action |
| Moderation | Withdrawals | Pending requests; "mark paid" records an externally-sent payout via `SettleWithdrawal`; reject releases the reserved funds |
| Finance | Transactions | Read-only projection of the app's `transactions` table (legacy decimal-major) |
| Finance | Payment providers | Read-only health: configured keys (presence only), 30-day webhook/intent counts, recent webhook failures. No balance checks, no sends |
| Finance | Notifications | Read-only delivery log per channel |
| System | Audit log | Every consequential admin action that the app already audits, filterable |
| System | Settings | Only a typed allowlist is editable; everything else is read-only (secrets excluded entirely); writes `system_setting.updated` |
| System | Administrators | Super-admin only. Create/enable/disable/role/password-reset admins |
| Account | Profile & password | Self-service; password change revokes Sanctum tokens and rotates remember-me |
| Reports | CSV exports | users / transactions / ledger / withdrawals, bounded to 5,000 rows, audited |

## 2. Architecture & security model

- **Guard.** A dedicated `admin` session guard (`config/auth.php`) with an `admins` provider. The backoffice never mixes app-user sessions with admin sessions.
- **Middleware.** `EnsureAdminSession` protects the whole backoffice group (redirects web guests to the backoffice login; signs out non-active admins). `EnsureSuperAdmin` additionally fences `AdminsIndex`. Both are also implemented as `admin.session` / `admin.super` route middleware aliases.
- **Livewire re-authorization.** Livewire update requests (`/livewire/update`) do **not** pass route middleware. Every privileged component re-checks authorization server-side on mount, on every render and on every mutating action (`App\Livewire\Backoffice\Concerns\AuthorizesAdminActions::authorizeAdmin()` / `::authorizeSuperAdmin()`). Never trust the route guard alone; a disabled admin cannot keep reading pages from an already-open tab.
- **Shared actions, not copies.** Wallet adjustments call `App\Application\Actions\Admin\AdjustWalletBalance` (same rule set the admin REST API uses: integer minor units, per-currency, idempotency key, ledger entry). Deposit/withdrawal decisions call the existing `DecideManualDeposit` / `SettleWithdrawal` actions, so business invariants and notifications are identically enforced.
- **Audit.** All mutating backoffice actions write `audit_logs` rows through the same model the API uses.
- **Money.** Amounts are stored as **integer minor units** (`wallets.balance_minor`, `ledger_entries.amount_minor`, `payment_*.*_minor`). Formatting happens via `App\Support\BackofficeFormat` using the per-currency exponent map in `config/trustlens.php → currency_minor_units` (default exponent 2). There is **no** USD conversion anywhere and **no** cross-currency summation; the dashboard groups by currency. The `transactions` table is a legacy decimal-major projection and is rendered as such.
- **Secrets.** Provider secrets come from the server environment only. The Settings UI never shows or edits values whose key contains `secret`, `credential` or `private_key`, mirroring the REST API's blacklist. "Provider keys configured" is shown as a boolean, never the value.
- **No external network calls.** The UI makes zero outbound requests (fonts are self-hosted via `@fontsource/sora`, icons via `remixicon`). Only CSV exports and file-proof streaming are server-side actions beyond rendering.
- **Proof files** are streamed through `ProofController` from the private `local` disk only; missing files yield 404. Nothing on the public disk is exposed.
- **No schema changes.** No new tables, no new columns, no `admins.avatar`. Admins continue to use the existing `admins` table.

## 3. Money display details

- Inputs accept major units with up to 6 decimals; they are parsed to minor units with the per-currency exponent (`BackofficeFormat::inputToMinor`). Example: `1234.50 NGN` → `123450`.
- Legacy `transactions.amount` is decimal; it is formatted with `BackofficeFormat::legacyDecimal` and is labelled "decimal units" wherever displayed. Do not use it to reconcile wallets — use `ledger_entries`.
- CSV `ledger` export emits raw minor units (integer). CSV `transactions` export emits decimal-major with currency.

## 4. Roles

| Role | Meaning |
|---|---|
| `super_admin` (also accepts `super-admin`, `super`; case-insensitive) | Full backoffice, including Administrators and settings writes |
| any other value (`admin`, `staff`, …) | Same operational privilege as the existing admin REST API: full queue, finance, user management. **There is no invented read-only or support tier.** |

Administrators cannot disable/demote themselves, and the last active super-admin cannot be disabled or demoted. Administrator creation generates a one-time password (or uses a provided ≥ 12-char password) shown exactly once.

## 5. Installation / setup

### 5.0 Prerequisite state you may need to fix first

`composer.json` now **requires `livewire/livewire ^4.3`** (added for this feature) but `composer.lock` was not regenerated in the authoring environment (no Composer available). Before installing, refresh the lock — do not hand-edit it:

```bash
composer update livewire/livewire --with-dependencies   # or full: composer update
```

(If the project already used `minimum-stability: dev` + Packagist access, this resolves cleanly. Laravel skeleton of 13.x supports Livewire 4.)

### 5.1 Install

```bash
composer update            # refresh lockfile, install Livewire 4
npm install
npm run build              # verified: builds resources/css/backoffice.css + js
php artisan migrate --force # no new migrations expected; safe to run
```

### 5.2 Environment

`.env` (or `.env.example` additions already present):

```dotenv
BACKOFFICE_PATH=backoffice     # serve under https://your-host/backoffice
BACKOFFICE_NAME="TrustLens Admin"
# ADMIN_BOOTSTRAP_EMAIL=…      # first super-admin (see below)
# ADMIN_BOOTSTRAP_PASSWORD=…
# ADMIN_BOOTSTRAP_NAME=…
```

### 5.3 Bootstrap the first super-admin — no default password exists

```bash
ADMIN_BOOTSTRAP_EMAIL=admin@example.com \
ADMIN_BOOTSTRAP_PASSWORD='choose-a-strong-password-12+' \
ADMIN_BOOTSTRAP_NAME='Platform Admin' \
php artisan db:seed --class=AdminBootstrapSeeder
```

The seeder is **idempotent**: an existing admin with that email gets its password reset and role forced to `super_admin` (rescue path), and every run writes an `admin.bootstrap_upserted` audit row. There is no committed credential anywhere in the repository. Record the password in your password manager; afterwards sign in at `/{BACKOFFICE_PATH}/login` and change it under **Account → Change password** (which revokes Sanctum tokens and remember tokens).

### 5.4 Rollback

Disable without touching data:

```dotenv
# Option A: move the backoffice somewhere obscure
BACKOFFICE_PATH=extremely-opaque-path
```

```php
// Option B (code rollback): remove the then-block in bootstrap/app.php that registers
// routes/backoffice.php and delete routes/backoffice.php. All data (audit logs etc.) remains.
```

No schema migration was added, so no down-migration is required.

## 6. Security notes & decisions

- **Font deviation.** The sample reference uses Nohemi (licensed). This implementation ships **Sora** (`@fontsource/sora`), a visually compatible open grotesk. No Nohemi files were copied. Icons use RemixIcon 4 (Apache-2.0). If you have a Nohemi license, swap the font family in `resources/css/backoffice.css` — do not copy from the sample.
- **No sample assets copied.** The sample backoffice is a design reference only. Excluded modules (loans, paylater, etc.) were never created. No sample business rules, schema, or credentials were copied into the target repo.
- **Session fixation / CSRF.** Login regenerates the session; logout invalidates it. All POST forms go through web+CSRF. Confirm dialogs call the Livewire component only after explicit consent.
- **Rate limiting.** Admin login is dual-keyed rate-limited (per email+IP and per IP), constant-shape failures, `status = active` attempt filter.
- **Proof upload streaming** never serves from the public disk; 404 on missing files.
- **Administrators area** writes audit rows on create/status change/role change/password reset and refuses self-disable/self-demotion/last-super removal.
- **What is intentionally NOT possible:** sending money, triggering payouts, impersonating users, balance sync from providers, editing provider secrets, any cross-currency balance sums. These are absent by design, not pending.

## 7. Verification status

**Written but never executed in the authoring environment** (PHP and Composer were unavailable; only `npm install` / `npm run build` could be run, and both succeeded):

1. `composer.lock` has not been regenerated (Livewire `^4.3` is in `composer.json` but not in the lock).
2. No PHPUnit/Feature test has been run. `tests/Feature/Backoffice/BackofficeAccessTest.php` (authz redirect/403/200, settings allowlist + audit, out-of-range rejection, bootstrap seeder) and `tests/Unit/Backoffice/BackofficeAdminModelTest.php` are written and expected to pass after `composer update`, but **this is unverified**.
3. No `php artisan migrate`, route-cache, view-cache or Livewire compile has run; no Blade template has been compiled by the engine (a basic balanced-directive heuristic was applied instead).
4. No browser session has been opened against the backoffice; JS/Alpine behavior (confirm dialog, toasts, sidebar drawer) is unverified end-to-end.
5. `/livewire/update` authorization tests were not run; treat the mount/action re-checks as reviewed-by-inspection only.

Recommended acceptance pass on a real machine:

```bash
composer update
php artisan migrate --force
php artisan test --filter=Backoffice
php artisan route:clear && php artisan view:clear && npm run build
# then manually click through: login → dashboard → each queue → settings → CSV exports → admins
```

## 8. Known gaps / deliberate decisions

- **Lockfile**: must be regenerated by the deployer (see 5.0). If you see `composer install` fail with "lock file out of date", that is why — do not force-install with the stale lock.
- **`.env.example` contains real-looking values** added before this work (e.g. a Telegram bot token and `SYSTEM_ACCOUNT_PASSWORD`). These are pre-existing, not introduced here, but should be scrubbed and rotated by the owner.
- Settings editable allowlist: `vas.enabled`, `vas.catalog_cache_minutes`, `campaign.service_fee_percent`, `campaign.currency`. Everything else is read-only in the UI; provider secrets are blacklisted by key substring (`secret`, `credential`, `private_key`).
- `transactions` is a legacy decimal projection; wallet truth is `ledger_entries`.
- Reports CSVs cap at 5,000 rows and are audited (`report.export` audit row).
- Payment providers screen shows presence of keys and 30-day webhook/intent stats only — it never calls any provider API.
- Backoffice bootstrap is env-driven only; there is no UI path to become the first admin and no default credentials.
- Nothing was committed; all work is in the working tree on `arena/91221293-tlstore` for review.

## 9. Files (map)

```
bootstrap/app.php                      Registers routes/backoffice.php (additive), middleware aliases, backoffice redirects
config/backoffice.php                  BACKOFFICE_PATH / BACKOFFICE_NAME
config/auth.php                        admin session guard + admins provider (additive)
routes/backoffice.php                  All backoffice routes (name prefix backoffice.)
routes…                                (admin REST API untouched)
app/Http/Middleware/EnsureAdminSession.php, EnsureSuperAdmin.php
app/Livewire/Backoffice/**             Screens (Login, Dashboard, Users, Moderation queues, Transactions,
                                       Providers, Notifications, AuditLog, Reports, Settings, Admins, Account)
app/Features/Admin/Http/Controllers/Backoffice/   ProofController, ReportExportController
app/Application/Actions/Admin/AdjustWalletBalance.php   Shared wallet-adjust rule (API delegates)
app/Support/BackofficeFormat.php       Integer minor-unit formatting (no floats for storage, no FX)
database/seeders/AdminBootstrapSeeder.php             First-super-admin bootstrap (env-only)
database/factories/AdminFactory.php                   Test factory (super_admin / inactive states)
resources/views/components/backoffice/**, resources/views/components/layouts/backoffice*.blade.php
resources/css/backoffice.css           Design system (Sora, RemixIcon, Tailwind 4)
public/build/**                        Vite build output (npm run build)
tests/Feature/Backoffice/**, tests/Unit/Backoffice/**
```
