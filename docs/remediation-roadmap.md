# TLStore / TrustLens — Phased Remediation & Implementation Roadmap
**Date:** 2026-11-27  
**Status:** Proposed Plan (No Application Code Modified Yet)  
**Inputs:** [`audit-overview.md`](./audit-overview.md), [`project-errors.md`](./project-errors.md), [`incomplete-implementations.md`](./incomplete-implementations.md)
---
## Prioritization Framework
Phases are ordered strictly by **Importance × Urgency**:
1. **Phase 0 — Emergency Security Containment (Immediate / Blocker):** Stop active credential exposure, unauthenticated DB access, and user-facing admin privilege leaks before any public deployment.
2. **Phase 1 — Boot Stability, Fatal Crash Elimination & Green Test Baseline (Urgent / Day 1–2):** Restore application boot, fix every confirmed fatal PHP/enum/type crash, and get PHPUnit + PHPStan green so all subsequent work has regression safety.
3. **Phase 2 — Financial, Ledger, Payment & Webhook Integrity (High Priority / Core Business):** Unify the `LedgerEntry` → `Transaction` projection, fix stuck/orphaned payment states, fix gateway verification/bank resolution bugs, and harden payout/referral math.
4. **Phase 3 — Client–Server Alignment, Web Bundle Sync & Public Flows (High Priority / User Experience):** Fix broken API contracts (`guest-support`, `TransferTopupFunds`, `AccountHistoryController` pagination), clean hardcoded domains/cleartext flags, and rebuild `public/web/` off legacy `gosmm.com`.
5. **Phase 4 — Operational Resilience, Queues, Scheduled Jobs & Admin Hardening (Medium Priority / Production Readiness):** Move synchronous FCM/email off the HTTP thread, fix and schedule `users:purge-deleted`, add FK delete guards and admin login rate limits, and restore caching.
6. **Phase 5 — Completing Stubbed / Partially Implemented Product Features (Planned Feature Delivery):** Complete or cleanly scope-gate incomplete features (Proxy/VPN add-on architecture decision, Email Verification, SMS channel, Automated Payouts, Anti-Fraud verification, Flutter theme/admin UX).
7. **Phase 6 — Comprehensive Automated Test Suite & Documentation Alignment (Continuous / Sign-off Gate):** Expand PHPUnit, Pytest/Schemathesis, and Flutter widget/unit tests across all critical domains and update `ARCHITECTURE.md` + `docs/api-v1.md`.
---
## Phase 0: Emergency Security Containment
**Urgency:** Immediate (P0 — Deployment Blocker)  
**Goal:** Eliminate unauthenticated database access, leaked secrets, production demo-seeding, and admin backdoors in the user client.
### Scope & Actions
1. **Remove Public Database Backdoor (`ERR-01`):**
   - Delete `public/_2KcfSjmX.php` (Adminer 4.8.1).
   - Add Nginx/Apache/Caddy and `.gitignore` rules blocking unknown `.php` scripts in `public/` (only `public/index.php` should execute).
2. **Sanitize Committed Secrets & Rotate Credentials (`ERR-02`):**
   - Scrub `TELEGRAM_BOT_TOKEN`, `FACEBOOK_APP_SECRET`, `FACEBOOK_CLIENT_TOKEN`, `VTPASS_API_KEY`, `VTPASS_PUBLIC_KEY`, `VTPASS_SECRET_KEY`, and `SYSTEM_ACCOUNT_PASSWORD=meandmyson` from `.env.example`.
   - External action required: rotate all exposed tokens in Telegram BotFather, Meta Developer Console, and VTpass dashboard.
3. **Restore Production Guard in `DatabaseSeeder` (`ERR-06`):**
   - Uncomment `if (! app()->environment(['local', 'testing', 'staging']))` in `database/seeders/DatabaseSeeder.php:23-27` and remove the duplicate `PackageAddonSeeder::class` call so production never seeds `admin@tlstore.test`, demo users, or synthetic wallet credits.
   - Require `SYSTEM_ACCOUNT_PASSWORD` to fail fast (or skip demo admin creation) if unset/weak in non-local environments.
