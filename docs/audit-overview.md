# TLStore / TrustLens — Project Audit & System Overview
**Audit Date:** 2026-11-27  
**Repository:** `fldsmine/tlstore` (`/home/user/tlstore`)  
**Audited Commit:** `749bb66305aa90b1decea375846623daa` (`email template added`)
---
## 1. Executive Summary
**TLStore ("TrustLens")** is a multi-tenant-style task participation, sponsored sharing, value-added services (VAS), and rewards platform built as a **modular monolith Laravel 13 backend** paired with a **Flutter (`review/`) mobile & web client** and a **Blade server-rendered Admin Console**.
### Audit Headline Findings
| Dimension | Status | Summary |
|---|---|---|
| **Security Posture** | **Critical Risk** | Unauthenticated Adminer 4.8.1 database manager exposed at `public/_2KcfSjmX.php`; live/sandbox third-party credentials committed in `.env.example`; 15 admin screens compiled inside the user-facing Flutter client (`SettingsScreen`), 11 of which pre-fill plaintext super-admin credentials (`admin@tlstore.test` / `meandmyson`). |
| **Application Boot & Test Suite** | **Broken (0/13 default, 4/13 after DB workaround)** | `AppServiceProvider::boot()` calls `gs()` unconditionally before migrations exist, crashing all 13 PHPUnit tests on SQLite `:memory:` (`no such table: system_settings`). Even with a pre-migrated SQLite copy, **9 of 13 tests still fail** due to `users.wallet_currency` `NOT NULL` without a default (`UserFactory` omits it) and renamed subscription plan keys (`demo-*` vs `market-*`/`review-*`). |
| **Core Runtime Paths** | **Multiple Fatal Errors** | Every VAS purchase crashes (`LedgerEntryStatus` unimported in `PurchaseVasOrder.php:159`); `gs()` helper is logically inverted and always returns `[]`; `AutoApproveAdShares` passes wrong arguments to `DecideAdShare::handle()`; `FrontendController` throws `Undefined variable $configured` when `about.how_it_works` is set; 5 Eloquent models have broken `ledgerEntries(): HasMany` return types; `AdminConsoleController` misses `ReviewQuestion` import; `Transaction::getSignedAmountAttribute()` calls undefined `money()`. |
| **Financial & Accounting Integrity** | **Inconsistent** | Dual/overlapping `Transaction` projection (`LedgerEntryObserver` vs explicit `Transaction::updateOrCreate` in controllers/actions) produces divergent statuses (`posted` vs `completed` vs `approved`) and duplicate rows on wallet top-ups (`payment-intent:{id}` stays `pending` while `ledger:{id}` is created as `posted`) and rejected campaign refunds. |
| **Client/Web Deployment Sync** | **Severely Out of Sync** | Pre-built `public/web/main.dart.js` points to legacy domain `https://gosmm.com/api/v1/` instead of `tlstore.org` or relative `/api/v1/` and lacks newer features (`SupportTicketsScreen`, `guest-support`, admin screens). Guest contact form calls nonexistent `POST /api/v1/guest-support`. Proxy add-on "connection" screen (`ProxyConnectionDialog`) is an explicit UI-only mock (`No network tunnel is started`). |
---
## 2. Audit Documentation Index
All audit deliverables are stored in `/home/user/tlstore/docs/`:
1. **[`audit-overview.md`](./audit-overview.md)** *(this document)* — System architecture, bounded contexts, data/financial model, deployment topology, and architecture drift from root `.md` docs.
2. **[`project-errors.md`](./project-errors.md)** — Complete catalog of confirmed security vulnerabilities, fatal runtime errors, financial/ledger accounting bugs, broken PHPUnit tests, client-server mismatches, and configuration defects with exact `file:line` references and root-cause analysis.
3. **[`incomplete-implementations.md`](./incomplete-implementations.md)** — Detailed inventory of stubbed, partial, mocked, unscheduled, or unimplemented features across the Laravel backend, Admin Console, Flutter mobile/web app, infrastructure, and automated test suite.
4. **[`workstation-tooling.md`](./workstation-tooling.md)** — Inventory and usage guide for all compilers, runtimes, linters, static analyzers, API fuzzers, load testers, and database tools installed on this workstation to aid implementation and testing.
Existing feature reference documents in `docs/`:
- [`api-v1.md`](./api-v1.md) — API v1 endpoint overview (partially outdated; see §6 below).
- [`mail-and-account-recovery.md`](./mail-and-account-recovery.md) — Password reset & notification template design.
- [`push-notifications.md`](./push-notifications.md) — FCM HTTP v1 push delivery notes.
- [`subscription-package-groups-proposal.md`](./subscription-package-groups-proposal.md) — Package Groups A/B/C business specification.
---
## 3. System Architecture & Technology Stack
### 3.1 Backend (`/home/user/tlstore`)
- **Framework:** Laravel `13.43.0` (PHP `^8.3`, running under FrankenPHP PHP `8.5.12` / PHP CLI `8.4.21` on workstation)
- **Authentication:**
  - User & Mobile Admin API: **Laravel Sanctum `^4.0`** (`Bearer` personal access tokens; abilities `['*']`, `['two_factor_challenge']`, `['*', 'two_factor_verified']`)
  - Browser Admin Console (`/admin/*`): Session-based guard `admin` (`Eloquent` provider over `App\Models\Admin`)
