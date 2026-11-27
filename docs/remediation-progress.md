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