4. **Strip Pre-Filled Admin Credentials & Isolate Admin UI in Flutter (`ERR-03`):**
   - Remove hardcoded `'admin@tlstore.test'` and `'meandmyson'` `TextEditingController` defaults across all 11 `review/lib/screens/admin/admin_*_screen.dart` files.
   - Hide/compile-gate the `ADMIN PAGES` section in `review/lib/screens/settings_screen.dart` behind a compile-time `--dart-define=ENABLE_ADMIN_UI=false` flag (default `false` for reviewer builds) or move admin screens to a separate admin entry point.
5. **Rate-Limit All Admin Authentication Endpoints (`ERR-32`):**
   - Apply strict `throttle:auth` (e.g., 5 attempts/minute per IP + email) to `POST /admin/login` (`routes/web.php`) and `POST /api/v1/admin/auth/login` (`routes/api.php`).
### Exit Criteria
- Zero standalone PHP tools in `public/` besides `index.php` (and cleaned `redirect.php`).
- Zero credentials/passwords in `.env.example` or `review/lib/`.
- `db:seed` in `APP_ENV=production` seeds only reference/system configuration.
- Admin login routes enforce rate limiting (HTTP 429 on brute-force).
---
## Phase 1: Boot Stability, Fatal Runtime Crashes & Test Baseline
**Urgency:** Urgent (P0/P1 — Prerequisite for Safe Development)  
**Goal:** Ensure the app boots cleanly on empty/fresh databases, eliminate every confirmed PHP fatal error / `TypeError` / `ValueError`, and make all 13 existing PHPUnit tests + PHPStan pass.
### Scope & Actions
1. **Fix `AppServiceProvider::boot()` & `gs()` Helper (`ERR-04`, `ERR-07`):**
   - Fix `app/Application/Support/Helpers.php:34-40` to query SQL properly (`SystemSetting::query()->where('is_public', true)->pluck('value', 'setting_key')->toArray()`), check `$settings === null` instead of `!$settings` so legitimate empty arrays are cached with a TTL, and guard against missing tables (`Schema::hasTable('system_settings')` wrapped in `try/catch`) during initial migrations and `:memory:` test boot.
   - Replace `\Cache::forget(...)` with imported `Illuminate\Support\Facades\Cache` in `app/Models/SystemSetting.php:32`.
2. **Fix Schema/Factory Default for `users.wallet_currency` (`ERR-08`):**
   - Add a safe default (or `User::creating` fallback from `preferred_currency` / `config('trustlens.default_currency', 'USD')`) via a corrective migration on `users.wallet_currency`, and add `'wallet_currency' => 'NGN'` to `database/factories/UserFactory.php`.
3. **Eliminate Confirmed Fatal Runtime Crashes (`ERR-05`, `ERR-11`, `ERR-12`, `ERR-13`, `ERR-14`, `ERR-15`, `ERR-19`):**
   - `PurchaseVasOrder.php:159` (`ERR-05`): Import `use App\Enums\LedgerEntryStatus;`.
   - `TransactionType.php` & `AccountHistoryController.php` (`ERR-11`, `ERR-33`): Add `case CAMPAIGN_ORDER = 'campaign_order';` to `TransactionType`, include `'campaign_order'` in `AccountHistoryController` category filters (`payment`), fix label typos (`'Salary'`, `'Referral Reward'`), remove dead `default` match arms, and remove `VTU_PURCHASE` from `isGatewayCharge()`.
   - `AutoApproveAdShares.php:158-165` (`ERR-19`): Fix the 5-argument call to `DecideAdShare::handle($admin, $event->fresh(), 'approved', 'Automatically approved after the configured verification delay.', $subscription->plan)`.
   - `AdminConsoleController.php:303,331` (`ERR-13`): Add `use App\Models\ReviewQuestion;` so published questionnaire immutability checks actually execute.
   - `FrontendController.php:185-187` (`ERR-14`): Use `$row['step'] ?? $fallback['step']` (or pass `$configured` in `use (...)`) so customized `about.how_it_works` does not throw `Undefined variable $configured`.
   - `Transaction.php:46-49` (`ERR-15`): Replace undefined `money($this->amount)` in `getSignedAmountAttribute()` with `CurrencyMinorUnits` / formatted decimal helper.
   - `AdShareEvent`, `PaymentIntent`, `ReferralAttribution`, `ReviewSubmission`, `WithdrawalRequest` (`ERR-12`): Convert broken `ledgerEntries(): HasMany` declarations to proper polymorphic `morphMany(LedgerEntry::class, 'source'): MorphMany` matching `TrustLensMorphMap`.