- **PDF Generation:** `barryvdh/laravel-dompdf` (`^3.1`) for withdrawal receipts (`resources/views/pdf/withdrawal-receipt.blade.php`)
- **Frontend Asset Pipeline (Admin/Landing):** Vite `7.3.8` + TailwindCSS `4.0.7` (`resources/js/app.js`, `resources/css/app.css` -> `public/build/`)
- **Database:** SQLite (`database/database.sqlite` in local/dev) or MySQL/MariaDB in production; **77 migrations**, **27 seeders**, **52 Eloquent models**, **16 enums**.
### 3.2 Client Application (`review/`)
- **Framework:** Flutter (`pubspec.yaml` SDK `^3.13.3`), **117 Dart files** in `review/lib/`
- **Targets:** Android (`pynith.tlstore.review`), iOS, and Flutter Web (compiled output committed to `public/web/`)
- **State & Networking:** Custom `AuthApi` HTTP wrapper (`http` + `flutter_secure_storage` caching), `AppLinks` deep linking (`tlstore://`, `trustlens://`, HTTPS app links), Firebase Core/Messaging + `flutter_local_notifications`, Google/Facebook/Telegram social auth.
### 3.3 Bounded Contexts (`app/Features/` & `app/Application/Actions/`)
| Bounded Context | Key Controllers / Actions | Responsibility |
|---|---|---|
| **Identity & Security** | `AuthController`, `TwoFactorChallengeController`, `AdminAuthController`, `RegisterUser`, `SocialLogin`, `TotpAuthenticator`, `SecurityPinHasher` | Registration, email/username login, Google/Facebook/Telegram OAuth, TOTP 2FA + recovery codes, 4-digit withdrawal PIN, soft-delete + 30-day purge. |
| **Subscriptions & Add-ons** | `SubscriptionController`, `PackageAddonController`, `PurchaseSubscription`, `ActivateSubscription`, `PurchasePackageAddon`, `ActivatePackageAddon` | 3 Subscription Groups (`group_a` Ad Share, `group_b` Play/Galaxy Review, `group_c` Apple App Store Review), wallet or gateway checkout, tester qualification questionnaire, monthly "Proxy" package add-ons. |
| **Marketplace & Reviews** | `ListingController`, `ReviewTaskController`, `ClaimReviewTask`, `SubmitReview`, `DecideReviewSubmission`, `AutoApproveAppReviews` | Store-scoped app listings, 24h task reservation, versioned questionnaires with attention checks, manual/auto review approval, app reporting, AI listing generator (`listings:generate`). |
| **Sponsored Ad Shares & User Campaigns** | `AdShareController`, `UserCampaignOrderController`, `ClaimAdShareTask`, `SubmitAdShareEvidence`, `DecideAdShare`, `AutoApproveAdShares`, `CreateCampaignOrder`, `DecideCampaignOrder` | Social platform share tasks, proof URL/screenshot submission, Group A daily revenue cap (`DailyShareRevenue`), user-funded social growth campaigns converted into `Ad` records upon admin approval. |
| **Wallets, Ledger & Withdrawals** | `WalletController`, `AccountHistoryController`, `PayoutProfileController`, `CreditUserWallet`, `TransferTopupFunds`, `RequestWithdrawal`, `SettleWithdrawal`, `ResolvePayoutAccount` | 6 account wallet types (`review_group_b`, `review_group_c`, `share`, `referral`, `bonus`, `topup_fund`), immutable `ledger_entries` + `LedgerEntryObserver` balance sync, payout bank account resolution, withdrawal schedules/caps, PDF receipts. |
| **Payments & Top-ups** | `TopupController`, `InitializeGatewayTopup`, `VerifyGatewayTopup`, `ProcessGatewayWebhook`, `SubmitManualDeposit`, `DecideManualDeposit` | Multi-gateway checkout & webhooks (`korapay`, `paystack`, `flutterwave`, `bachs`) + manual bank/crypto proof uploads for wallet top-ups, subscriptions, and add-ons. |
| **VAS (VTpass)** | `VasController`, `VtpassWebhookController`, `VasCatalogService`, `VtpassClient`, `PurchaseVasOrder`, `SettleVasOrder`, `ReconcileVasOrder` | NGN airtime, data bundles, cable TV, electricity tokens, and education pins via VTpass API with reserved-debit settlement and async requery job. |
| **Referrals, Social Follow & Rankings** | `ReferralController`, `SocialFollowController`, `UserRankingController`, `CreditReferralReward`, `ClaimSocialFollowReward`, `CheckRankEligibility`, `RewardDueRanks` | 3-tier referral commissions on subscriptions and tasks, one-time social handle follow bonuses, and 5-level monthly USD rank rewards. |
| **Notifications & Support** | `NotificationController`, `SupportTicketController`, `NotificationTemplateService`, `FirebaseCloudMessaging`, `NotificationObserver` | In-app notifications, category preferences, FCM HTTP v1 push notifications, sanitized HTML email templates, user/admin support tickets. |
| **Admin Console & API** | `AdminConsoleController`, `AdminSupportTicketController`, `NotificationTemplateAdminController`, `app/Features/Admin/Http/Controllers/Api/V1/*` | Role-gated (`super_admin`, `admin`, `content_admin`, `moderator`, `finance`) web CRUD over `config/admin.php` modules + JSON Admin API consumed by Flutter admin screens. |
---
## 4. Financial & Wallet Architecture
1. **Multi-Wallet Model (`App\Enums\WalletType`):**
   - `topup_fund`: Funded via payment gateways (`korapay`, `paystack`, `flutterwave`, `bachs`) or manual deposit approval. Used to buy subscriptions, package add-ons, VAS orders, user campaigns, or transfer into earning wallets (`TransferTopupFunds`). Cannot be withdrawn directly.
   - `share`: Credited by Group A ad-share tasks.
   - `review_group_b`: Credited by Group B (Google Play / Samsung Galaxy Store) app review tasks.
   - `review_group_c`: Credited by Group C (Apple App Store) app review tasks.
   - `referral`: Credited by Level 1–3 subscription and task referral rewards.
   - `bonus`: Credited by plan welcome bonuses, social follow rewards, and monthly user rank rewards (withdrawals disabled by default via `withdrawal.wallet.bonus`).
   - Legacy `review`: Still present in enum/validation for backward compatibility.
