# TLStore / TrustLens — Incomplete, Stubbed & Partially Implemented Areas
**Audit Date:** 2026-11-27  
**Scope:** End-to-end review of Laravel backend (`app/`, `config/`, `database/`, `routes/`, `resources/`), Flutter client (`review/`, `public/web/`), Admin interfaces, external integrations, and automated testing.
---
## Summary Matrix
| Area | Status | Key Gaps |
|---|---|---|
| **1. Proxy / VPN Package Add-on** | **UI Mock Only** | Billing & entitlement checks work, but `ProxyConnectionDialog` explicitly states *"No network tunnel is started"*; no VPN/proxy backend, WireGuard/SOCKS5 provisioning, or real IP routing exists. |
| **2. SMS Notification Channel** | **Schema + UI Only (No Sender)** | `notification_templates` has `sms_body` and `sms_status`, Admin UI edits them, and `notifications.sms.enabled` setting is queried, **but zero SMS provider/dispatcher code exists**. |
| **3. Guest Contact / Public Support** | **Client Form Only (No Backend)** | Flutter `ContactUs` submits to `POST /api/v1/guest-support`, which is completely unimplemented on the server. |
| **4. Email Verification Flow** | **Column Only (Unimplemented)** | `users.email_verified_at` exists and OAuth sets it, **but password registration has no verification email, OTP, or `verified` middleware**. |
| **5. Automated Payout Disbursement** | **Manual Admin Only** | Withdrawals only record manual admin settlement (`SettleWithdrawal`); no Paystack/Flutterwave/Korapay Transfer API integration exists despite bank-account resolution being wired. |
| **6. Ad Share & Social Follow Verification** | **Honor-System / Manual / Timed** | `ClaimSocialFollowReward` credits instant bonus money on button tap without API/OAuth proof; `AutoApproveAdShares` approves shares purely on a timer without checking the submitted URL. |
| **7. App Store Snapshot & Store Verification** | **Unused Model / Table** | `AppStoreListingSnapshot` model and migration exist, `App::storeListingSnapshots()` relation exists, **but nothing in the codebase ever writes or reads store snapshots**. |
| **8. Flutter Theme & Appearance** | **Partially Stubbed** | `System` theme button is hardcoded `enabled: false` (`"Unavailable"`); `AppThemeController` only supports binary `dark`/`light`; Accent color says `"More color options coming soon"`. |
| **9. Admin Session & RBAC in Flutter** | **Fragmented Prototype** | 15 Flutter admin screens each maintain an isolated in-memory `_token` (requiring re-login on every screen), lack a shared Admin session/router, and missing Flutter Support Ticket admin screen despite `AuthApi.adminSupportTickets` methods existing. |
| **10. Test Coverage** | **<10% Domain Coverage** | Only 5 backend test files (13 tests, currently failing) and 1 Flutter widget test file (3 widget/unit assertions); zero tests for auth, 2FA, payments, webhooks, withdrawals, VAS, campaigns, referrals, rankings, support, or admin. |
---
## Detailed Breakdown of Unimplemented & Partial Features
### 1. Proxy / VPN Package Add-on (`PackageAddon` + `ProxyConnectionDialog`)
- **Backend (`app/Models/PackageAddon.php`, `ActivatePackageAddon.php`, `AutoApproveAppReviews.php:189-214`):**
  - Users on review plans with `addons = true` are required to purchase a monthly `PackageAddon` ("Proxy") or their submitted app reviews are auto-rejected (`package_addon_required`).
  - However, the backend only stores a static JSON `features` blob (e.g., fake/static `location`, `ip_addresses`, `download_speed`, `upload_speed`, `cpu_usage`) on `package_addons`. There is **no proxy server provisioning, no SOCKS5/HTTP/WireGuard credential generation, and no IP verification** when a user claims or submits a review task.
- **Flutter Client (`review/lib/widgets/proxy_connection_dialog.dart:9-10, 224-226, 487-490`):**
  - Dartdoc explicitly notes: *"This page presents the connection details made available by the add-on API. It is a UI preview only; it does not establish a VPN/proxy tunnel."*
  - Displays a fake incrementing `00:00:00` timer (`Timer.periodic`), hardcoded fallback metrics (`12.4 MB/s` download, `3.1 MB/s` upload, `14%` CPU), and a footer reading `"CONNECTION PREVIEW · No network tunnel is started"`.
  - Also contains an unreferenced developer demo screen `review/lib/screens/proxy_prompt_example.dart` (`ProxyPromptExamplePage`).
---
### 2. SMS Notification Delivery Channel
- **Locations:**
  - `database/migrations/*notification_templates*` (`sms_body`, `sms_status` columns)
  - `app/Features/Admin/Http/Controllers/Web/NotificationTemplateAdminController.php:24,57,60`
  - `app/Observers/NotificationObserver.php:17-32`
  - `app/Features/Notifications/Services/NotificationTemplateService.php`