4. **Reconcile Broken PHPUnit Tests (`ERR-09`):**
   - Update `tests/Feature/SubscriptionPackageGroupsTest.php` to assert against the canonical `SubscriptionPlanSeeder` keys (`market-lite`, `market-premium`, `market-gold`, `review-google-play-4m`, `review-samsung-store-4m`, `review-apple-store-4m`) and current Lite share limit (`1000`).
### Exit Criteria
- `phpunit` passes **13/13 tests** out-of-the-box on default SQLite `:memory:`.
- `phpstan analyse --level=5 app config database routes tests` reports **0 errors**.
---
## Phase 2: Financial, Ledger, Payment & Webhook Integrity
**Urgency:** High (P1 — Protects User Balances, Top-Ups & Accounting Accuracy)  
**Goal:** Establish a single, deterministic source of truth for wallet balances and transaction history, and fix payment gateway / payout validation defects.
### Scope & Actions
1. **Unify `LedgerEntryObserver` & `Transaction` Lifecycle (`ERR-10`, `ERR-11`):**
   - Standardize status vocabulary across `transactions` (`pending`, `completed`, `approved`, `rejected`, `failed`, `expired`, `cancelled`) and document exact mapping rules.
   - Ensure `LedgerEntryObserver` is the sole writer for ledger-backed `Transaction` projections, while pre-ledger pending activities (checkout intents, pending review/share submissions) transition cleanly via deterministic `reference` keys:
     - Update `ProcessGatewayWebhook` (`lines 95-102`) so `failed`/`cancelled` webhooks update `Transaction` (`reference = 'payment-intent:{id}'`) from `'pending'` to `'failed'`/`'cancelled'`.
     - Update `TopupController::cancel()` (`line 71`) so cancelling a pending top-up also marks its `Transaction` row as `'cancelled'`/`'expired'`.
     - Ensure `ProcessGatewayWebhook::creditTopup()` and `LedgerEntryObserver` update `'payment-intent:{id}'` consistently even when cross-currency conversion occurs.
     - Ensure `DecideCampaignOrder` rejection creates a clearly labeled refund `Transaction` while updating the original `'campaign-order:{id}'` row to `'rejected'`.
2. **Fix Payment Gateway & Bank Account Resolution Bugs (`ERR-18`, `ERR-23`, `ERR-31`, `ERR-36`):**
   - `VerifyGatewayTopup.php:55` (`ERR-18`): Check `$data['payment_status'] ?? $data['status'] ?? ''` for `bachs` provider (matching `ProcessGatewayWebhook`).
   - `ResolvePayoutAccount.php:41` (`ERR-31`): Map `$bank->country` to its ISO-4217 currency (`Country::default_currency`, e.g., `NG` → `NGN`, `GH` → `GHS`, `KE` → `KES`) when calling Korapay's `/misc/banks/resolve`.
   - `InitializeGatewayTopup.php`, `TopupController.php`, `SubscriptionController.php` (`ERR-23`): Replace `'email:rfc,dns'` with `'email:rfc'` so DNS latency or offline/test domains never block payment checkouts.
   - `config/trustlens.php` & `DemoAdminSeeder.php` (`ERR-36`): Move all `env()` reads strictly into top-level `config/*.php` keys so `php artisan config:cache` is 100% safe.
3. **Align Seeder & Plan Financial Metadata (`ERR-24`):**
   - Reconcile `SubscriptionPlanSeeder.php` `task_referral_percent` (`6` vs `5` in `task_referral_tiers['1']`) and normalize `withdrawal_days` tokens (`['Monthly 28th']` vs `['Monthly']`) against `RequestWithdrawal` schedule-checking logic.