2. **Double-Layer Accounting (`ledger_entries` + `transactions`):**
   - `LedgerEntry` is the source-of-truth ledger in minor units (`amount_minor`).
   - `LedgerEntryObserver` (`created` / `updated`) atomically adjusts `wallets.balance_minor` inside `DB::transaction(... lockForUpdate())` (debit `reserved` and `posted` reduce balance; credit `posted` increases balance) and projects rows into the `transactions` table for `/api/v1/me/account-history`.
   - **Architectural flaw:** Several actions (`InitializeGatewayTopup`, `SubmitReview`, `SubmitAdShareEvidence`, `DecideManualDeposit`, `DecideReviewSubmission`, `DecideAdShare`, `DecideCampaignOrder`, `PurchasePackageAddon`, `CreditReferralReward`, `ProcessGatewayWebhook`) *also* write directly to `transactions` using different reference keys and status strings, causing duplicate history entries and stuck `pending` rows (detailed in [`project-errors.md`](./project-errors.md)).
---
## 5. Scheduled Background Tasks (`routes/console.php`)
| Command | Schedule | Notes |
|---|---|---|
| `tasks:expire-reservations` | `hourly()` | Releases expired 24h `ReviewTask` and `AdShareTask` reservations. |
| `app:auto-approve-app-reviews` | `everyFiveMinutes()` | Auto-approves/rejects pending app reviews after `app.auto_approve_reviews.delay_minutes`. |
| `ads:auto-approve-shares` | `everyFiveMinutes()` | Auto-approves pending ad shares — **currently broken due to `DecideAdShare::handle()` signature mismatch**. |
| `vas:reconcile-pending` | `everyFiveMinutes()` | Dispatches `ReconcileVasOrderJob` for pending VTpass orders older than 2 minutes. |
| `rankings:check-eligibility` | `hourly()` | Evaluates reviewer metrics against `user_rank_levels`. |
| `rankings:reward-due` | `hourly()` | Pays due monthly rank rewards into `bonus` wallet. |
| `users:purge-deleted` | **NOT SCHEDULED** | Implemented in `PurgeDeletedUsers.php`, promised as automatic 30-day permanent deletion in UI/API, but missing from `routes/console.php`. |
---
## 6. Architecture Documentation Drift
The three root architecture documents (`ARCHITECTURE.md`, `architecture-model.md`, `software-architecture-model.md`) and `docs/api-v1.md` have drifted substantially from the actual codebase:
1. **Laravel & Flutter Versions:** `ARCHITECTURE.md` states "Laravel 11+ (PHP 8.3+)" whereas `composer.json` runs **Laravel 13 (`^13.0`)**.
2. **Product Types & Regional Access:** Root architecture docs describe `location_coverage` regional add-on subscriptions (`user_region_entitlements`). In reality, `PurchaseSubscription` and `ActivateSubscription` throw an error if `product_type !== 'marketplace_access'` (`"Location coverage packages are no longer offered."`), replaced by Subscription Groups (`group_a`, `group_b`, `group_c`) and `PackageAddon` ("Proxy" add-ons).
3. **Wallet Types:** Root docs describe 4 wallets (`review`, `share`, `referral`, `bonus`). The codebase now provisions **6 wallets** (`review_group_b`, `review_group_c`, `share`, `referral`, `bonus`, `topup_fund`).
4. **Undocumented Subsystems in Root Docs:** VAS/VTpass (`vtu_orders`), User Campaign Orders (`user_campaign_orders`), User Rankings (`user_rank_levels`, `user_rank_qualifications`), Support Tickets (`support_tickets`), Package Add-ons (`package_addons`), AI Listing Generation (`config/ai-listings.php`), TOTP 2FA & Withdrawal PIN, and `bachs` payment gateway are absent from `ARCHITECTURE.md` and `docs/api-v1.md`.
5. **Web Routing Topology:** `routes/web.php` serves `landing.blade.php` at `/` while `public/web/` contains a separate Flutter web SPA (with its own `LandingScreen`) served via `/app` or `web.*` subdomain — a split architecture not explained in root docs.