- **What exists:** Database columns (`sms_body`, `sms_status`), admin form inputs in `NotificationTemplateAdminController::update()`, and `NotificationTemplateAdminController::index()` querying `'notifications.sms.enabled'`.
- **What is missing:**
  - `NotificationTemplateAdminController::updateSettings()` (`lines 73-95`) **does not even save `notifications.sms.enabled`**!
  - `NotificationObserver::created()` dispatches FCM push and HTML email, **never SMS**.
  - No SMS gateway service (Twilio, Termii, Africa's Talking, Vonage, etc.) or `channel = 'sms'` delivery handler exists anywhere in `app/`.
---
### 3. Guest Support / Public Contact Form Submission
- **Locations:**
  - `review/lib/screens/frontend/widgets/contact_us.dart:59-84,113`
  - `review/lib/services/auth_api.dart:1241-1253`
  - `routes/api.php`
- **What exists:** A full UI form (`Your Name`, `Email Address`, `Subject`, `Your Message`) in the Flutter landing page's `ContactUs` widget.
- **What is missing:**
  - Backend has **no `POST /api/v1/guest-support` route, controller, migration, rate limiter, or email notification**.
  - Client `AuthApi.support()` was copy-pasted from `login()` (`return _authRequest('guest-support', ...)`), expecting an authenticated `User` + Sanctum token back and replacing the navigation stack with `HomeScreen`!
  - Logged-in user ticket routing in `ContactUs` is disabled by `final isUser = false;` (`contact_us.dart:113`).
---
### 4. Email Verification & Account Lifecycle Gaps
- **Email Verification:**
  - `users.email_verified_at` column exists (`2026_11_10_000006_create_users_table.php`) and `UserFactory::unverified()` exists, **but `RegisterUser` leaves `email_verified_at` as `null`**, `User` does not implement `MustVerifyEmail`, and there are **zero endpoints** to send or verify an email OTP/link.
- **Email Address Change:**
  - `AuthController::updateProfile()` (`AuthController.php:185-194`) allows updating `full_name`, `username`, `phone`, and `preferred_currency`, **but provides no flow to change a user's email address** (nor re-verify it).
- **30-Day Account Soft-Delete Recovery & Scheduled Purge:**
  - Soft-deleted users receive `account_state: 'deleted'` on login with instructions to contact support within 30 days, **but there is no self-service account restoration flow**, and `users:purge-deleted` is **not registered in `routes/console.php`** (and fails on uncleaned FK tables; see `ERR-21`).
---
### 5. Automated Withdrawal Payouts & Manual Payment Placeholders
- **Manual-Only Withdrawal Settlement (`SettleWithdrawal.php`):**
  - Although `ResolvePayoutAccount` integrates with Paystack, Flutterwave, Korapay, and Bachs to verify bank account names, **actual payouts are 100% manual**. Admins must transfer money outside the platform and paste a `provider_reference` into `SettleWithdrawal`. No transfer initiation API, payout webhook, or automated retry/reversal flow is implemented.
- **Placeholder Manual Deposit Accounts (`database/seeders/PaymentOptionSeeder.php`):**
  - Manual payment options are seeded with placeholder wallet/bank details (`PaymentOptionSeeder.php:9: Seeds the manual payment options with placeholder details`).
- **Missing Expired Checkout Cleanup Command:**
  - `InitializeGatewayTopup` sets `expires_at = now()->addMinutes(30)` on `payment_intents` and creates `pending` `transactions`, **but no scheduled console command expires stale `payment_intents` or marks abandoned `transactions` as `expired`/`cancelled`**.
---
### 6. Task Verification & Anti-Fraud Gaps
- **Instant Unverified Social Follow Rewards (`ClaimSocialFollowReward.php`):**
  - `POST /api/v1/me/social-handles/{socialHandle}/follow` immediately creates a `completed` `UserSocialFollow` record and credits real `Bonus` wallet funds without any proof upload, OAuth check, or moderation step.
- **Blind Timer Auto-Approval on Ad Shares (`AutoApproveAdShares.php`):**
  - Aside from its fatal `TypeError` (`ERR-19`), `AutoApproveAdShares` approves any pending `AdShareEvent` after `ads.auto_approve_shares.delay_minutes` without fetching/checking `external_reference` (`post_url`) or verifying that a screenshot was uploaded (`screenshot_path` is nullable).
- **Unused `AppStoreListingSnapshot` Subsystem:**
  - `app/Models/AppStoreListingSnapshot.php` and its migration exist to store scraped/verified Google Play / App Store metadata snapshots, **but zero services, jobs, or controllers ever create or read `AppStoreListingSnapshot` records**.
- **Legacy `location_coverage` Dead Code:**
  - `SubscriptionProductType` enum, `Region`, `Country`, `user_region_entitlements` migrations, and admin modules for `regions` / `region-countries` still exist from an earlier architecture, even though `PurchaseSubscription` and `ActivateSubscription` now hard-block `location_coverage` (`"Location coverage packages are no longer offered."`).
---
### 7. Frontend Caching & Web/Mobile Platform Gaps
- **`FrontendController` Caching Not Implemented (`FrontendController.php:19-20`):**
  - Declares `CACHE_KEY = 'api:frontend:v1'` and `CACHE_TTL = 60`, but never uses `Cache::remember()` (and `AdminSettingsController` has no cache invalidation hook for it).
- **Mobile App Store Distribution Links Missing (`resources/views/landing.blade.php:46-56`):**
  - Both Google Play and Apple App Store badges default to disabled `"coming soon"` states unless `APP_ANDROID_STORE_URL` / `APP_IOS_STORE_URL` are configured; `public/.well-known/assetlinks.json` and `apple-app-site-association` are not implemented.
- **Flutter Appearance & Theme Settings (`review/lib/screens/settings_screen.dart:329-380`, `app_theme_controller.dart`):**
  - `System` theme option is rendered with `enabled: false, onTap: () {}` and labeled `"Unavailable"`; `AppThemeController` only persists `'dark'` or `'light'` (ignoring `ThemeMode.system`).
  - Accent color picker is a non-interactive row displaying `"More color options coming soon"`.
- **Flutter Admin Architecture & Missing Admin Support Screen:**
  - `AuthApi` implements `adminSupportTickets()`, `adminSupportTicket()`, `adminReplySupportTicket()`, and `updateAdminSupportTicket()` (`auth_api.dart:1285-1313`), **but no `AdminSupportTicketsScreen` exists in `review/lib/screens/admin/`** (unlike the other 15 admin screens).
  - Conversely, the Blade Web Admin Console (`/admin`) lacks dedicated rich editors for several workflows that were custom-built in Flutter (`AdminPaymentGatewaysScreen`, `AdminFrontendContentScreen`, `AdminRewardSettingsScreen`), forcing admins to edit raw JSON in `system-settings` on the web console.
  - All 15 Flutter admin screens store `_token` in local `State` rather than a shared `AdminSession` service, forcing an admin to log in again every time they navigate between admin screens.
- **Linux/macOS/Windows Build Tooling for Flutter Web:**
  - Only a Windows PowerShell build script (`review/tool/build_web.ps1`) is provided; there is no POSIX shell script (`review/tool/build_web.sh`) or Makefile/Composer script to rebuild `public/web/` and `firebase-messaging-sw.js` on Linux CI/CD servers.
---
### 8. Automated Test Coverage Gaps
#### Backend (`tests/` — 5 files, 13 tests total)
- **Existing tests (all currently broken out-of-the-box; 9/13 broken even with migrated DB):**
  - `tests/Unit/SubscriptionPlanModelTest.php` (2 tests)
  - `tests/Feature/AccountHistoryControllerTest.php` (1 test)
  - `tests/Feature/AppListingCatalogTest.php` (1 test)
  - `tests/Feature/GroupADailyShareRevenueTest.php` (4 tests)
  - `tests/Feature/SubscriptionPackageGroupsTest.php` (5 tests)
- **Completely untested backend domains (0 tests):**
  - Authentication: Registration, login, social login (Google/Facebook/Telegram), password reset, TOTP 2FA setup/challenge/disable, security PIN, soft-delete & `PurgeDeletedUsers`.
  - Payments & Webhooks: `InitializeGatewayTopup`, `VerifyGatewayTopup`, `ProcessGatewayWebhook` (HMAC signature checks for Paystack, Flutterwave, Korapay, Bachs), `SubmitManualDeposit`, `DecideManualDeposit`.
  - Wallets & Withdrawals: `LedgerEntryObserver` concurrency/locking, `TransferTopupFunds`, `RequestWithdrawal` (schedule days, min withdrawal, PIN), `SettleWithdrawal` (reserve reversal vs post), PDF receipt rendering, `ResolvePayoutAccount`.
  - VAS / VTpass: `VasCatalogService`, `PurchaseVasOrder` (which currently has a fatal missing-import crash!), `SettleVasOrder`, `ReconcileVasOrder`, `VtpassWebhookController`.
  - Reviews & Ad Shares: `ClaimReviewTask`, `SubmitReview`, attention-check auto-rejection in `AutoApproveAppReviews`, `AutoApproveAdShares` (which currently has a fatal `TypeError`!), `ClaimAdShareTask`, `SubmitAdShareEvidence`.
  - User Campaigns: `CreateCampaignOrder`, `DecideCampaignOrder` (which currently triggers a `TransactionType` enum `ValueError`!).
  - Referrals, Social Follow & Rankings: `CreditReferralReward` (3-tier math & loop prevention), `ClaimSocialFollowReward`, `CheckRankEligibility`, `RewardDueRanks`.
  - Support Tickets, Notifications (FCM & HTML sanitizer), Web Admin Console RBAC, and Admin API endpoints.
#### Flutter Client (`review/test/` — 1 file, 3 tests total)
- Only `review/test/widget_test.dart` exists (testing `TaskCompletionPage` and `StoreFilterChips`).
- Zero unit or widget tests for `AuthApi`, currency conversion (`money.dart`), deep-link parsing (`ReferralLink`), auth/2FA screens, task questionnaires, wallet/history/withdrawal flows, VAS flows, or support tickets.