4. **Expire Stale Payment Intents:**
   - Add a lightweight scheduled command (e.g., `payments:expire-intents`) to transition `payment_intents` past `expires_at` (and their `'pending'` `transactions`) to `'expired'`.
### Exit Criteria
- Every top-up, subscription purchase, add-on purchase, campaign order, transfer, review/share reward, referral bonus, VAS order, and withdrawal produces **exactly one** accurate lifecycle in `transactions` and reconciles 1:1 with `wallets.balance_minor`.
- Automated feature tests cover gateway webhooks (success, duplicate replay, failure, cancellation, cross-currency top-up) and payout bank resolution.
---
## Phase 3: Client–Server Contract Alignment & Web Bundle Synchronization
**Urgency:** High (P1/P2 — Fixes Broken User-Facing Flows & Web App)  
**Goal:** Align Flutter API calls with Laravel endpoints, remove hardcoded legacy domains/IPs, enforce mobile transport security, and rebuild `public/web/`.
### Scope & Actions
1. **Fix Guest & Authenticated Contact / Support Flow (`ERR-16`, `ERR-39`):**
   - Backend: Implement `POST /api/v1/guest-support` (rate-limited with `throttle`, stores a guest inquiry or support ticket and sends support notification email, returning `{message: "..."}` HTTP 201).
   - Flutter (`review/lib/screens/frontend/widgets/contact_us.dart` & `auth_api.dart`):
     - Fix `AuthApi.support()` to call `postPublic('guest-support', ...)` instead of `_authRequest(...)` (do not expect an auth token or navigate to `HomeScreen`; show confirmation banner and reset form).
     - Fix `final isUser = widget.token.isNotEmpty && widget.user.isNotEmpty;` instead of hardcoded `false` so signed-in users open `SupportTicketsScreen`.
     - Reuse `widget.api` instead of instantiating an unclosed `AuthApi()`.
2. **Fix Wallet Transfer & Account History Contracts (`ERR-25`, `ERR-30`):**
   - Align `WalletController::transfer()` and `TransferTopupFunds::handle()` allowed target wallet types and error copy.
   - Increase `AccountHistoryController` `per_page` validation from `max:5` to `max:50` (default `15` or `20`), and update `HistoryScreen` (`perPage: 20`) to eliminate 20x sequential HTTP chatter.
3. **Clean Hardcoded Domains, CORS & Mobile Transport Security (`ERR-26`, `ERR-27`, `ERR-28`):**
   - Replace hardcoded `tlstore.org` / `web.tlstore.org` in `public/redirect.php` and `resources/views/landing.blade.php` with `config('app.url')`, `config('trustlens.web_app_url')`, and `config('trustlens.support_email')`.
   - Remove `gptminpro.online` and `10.145.107.216` from `review/android/app/src/main/AndroidManifest.xml`, add `web.tlstore.org`, disable global cleartext traffic (`android:usesCleartextTraffic="false"` and remove global `NSAllowsArbitraryLoads` in `Info.plist`, scoping localhost exceptions strictly to debug builds).
   - Add `/.well-known/assetlinks.json` and `/.well-known/apple-app-site-association` routes/configs driven by env vars for verified deep links.
4. **Cross-Platform Web Build Script & `public/web/` Rebuild (`ERR-17`):**
   - Add a POSIX `review/tool/build_web.sh` alongside `build_web.ps1`.
   - Rebuild `public/web/` from current `review/lib/` (replacing the stale `https://gosmm.com/api/v1/` bundle in `public/web/main.dart.js`).
### Exit Criteria
- `public/web/main.dart.js` contains zero references to `gosmm.com` and targets the configured API origin.
- Guest contact form, signed-in support tickets, wallet transfers, and paginated account history work end-to-end.
---
## Phase 4: Operational Resilience, Async Queues, Scheduled Jobs & Admin Hardening
**Urgency:** Medium (P2 — Production Performance, Compliance & Admin Safety)  
**Goal:** Prevent slow third-party calls from blocking user requests, complete GDPR/account-deletion lifecycle, restore caching, and guard Admin CRUD operations.
### Scope & Actions
1. **Queue Outbound Push & Email Notifications (`ERR-22`):**
   - Dispatch FCM HTTP v1 and SMTP/API email deliveries via queued jobs (`SendNotificationChannelsJob` implementing `ShouldQueue` + `ShouldBeUnique` / retry backoff) from `NotificationObserver`, keeping HTTP response times sub-100ms even if FCM or SMTP times out.
2. **Fix & Schedule `PurgeDeletedUsers` (`ERR-21`):**
   - Update `app/Console/Commands/PurgeDeletedUsers.php` to cleanly delete or anonymize all FK-dependent records (`transactions`, `payment_intents`, `manual_deposit_submissions`, `user_subscriptions`, `user_package_addons`, `review_tasks`, `review_submissions`, `ad_share_tasks`, `vtu_orders`, `user_campaign_orders`, `support_tickets`, `notifications`, `user_devices`, `social_identities`, `user_rank_qualifications`) inside the transaction before `$user->forceDelete()`.
   - Schedule `Schedule::command('users:purge-deleted')->dailyAt('03:15')->withoutOverlapping();` in `routes/console.php`.
3. **Restore Public Endpoint Caching (`ERR-20`, `ERR-37`):**
   - Remove the stray `Cache::forget(self::CACHE_KEY);` on line 19 of `PublicFrontService::getSportlight()`.
   - Implement `Cache::remember(self::CACHE_KEY, ...)` in `FrontendController::index()` and invalidate both keys whenever an admin updates frontend/system settings, FAQs, testimonials, or payment options.
4. **Harden Admin Console Deletions & Console Command Logging (`ERR-29`, `ERR-34`, `ERR-35`):**
   - Add relational existence checks (or `QueryException` handling returning a clean 409/redirect error flash) in `AdminConsoleController::destroy()` before deleting `subscription-groups`, `ads`, `countries`, `regions`, etc.
   - Fix `AutoApproveAppReviews`, `AutoApproveAdShares`, and `PurgeDeletedUsers` to compute `$report['execution_seconds']` *before* writing to `Log::channel(...)`.
   - Clean up `GenerateDailyDemoListings` (`demo:generate-listings`) so template count, idempotency check, and console output match.
### Exit Criteria
- User mutations return without synchronous network calls to Google OAuth/FCM/SMTP.
- `users:purge-deleted` runs cleanly against users with full platform activity and is registered in `schedule:list`.
- Admin deletions of referenced entities return friendly validation messages instead of HTTP 500s.
---
## Phase 5: Completing Stubbed & Partially Implemented Features
**Urgency:** Medium-to-Planned (P2/P3 — Product Completeness Decisions)  
**Goal:** Either finish implementing half-built subsystems or explicitly scope/hide them so users and admins are never misled by non-functional UI.
### Scope & Product Decisions Required
1. **Proxy / VPN Package Add-on (`PackageAddon` + `ProxyConnectionDialog`):**
   - *Option A (Full Implementation):* Integrate a real proxy/VPN provider or WireGuard/SOCKS5 credential allocator per `UserPackageAddon`, record submission IP/country metadata on `SubmitReview`, and wire native tunnel or copyable proxy credentials into Flutter.
   - *Option B (Rename / Reframe as Compliance/Region Access Pass):* If TLStore is not operating VPN infrastructure, remove fake bandwidth/CPU/IP tunnel UI (`ProxyConnectionDialog`) and present the add-on transparently as a *Store Verification / Tester Access Add-on*.
   - Remove unused `review/lib/screens/proxy_prompt_example.dart`.
2. **Email Verification & Account Recovery:**
   - Implement email verification OTP/link on registration (`POST /api/v1/auth/email/verify`, `POST /api/v1/auth/email/resend`) using `NotificationTemplateService`, and optionally require verified email before first withdrawal.
   - Add admin or self-service reactivation flow during the 30-day soft-delete window.
3. **SMS Notification Channel:**
   - Either wire an SMS provider adapter (e.g., Termii / Twilio / Africa's Talking) into `NotificationObserver` + save `notifications.sms.enabled` in `NotificationTemplateAdminController::updateSettings()`, **or** hide SMS toggles/fields in the Admin UI until an SMS vendor is contracted.
4. **Automated Payout Disbursement (Optional Enhancement):**
   - Keep manual admin settlement as default, or add optional Paystack/Flutterwave/Korapay Transfer API disbursement with webhook confirmation and automatic ledger reversal on transfer failure.
5. **Social Follow & Ad Share Verification Hardening:**
   - Require screenshot/handle confirmation or delayed moderation on `ClaimSocialFollowReward` instead of instant unverified cash-equivalent bonus credit.
   - Require non-null proof screenshot or URL reachability/domain check before `AutoApproveAdShares` approves a task.
   - Either implement or remove the unused `AppStoreListingSnapshot` model/table and legacy `location_coverage` dead code.
6. **Flutter Polish & Admin Parity:**
   - Enable `ThemeMode.system` in `AppThemeController` + `SettingsScreen` (or remove the disabled button) and remove `"More color options coming soon"` placeholder until implemented.
   - If Flutter Admin UI is retained as a separate admin build: introduce a shared `AdminAuthSession` (so admins do not re-login on each of the 15 screens) and add `AdminSupportTicketsScreen` to consume the already-written `AuthApi.adminSupportTickets*` methods.
### Exit Criteria
- Zero UI screens display fake/mocked network states, `"Unavailable"` buttons, `"coming soon"` placeholders, or settings toggles that do nothing on the backend.
---
## Phase 6: Comprehensive Test Coverage, CI & Documentation Sync
**Urgency:** Ongoing / Release Gate (P3 — Long-Term Maintainability)  
**Goal:** Prevent regressions across financial/security flows and bring repository documentation into 100% alignment with the codebase.
### Scope & Actions
1. **Backend Feature & Concurrency Tests (`tests/Feature/`):**
   - **Identity & Security:** Register, login, social auth mocks, password reset, TOTP 2FA setup/challenge/recovery, withdrawal PIN, soft-delete & `users:purge-deleted`.
   - **Payments & Webhooks:** Paystack/Flutterwave/Korapay/Bachs HMAC signature validation, idempotent webhook replay, failed/cancelled checkout cleanup, manual deposit moderation.
   - **Wallets & Withdrawals:** `LedgerEntryObserver` race/locking tests, `TransferTopupFunds`, `RequestWithdrawal` schedule/minimum checks, `SettleWithdrawal` reserve-to-posted and reserve-to-reversed balance restoration, PDF receipt generation.
   - **VAS / VTpass:** `PurchaseVasOrder`, `SettleVasOrder`, `ReconcileVasOrder`, `VtpassWebhookController`.
   - **Marketplace, Shares, Campaigns, Referrals & Rankings:** `ClaimReviewTask`, `SubmitReview`, `AutoApproveAppReviews` (attention checks + add-on requirement), `AutoApproveAdShares`, `CreateCampaignOrder` + `DecideCampaignOrder`, 3-tier `CreditReferralReward`, `CheckRankEligibility` + `RewardDueRanks`.
   - **Support & Admin RBAC:** User/admin support ticket lifecycle and role matrix tests across `super_admin`, `admin`, `content_admin`, `moderator`, and `finance`.
2. **Flutter Unit & Widget Tests (`review/test/`):**
   - Add tests for `AuthApi` error/2FA/cache handling, `money.dart` minor-unit conversions, `ReferralLink` deep-link parsing, login/register/2FA screens, and history/wallet screens.
3. **Architecture & API Documentation Refresh:**
   - Update root `ARCHITECTURE.md`, `architecture-model.md`, `software-architecture-model.md`, and `docs/api-v1.md` to reflect Laravel 13, the 6-wallet model, Subscription Groups A/B/C, Package Add-ons, VAS, User Campaigns, Rankings, Support Tickets, and 2FA.
### Exit Criteria
- Automated CI pipeline runs `phpstan`, `pint --test`, `phpunit` (>80% coverage of `app/Application/Actions` and controllers), and `flutter analyze && flutter test` on every pull request.
