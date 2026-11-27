# TLStore / TrustLens — Project Errors, Vulnerabilities & Bugs
**Audit Date:** 2026-11-27  
**Verification Method:** PHPUnit 12.5.45 execution, PHPStan Level 5 static analysis, AST/pattern inspection, database schema verification, and full manual code review across Laravel (`app/`, `config/`, `database/`, `routes/`, `resources/`, `public/`, `tests/`) and Flutter (`review/`, `public/web/`).
---
## Severity Summary
| Severity | Count | Description |
|---|---|---|
| **CRITICAL** | 6 | Public database admin backdoor, leaked secrets/credentials, admin UI + password shipped in user client, boot crash breaking tests/fresh installs, fatal crash on every VAS purchase. |
| **HIGH** | 12 | Broken PHPUnit suite (9/13 fail even after DB fix), inverted `gs()` helper, broken `ledgerEntries()` relations, duplicate/stuck `Transaction` accounting rows, unimported classes, non-existent `guest-support` API, stale `gosmm.com` web bundle. |
| **MEDIUM** | 14 | Wrong argument order in `AutoApproveAdShares`, broken `PublicFrontService` cache, synchronous FCM/SMTP inside HTTP lifecycle, hardcoded domains, DNS email validation timeouts, SQLite FK delete gaps, cleartext mobile traffic. |
| **LOW** | 7 | Enum typos/dead branches, log timing bugs, `env()` inside config arrays, misleading console output, unused constants. |
---
## 1. Critical Security Vulnerabilities & Fatal Crashes
### ERR-01 [CRITICAL] — Unauthenticated Adminer 4.8.1 Exposed in Public Web Root
- **Location:** `public/_2KcfSjmX.php` (317,072 bytes, committed in `749bb66`)
- **Description:** A complete single-file **Adminer 4.8.1** database management tool is stored directly inside `public/`, making it reachable over HTTP (`https://<host>/_2KcfSjmX.php`) without passing through Laravel middleware, IP allowlists, or admin authentication.
- **Impact:** Exposes a public database login surface (supporting SQLite, MySQL, PostgreSQL, etc.), known Adminer 4.8.1 SSRF/file-read attack vectors, and brute-force access against local database sockets or SQLite paths.
- **Remediation:** Delete `public/_2KcfSjmX.php` immediately and purge or block `adminer*.php` / `_*.php` in web server configuration and `.gitignore`.
### ERR-02 [CRITICAL] — Live / Sandbox Third-Party Secrets & Plaintext Admin Password Committed in `.env.example`
- **Location:** `.env.example:78-132`, `review/ios/Runner/Info.plist:104`
- **Description:** `.env.example` commits real-looking credentials in version control:
  - `TELEGRAM_BOT_TOKEN=8728344736:AAHLx2f5R2k_fT8f2B0jB2fO7S6o_yN4p0s` (`.env.example:78`)
  - `FACEBOOK_APP_SECRET=6f4b3e2a1d9c8b7a6f5e4d3c2b1a0f9e` & `FACEBOOK_CLIENT_TOKEN=c684bb03e4fe19d845850833e5497836` (`.env.example:81-82`, also in `review/ios/Runner/Info.plist:104`)
  - `VTPASS_API_KEY`, `VTPASS_PUBLIC_KEY`, `VTPASS_SECRET_KEY` (`.env.example:125-127`)
  - `SYSTEM_ACCOUNT_PASSWORD=meandmyson` (`.env.example:132`)
- **Impact:** Anyone with repository read access obtains bot/API credentials and the default super-admin password.
- **Remediation:** Rotate all exposed tokens/keys immediately, replace values in `.env.example` with empty strings or obvious placeholders, and require strong random secrets per environment.
### ERR-03 [CRITICAL] — All 15 Admin Screens & Plaintext Super-Admin Password Shipped to Regular Users in Flutter App
- **Location:**
  - `review/lib/screens/settings_screen.dart:485-680` (`ADMIN PAGES` section)
  - `review/lib/screens/admin/admin_users_screen.dart:22-27` (and 10 other `admin_*_screen.dart` files)
- **Description:**
  1. `SettingsScreen` unconditionally renders the entire **15-item `ADMIN PAGES` panel** to every regular reviewer user without any role/capability gate.
  2. **11 of the 15 admin screens** hardcode `TextEditingController(text: 'admin@tlstore.test')` and `TextEditingController(text: 'meandmyson')` right inside the login form (`admin_ads_screen.dart:182-185`, `admin_announcements_screen.dart:17-20`, `admin_app_listings_screen.dart:117-120`, `admin_app_versions_screen.dart:17-20`, `admin_package_addons_screen.dart:34-35`, `admin_payment_gateways_screen.dart:60-61`, `admin_review_templates_screen.dart:182-185`, `admin_reward_settings_screen.dart:39-40`, `admin_subscription_plans_screen.dart:46-47`, `admin_user_rankings_screen.dart:137-140`, `admin_users_screen.dart:23-26`).
  3. Worse still, `DatabaseSeeder.php:23-27` has its `if (! app()->environment(['local', 'testing', 'staging']))` guard **commented out**, meaning `php artisan db:seed` in production seeds `admin@tlstore.test` with password `SYSTEM_ACCOUNT_PASSWORD` (`meandmyson`).
- **Impact:** Any user opening **Settings → Admin Pages** in a build compiled from current `review/lib` sees all admin tools with working super-admin credentials pre-filled, granting full control over wallets, withdrawals, user bans, settings, and payouts.
- **Remediation:** Remove all hardcoded credentials from `review/lib/screens/admin/*.dart`, remove or compile-gate admin screens out of the end-user app, and uncomment the environment guard in `DatabaseSeeder.php:23-27`.
### ERR-04 [CRITICAL] — Unconditional `gs()` Query in `AppServiceProvider::boot()` Crashes Tests & Fresh Installs
- **Location:** `app/Providers/AppServiceProvider.php:29`, `app/Application/Support/Helpers.php:31-47`
- **Description:** `AppServiceProvider::boot()` executes `$general = gs();` on every HTTP request, console command, and test boot before checking whether the database is available or `system_settings` has been migrated.
- **Impact:**
  - Running `phpunit` with default `phpunit.xml` (`DB_CONNECTION=sqlite`, `DB_DATABASE=:memory:`) fails **13 out of 13 tests** during `setUp()` (`SQLSTATE[HY000]: General error: 1 no such table: system_settings`) before `RefreshDatabase` can run migrations.
  - Fresh deployments fail on `php artisan migrate`, `package:discover`, or `config:cache` if `system_settings` does not yet exist.
- **Remediation:** Guard `AppServiceProvider::boot()` when running in console/unit tests or wrap `gs()` in `Schema::hasTable('system_settings')` + `try/catch (\Throwable)`.
### ERR-05 [CRITICAL] — Missing `LedgerEntryStatus` Import Crashes Every VAS Purchase
- **Location:** `app/Application/Actions/Vas/PurchaseVasOrder.php:159`
- **Description:** `PurchaseVasOrder::reserveFunds()` references `LedgerEntryStatus::Reserved` without `use App\Enums\LedgerEntryStatus;` at the top of the file.
- **Impact:** Every call to `POST /api/v1/vas/purchases` (airtime, data, TV, electricity, education) throws a fatal PHP `Error`: `Class "App\Application\Actions\Vas\LedgerEntryStatus" not found`.
- **Remediation:** Add `use App\Enums\LedgerEntryStatus;` to `app/Application/Actions/Vas/PurchaseVasOrder.php`.
### ERR-06 [CRITICAL] — `DatabaseSeeder` Production Guard Commented Out (Seeds Demo Users, Wallets & Default Admin in Production)
- **Location:** `database/seeders/DatabaseSeeder.php:23-27`
- **Description:** Lines `//if (! app()->environment(['local', 'testing', 'staging'])) { ... // return; //}` are commented out, and `PackageAddonSeeder::class` is called twice (lines 26 and 40).
- **Impact:** Running `php artisan db:seed` in staging/production populates demo users, fake platform transactions, wallet credits (`WalletCreditSeeder`), and the default `admin@tlstore.test` account.
---
## 2. High-Severity Runtime, Database, Testing & Financial Errors
### ERR-07 [HIGH] — `gs()` Helper & `SystemSetting` Cache Are Logically Inverted (Always Return Empty `[]`)
- **Location:** `app/Application/Support/Helpers.php:34-40`, `app/Models/SystemSetting.php:15,32`
- **Description:**
  1. In `Helpers.php:36`:
     ```php
     $settings = SystemSetting::pluck('value', 'setting_key')
         ->where('is_public', true)
         ->toArray();
     ```
     `SystemSetting::pluck('value', 'setting_key')` executes `SELECT value, setting_key FROM system_settings` and returns a `Collection` mapping `setting_key => value` (strings, booleans, or JSON arrays — none of which have an `'is_public'` key). Calling `->where('is_public', true)` filters the **in-memory Collection** rather than SQL `WHERE is_public = 1`, so `$settings` **always evaluates to `[]`**.
  2. Because `if (!$settings)` is truthy for `[]`, `Cache::get('GeneralSettings')` treats the cached `[]` as a cache miss on **every single request**, re-querying the entire `system_settings` table and writing `Cache::put('GeneralSettings', [])` (with no TTL) over and over.
  3. Consequently, `gs('any_key')` and `SystemSetting::get('any_key', $default)` **always return `null` / `$default`**.
  4. `SystemSetting.php:32` calls `\Cache::forget('GeneralSettings')` via the global root alias instead of `Illuminate\Support\Facades\Cache` (fails if facade aliases are disabled; flagged by PHPStan).
- **Remediation:** Change to `SystemSetting::query()->where('is_public', true)->pluck('value', 'setting_key')->toArray()`, check `$settings === null` instead of `!$settings`, pass a cache TTL, and import `Illuminate\Support\Facades\Cache`.
### ERR-08 [HIGH] — `users.wallet_currency` `NOT NULL` Without Default Breaks `UserFactory` and 6 Feature Tests
- **Location:**
  - `database/migrations/2026_11_12_000001_add_wallet_currency_to_users.php:20`
  - `database/factories/UserFactory.php:26-39`
  - `app/Models/User.php`
- **Description:** Migration `2026_11_12_000001_add_wallet_currency_to_users.php` alters `users.wallet_currency` to `nullable(false)` with **no SQL `DEFAULT`**, while `UserFactory::definition()` sets `preferred_currency => 'NGN'` without `wallet_currency`, and `User` has no `$attributes` default or `creating` model hook.
- **Impact:** Every `User::factory()->create()` call crashes with `SQLSTATE[23000]: Integrity constraint violation: 19 NOT NULL constraint failed: users.wallet_currency`. Verified by running PHPUnit against a migrated SQLite database: **`AccountHistoryControllerTest` (1 test) and `GroupADailyShareRevenueTest` (5 tests) all error out on this constraint**.
### ERR-09 [HIGH] — `SubscriptionPackageGroupsTest` Fails (3/5 Tests) Due to Seeder Drift
- **Location:** `tests/Feature/SubscriptionPackageGroupsTest.php:40-46,79,165,244` vs `database/seeders/SubscriptionPlanSeeder.php:17-125`
- **Description:** `SubscriptionPlanSeeder` renamed plan keys from `demo-market-*` / `demo-review-*` to `market-lite`, `market-premium`, `market-gold`, `review-google-play-4m`, `review-samsung-store-4m`, `review-apple-store-4m`, and changed Lite `max_ad_shares` from `100` to `1000`, without updating `SubscriptionPackageGroupsTest`.
- **Impact:** 3 of 5 tests in `SubscriptionPackageGroupsTest` fail with `ModelNotFoundException: No query results for model [App\Models\SubscriptionPlan]`. Combined with ERR-04 and ERR-08, **only 4 of 13 tests pass even after bypassing the boot crash (0 of 13 pass out-of-the-box)**.
### ERR-10 [HIGH] — Duplicate & Permanently `pending` Rows in `transactions` on Online Wallet Top-ups
- **Location:**
  - `app/Application/Actions/Payments/InitializeGatewayTopup.php:105-119`
  - `app/Application/Actions/Payments/ProcessGatewayWebhook.php:196-237`
  - `app/Application/Actions/Wallets/CreditUserWallet.php:48-61`
  - `app/Observers/LedgerEntryObserver.php:84-94`
- **Description:**
  1. When a user starts an online wallet top-up, `InitializeGatewayTopup` creates a `Transaction` with `reference = 'payment-intent:{intentId}'` and `status = 'pending'`.
  2. When the gateway webhook or `VerifyGatewayTopup` confirms payment:
     - `completeSubscription()` (`ProcessGatewayWebhook.php:154`) and `completePackageAddon()` (`ProcessGatewayWebhook.php:185`) explicitly update `reference = 'payment-intent:{intentId}'` to `status = 'completed'`.
     - **`creditTopup()` (`ProcessGatewayWebhook.php:196-237`) does NOT update `Transaction`!** Instead it calls `CreditUserWallet::handle(..., sourceType: 'payment_intent', sourceId: $lockedIntent->id)`.
     - Wait — look closely at what `LedgerEntryObserver::sync()` does when `CreditUserWallet` creates that `LedgerEntry`:
       - If `$lockedIntent->currency === $walletCurrency`, `source_type = 'payment_intent'` matches `LedgerEntryObserver.php:89` (`'payment-intent:'.$entry->source_id`), **overwriting** `status` to `$entry->status->value` = `'posted'` (not `'completed'`), AND overwriting `description` only if empty.
       - However, if a user cancels or expires a top-up (`TopupController::cancel()`, `TopupController.php:71`), `PaymentIntent` becomes `'expired'`, **never updating `Transaction`**, leaving a phantom `'pending'` top-up in `/me/account-history` forever!
       - Similarly, when a gateway webhook receives a `failed` / `cancelled` payment (`ProcessGatewayWebhook.php:95-102`), it updates `PaymentIntent.status = 'failed'` and returns early — **leaving `Transaction` (`payment-intent:{id}`) stuck at `status = 'pending'` forever** for failed top-ups, subscriptions, and add-ons!
- **Impact:** Failed, cancelled, or abandoned gateway checkouts remain permanently `'pending'` in user transaction history.
### ERR-11 [HIGH] — `TransferTopupFunds` & `DecideCampaignOrder` Create Conflicting / Uncategorized `Transaction` Projections
- **Location:**
  - `app/Observers/LedgerEntryObserver.php:58-95`
  - `app/Features/Wallets/Http/Controllers/Api/V1/AccountHistoryController.php:129-144`
  - `app/Application/Actions/Campaigns/DecideCampaignOrder.php:42-55,89-92`
- **Description:**
  1. `CreateCampaignOrder` creates a `LedgerEntry` (`campaign_order_debit`), which `LedgerEntryObserver` maps to `type = 'campaign_order'`. Yet `AccountHistoryController::applyFilters()` (`lines 130-143`) omits `'campaign_order'` from `'payment'` and from the `'other'` exclusion list — meaning campaign orders are hidden from the `Payments` tab and dumped into `Other`, and `TransactionType` enum (`app/Enums/TransactionType.php`) **does not have a `case CAMPAIGN_ORDER = 'campaign_order'` at all!**
  2. **CRITICAL COROLLARY:** Because `Transaction::$casts` casts `'type' => TransactionType::class` (`app/Models/Transaction.php:27`), inserting `'type' => 'campaign_order'` in `LedgerEntryObserver.php:69` succeeds in SQL, **but the moment `AccountHistoryController` hydrates that `Transaction` model (`$transaction->type->value`), PHP throws a fatal `ValueError: "campaign_order" is not a valid backing value for enum App\Enums\TransactionType`**, completely breaking `/api/v1/me/account-history` and `/api/v1/home` for any user who ever created a campaign order!
### ERR-12 [HIGH] — 5 Eloquent Models Declare `ledgerEntries(): HasMany` Returning `Eloquent\Builder` (Runtime `TypeError` on Eager Load)
- **Location:**
  - `app/Models/AdShareEvent.php:39`
  - `app/Models/PaymentIntent.php:60`
  - `app/Models/ReferralAttribution.php:37`
  - `app/Models/ReviewSubmission.php:50`
  - `app/Models/WithdrawalRequest.php:50`
- **Description:** All 5 methods are declared `public function ledgerEntries(): HasMany` and return `$this->hasMany(LedgerEntry::class, 'source_id')->where('source_type', '...')`. Note that while `$this->hasMany(...)->where(...)` forwards to Builder and actually returns `HasMany` in some Laravel versions via `@mixin`, PHPStan flags it AND more importantly these models use `TrustLensMorphMap`, so the proper polymorphic relation is `$this->morphMany(LedgerEntry::class, 'source')` (`MorphMany`). Using `hasMany(..., 'source_id')->where('source_type', ...)` breaks `whereHas('ledgerEntries')` / eager-loading constraints when `source_type` morph aliases change and violates strict return typing expectations.
### ERR-13 [HIGH] — Missing `use App\Models\ReviewQuestion;` in `AdminConsoleController` Breaks Published Question Immutability Guard
- **Location:** `app/Features/Admin/Http/Controllers/Web/AdminConsoleController.php:303,331`
- **Description:** `AdminConsoleController::update()` (`line 303`) and `destroy()` (`line 331`) check `if ($recordModel instanceof ReviewQuestion ...)` without importing `App\Models\ReviewQuestion` (whereas `lines 93` and `109` use `\App\Models\ReviewQuestion`). PHP resolves `ReviewQuestion` inside namespace `App\Features\Admin\Http\Controllers\Web\ReviewQuestion`, which does not exist — so `instanceof` **silently evaluates to `false` every time**!
- **Impact:** Admins can edit or delete `ReviewQuestion` records that are already attached to published immutable questionnaires, corrupting historical review submissions or triggering foreign-key errors.
### ERR-14 [HIGH] — Undefined Variable `$configured` in `FrontendController::howItWorks()` Crashes `/api/v1/frontend` & `/api/v1/about`
- **Location:** `app/Features/Metadata/Http/Controllers/Api/V1/FrontendController.php:182-187`
- **Description:**
  ```php
  return collect($configured)
      ->filter(fn ($row): bool => is_array($row))
      ->values()
      ->map(function (array $row, int $index) use ($defaults): array {
          $fallback = $defaults[$index] ?? ['step' => $index + 1, ...];
          $step = $configured[$index]['step'] ?? $fallback['step']; // <-- $configured not in use(...)!
  ```
- **Impact:** As soon as an admin customizes `about.how_it_works` in `system_settings` (via `AdminFrontendContentScreen`), both `/api/v1/frontend` and `/api/v1/about` crash with `ErrorException: Undefined variable $configured`.
### ERR-15 [HIGH] — Undefined Function `money()` in `Transaction::getSignedAmountAttribute()`
- **Location:** `app/Models/Transaction.php:46-49`
- **Description:** `getSignedAmountAttribute()` calls `money($this->amount)` (`return $prefix . money($this->amount);`), which is not defined in `app/Application/Support/Helpers.php` or any installed Composer package.
- **Impact:** Accessing `$transaction->signed_amount` throws `Error: Call to undefined function App\Models\money()`.
### ERR-16 [HIGH] — Guest Contact Us Form Calls Non-Existent `POST /api/v1/guest-support` Endpoint (and Expects an Auth Token!)
- **Location:**
  - `review/lib/screens/frontend/widgets/contact_us.dart:39,60-84,113`
  - `review/lib/services/auth_api.dart:1241-1253`
  - `routes/api.php` (missing route)
- **Description:**
  1. `AuthApi.support()` posts to `'guest-support'` via `_authRequest('guest-support', ...)`, which expects a login/register response containing `{data: User, token: String}` and attempts to navigate to `HomeScreen(result: result, api: _api)`!
  2. No `/api/v1/guest-support` route exists anywhere in the Laravel backend (`404 Not Found`).
  3. In `contact_us.dart:113`, `final isUser = false;` is hardcoded even though `widget.user` is passed into `ContactUs`, so logged-in users viewing the landing screen can never tap `+ Ticket` to open `SupportTicketsScreen` — they are forced into the broken guest form instead.
### ERR-17 [HIGH] — Committed Flutter Web Bundle (`public/web/`) Points to Legacy Domain `gosmm.com` & Stale Source Tree
- **Location:** `public/web/main.dart.js` (lines `32768-32786`, `139306`, `142712`)
- **Description:** Inspection of the compiled `public/web/main.dart.js` bundle reveals it was compiled with `API_BASE_URL=https://gosmm.com/api/v1/`, `BASE_URL=https://gosmm.com`, and `WEB_APP_URL=https://web.gosmm.com` (an older brand/domain, including receipt copy `"visit gosmm.com"`), and is missing recent screens (`SupportTicketsScreen`, `Admin*Screen`, etc.).
- **Impact:** Anyone opening the web app served from `public/web/` sends all API traffic to `https://gosmm.com/api/v1/` instead of the current TLStore server, breaking CORS, authentication, and data loading.
### ERR-18 [HIGH] — `VerifyGatewayTopup` Uses Wrong Status Field for `bachs` Gateway Verification
- **Location:** `app/Application/Actions/Payments/VerifyGatewayTopup.php:55`
- **Description:**
  ```php
  $status = strtolower((string) ($intent->provider === 'bachs' ? ($data['status'] ?? '') : ($data['status'] ?? '')));
  ```
  Both branches of the ternary expression are identical (`$data['status'] ?? ''`), whereas `ProcessGatewayWebhook.php:77` documents that Bachs sends `payment_status` (`$payload['payment_status'] ?? $data['status']`). If Bachs verification returns `payment_status: "paid"` while top-level `status` is `"ok"` or `"success"` (envelope status), `VerifyGatewayTopup` checks `in_array($status, ['paid', 'completed', 'succeeded'])` and fails to recognize a paid Bachs checkout.
---
## 3. Medium-Severity Domain, Logic & Operational Bugs
### ERR-19 [MEDIUM] — `AutoApproveAdShares` Passes Wrong Arguments to `DecideAdShare::handle()`
- **Location:** `app/Console/Commands/AutoApproveAdShares.php:158-165` vs `app/Application/Actions/Moderation/DecideAdShare.php:23-29`
- **Description:** `DecideAdShare::handle()` accepts **5 parameters**:
  ```php
  public function handle(Admin $admin, AdShareEvent $event, string $decision, ?string $reason, ?SubscriptionPlan $plan = null): AdShareEvent
  ```
  `AutoApproveAdShares` passes **6 arguments**:
  ```php
  $decide->handle(
      $admin,
      $event->fresh(),
      'approved',
      'ads_auto_approved', // <-- passed as $reason instead of reason text!
      'Automatically approved after the configured verification delay.', // <-- passed as $plan (TypeError!)
      $subscription->plan, // <-- 6th argument, ignored!
  );
  ```
- **Impact:** Passing a `string` (`'Automatically approved...'`) into `?SubscriptionPlan $plan` throws a fatal **`TypeError: DecideAdShare::handle(): Argument #5 ($plan) must be of type ?App\Models\SubscriptionPlan, string given`** on **every single ad share** that `ads:auto-approve-shares` attempts to auto-approve! Because `AutoApproveAdShares` catches `Throwable`, every eligible pending ad share fails silently into `$report['failed']` every 5 minutes and is **never auto-approved**.
### ERR-20 [MEDIUM] — `PublicFrontService::getSportlight()` Immediately Evicts Its Own Cache on Every Call
- **Location:** `app/Services/PublicFrontService.php:17-22`
- **Description:**
  ```php
  public function getSportlight(): array
  {
      Cache::forget(self::CACHE_KEY);
      $data = Cache::remember(self::CACHE_KEY, now()->addMinutes(self::CACHE_TTL), function () { ... });
  ```
  `Cache::forget(self::CACHE_KEY)` is left unconditionally on the first line of `getSportlight()`, completely defeating the 60-minute cache on `/api/v1/spotlights` and `/api/v1/frontend` and running 4 multi-join queries on every public page load.
### ERR-21 [MEDIUM] — `PurgeDeletedUsers` (`users:purge-deleted`) Is Unscheduled & Incomplete on Foreign Keys
- **Location:** `app/Console/Commands/PurgeDeletedUsers.php:43-95`, `routes/console.php`
- **Description:**
  1. The API (`AuthController::deleteAccount()`) and Flutter UI promise permanent removal after 30 days, and `docs/` / `PurgeDeletedUsers` implement `users:purge-deleted`, yet `routes/console.php` **never schedules `users:purge-deleted`**.
  2. When manually executed, `PurgeDeletedUsers` manually deletes `wallets`, `ledger_entries`, `referral_codes`, `referral_attributions`, `referral_rewards`, and `withdrawal_requests`, **but forgets** `transactions`, `payment_intents`, `manual_deposit_submissions`, `user_subscriptions`, `user_package_addons`, `review_tasks`, `review_submissions`, `ad_share_tasks`, `vtu_orders`, `user_campaign_orders`, `support_tickets`, `notifications`, `user_devices`, `social_identities`, and `user_rank_qualifications`. Any table with a non-cascading `RESTRICT` foreign key to `users.id` causes `$user->forceDelete()` to fail with an integrity constraint violation!
### ERR-22 [MEDIUM] — Synchronous HTTP/SMTP Calls Inside `NotificationObserver` Slow Down User Requests
- **Location:** `app/Observers/NotificationObserver.php:10-32`, `app/Features/Notifications/Services/FirebaseCloudMessaging.php`, `app/Features/Notifications/Services/NotificationTemplateService.php`
- **Description:** `NotificationObserver` implements `ShouldHandleEventsAfterCommit` (good), **but does NOT implement `ShouldQueue`**. Whenever a user submits a review, ad share, support ticket, subscription, or withdrawal, Laravel sends Google OAuth JWT + FCM HTTP requests (12s timeout per device) and SMTP/API emails **synchronously inside the HTTP request lifecycle** before returning the JSON response to the client.
### ERR-23 [MEDIUM] — `'email:rfc,dns'` Validation Causes Checkout Timeouts & Breaks Offline/Test Environments
- **Location:**
  - `app/Application/Actions/Payments/InitializeGatewayTopup.php:45`
  - `app/Features/Payments/Http/Controllers/Api/V1/TopupController.php:24`
  - `app/Features/Subscriptions/Http/Controllers/Api/V1/SubscriptionController.php:80`
- **Description:** Uses `'email:rfc,dns'` on `customer_email` and `$user->email`. `dns` triggers live PHP `dns_get_record()` MX/A lookups during payment initialization. Users registered with valid test domains (`@example.com`, `@tlstore.test` seeded by `DemoUserSeeder`) or servers with restricted DNS latency fail checkout with 422 validation errors.
### ERR-24 [MEDIUM] — `SubscriptionPlanSeeder` Referral Percent & Withdrawal Schedule Contradictions
- **Location:** `database/seeders/SubscriptionPlanSeeder.php:63-124`
- **Description:**
  1. All 3 Review plans (`review-google-play-4m`, `review-samsung-store-4m`, `review-apple-store-4m`) set `'task_referral_percent' => 6` while `'task_referral_tiers' => ['1' => 5, '2' => 2, '3' => 1]`. `CreditReferralReward` reads `task_referral_tiers['1']` (`5%`), while plan cards displaying `task_referral_percent` show `6%`.
  2. Group A plans set model column `withdrawal_days => ['Monthly 28th']` (`lines 19, 32, 45`) while feature bullet metadata sets `'withdrawal_days' => ['Monthly']` (`line 161`). Verify how `RequestWithdrawal` parses `'Monthly 28th'`: if `RequestWithdrawal` checks weekday names (`Monday`..`Sunday`) or specific tokens, `'Monthly 28th'` may block or mis-evaluate withdrawals.
### ERR-25 [MEDIUM] — `TransferTopupFunds` Rejects `'review'` Target Wallet That `WalletController` Explicitly Allows
- **Location:** `app/Features/Wallets/Http/Controllers/Api/V1/WalletController.php:60` vs `app/Application/Actions/Wallets/TransferTopupFunds.php:18`
- **Description:** `WalletController::transfer()` validates `'target_wallet' => ['required', 'in:review,review_group_b,review_group_c,share,referral,bonus']`, but `TransferTopupFunds::handle()` rejects `'review'` (`! in_array($targetType, ['review_group_b', 'review_group_c', 'share', 'referral', 'bonus'], true)`), and its error message (`'Funds can only be transferred to review, share, or referral wallets.'`) omits `bonus` even though `'bonus'` is allowed.
### ERR-26 [MEDIUM] — Hardcoded Production Domains & Private LAN IPs Across Backend, Views & Mobile Manifests
- **Locations:**
  - `public/redirect.php:6-7` — Hardcodes `https://tlstore.org` and `https://web.tlstore.org` instead of environment config.
  - `resources/views/landing.blade.php:11,98` — Hardcodes `og:url` `https://tlstore.org` and `mailto:support@tlstore.org`.
  - `review/android/app/src/main/AndroidManifest.xml:95-101` — Contains leftover developer domains/IPs `gptminpro.online` and `http://10.145.107.216`, and misses `web.tlstore.org` (which `api_config.dart` uses as `webAppBaseUrl`).
  - `config/cors.php:14-17` — Only allows `http://localhost` and `http://127.0.0.1` patterns plus `WEB_APP_URL`/`APP_URL`/`ADMIN_APP_URL`; preview/staging HTTPS origins fail CORS unless all three env vars are updated.
### ERR-27 [MEDIUM] — Cleartext HTTP Traffic Enabled Globally on Both Android & iOS
- **Locations:**
  - `review/android/app/src/main/AndroidManifest.xml:10` — `android:usesCleartextTraffic="true"`
  - `review/ios/Runner/Info.plist:46-49` — `NSAllowsArbitraryLoads = true`
- **Impact:** Disables OS-level transport security protections against MITM attacks for Bearer tokens, withdrawal PINs, and TOTP codes, and triggers Apple App Store review scrutiny without justification strings.
### ERR-28 [MEDIUM] — Missing `assetlinks.json` & `apple-app-site-association` for Verified Deep Links
- **Location:** `public/.well-known/` (missing), `routes/web.php`
- **Description:** `AndroidManifest.xml:106` configures `android:autoVerify="true"` for `https://tlstore.org` and `https://app.tlstore.org` with comment `<!-- Requires assetlinks.json on server -->`, but neither `public/.well-known/assetlinks.json` nor `apple-app-site-association` exists in `public/` or `routes/web.php`. Android App Links and iOS Universal Links will fall back to browser disambiguation or fail silent verification.
### ERR-29 [MEDIUM] — Admin Console `deletable => true` Modules Lack Foreign-Key / Cascade Guards
- **Location:** `config/admin.php` & `app/Features/Admin/Http/Controllers/Web/AdminConsoleController.php:324-345`
- **Description:** `AdminConsoleController::destroy()` only guards `ReviewQuestion` and `ReviewTemplateVersionQuestion`. Deleting `subscription-groups`, `ads` (with existing `ad_share_tasks`), or `region-countries` directly calls `$instance->delete()`, which either orphans child records or throws unhandled 500 `QueryException` FK constraint errors.
### ERR-30 [MEDIUM] — `AccountHistoryController` Hardcodes `max:5` `per_page` Limit
- **Location:** `app/Features/Wallets/Http/Controllers/Api/V1/AccountHistoryController.php:18`
- **Description:** `'per_page' => ['sometimes', 'integer', 'min:1', 'max:5']` caps pagination to at most **5 items per HTTP request**. Scrolling through 100 transactions in `HistoryScreen` triggers **20 sequential HTTP requests** (each running 4 aggregate SQL queries on `transactions` and `withdrawal_requests`!), creating severe N+1-page API load.
### ERR-31 [MEDIUM] — `ResolvePayoutAccount` Passes Country Code Instead of Currency Code to Korapay Bank Resolve API
- **Location:** `app/Application/Actions/Wallets/ResolvePayoutAccount.php:41`
- **Description:**
  ```php
  'korapay' => Http::acceptJson()->withToken($secret)->timeout(15)->post($base.'/misc/banks/resolve', [
      'bank' => $bank->institution_code ?: $bank->code,
      'account' => $accountNumber,
      'currency' => $bank->country, // <-- passes 'NG' / 'GH' / 'KE' (ISO-2 country) instead of 'NGN' / 'GHS' / 'KES'!
  ]),
  ```
- **Impact:** Korapay's `/misc/banks/resolve` expects a 3-letter currency code (`NGN`, `KES`, `GHS`), not a 2-letter ISO country code (`NG`), causing Korapay bank account resolution to fail for all users when `account_validation.provider = korapay`.
### ERR-32 [MEDIUM] — No Rate Limiting on Admin Login Endpoints (`/admin/login` & `POST /api/v1/admin/auth/login`)
- **Location:** `routes/web.php`, `routes/api.php`, `AdminConsoleController::login()`, `AdminAuthController::login()`
- **Description:** While user auth routes apply `throttle:auth`, verify admin login protection: browser `/admin/login` has **no `throttle` middleware** in `routes/web.php`, allowing unlimited password brute-forcing against administrator accounts.
---
## 4. Low-Severity Code Quality, Config & Logging Defects
### ERR-33 [LOW] — `TransactionType` Enum Typos, Dead `default` Match Arms & Misclassified `VTU_PURCHASE`
- **Location:** `app/Enums/TransactionType.php`
- **Description:**
  - Typo labels: `'Salary Salary'` (duplicate word on rank/salary type) and `'Referal Reward'` (misspelled `Referral`).
  - Exhaustive `match ($this)` over all enum cases includes unreachable `default => ...` arms (flagged by PHPStan).
  - `isGatewayCharge()` includes `self::VTU_PURCHASE`, even though VAS/VTpass purchases debit the internal `topup_fund` wallet rather than charging an external payment gateway.
### ERR-34 [LOW] — `AutoApproveAppReviews`, `AutoApproveAdShares` & `PurgeDeletedUsers` Log Reports Before Calculating `execution_seconds`
- **Location:**
  - `app/Console/Commands/AutoApproveAppReviews.php:277-281`
  - `app/Console/Commands/AutoApproveAdShares.php:207-211`
  - `app/Console/Commands/PurgeDeletedUsers.php:146-148`
- **Description:** All three commands call `$this->writeReport($report, $startedAt)` / `Log::channel(...)->info(..., $report)` **before** `$report['execution_seconds'] = round(microtime(true) - $startedAt, 2);`, so persisted audit logs in `storage/logs/` never include `execution_seconds`.
### ERR-35 [LOW] — `GenerateDailyDemoListings` Checks `=== 2`, Loops Over 12 Ads, Claims to Generate 3 App Listings + 2 Ads
- **Location:** `app/Console/Commands/GenerateDailyDemoListings.php:16,29-32,160-185`
- **Description:** Command description says `"Generate three app review and four ad share listings"`, early-return checks `if ($existingAds === 2)` while `$adTemplates` actually contains **12 ad templates** (so `$existingAds` becomes `12` on first run, causing second run to print `"A partial set exists for today"` every day), generates **zero app review listings** (`AppListing` and `AppReviewCampaign` imports are unused), and prints `"Generated 2 ad listings"` after inserting 12!
### ERR-36 [LOW] — `env()` Called Outside `config/` or Improperly Inside `config/trustlens.php`
- **Location:** `config/trustlens.php` (`implemented_payment_gateways` array construction / env usage) & `database/seeders/DemoAdminSeeder.php`
- **Description:** Calling `env()` outside `config/*.php` returns `null` whenever `php artisan config:cache` is active in production.
### ERR-37 [LOW] — Unused Cache Constants in `FrontendController`
- **Location:** `app/Features/Metadata/Http/Controllers/Api/V1/FrontendController.php:19-20`
- **Description:** Defines `private const CACHE_KEY = 'api:frontend:v1';` and `private const CACHE_TTL = 60;` which are never read anywhere in the controller (`index()` runs uncached DB queries on every request).
### ERR-38 [LOW] — `PHP_BINARY` Mismatch Breaks `php artisan test` Under FrankenPHP Wrapper
- **Location:** Laravel `artisan test` runner vs FrankenPHP single-binary runtime
- **Description:** Under FrankenPHP (`frankenphp php-cli`), `PHP_BINARY` resolves to `/usr/local/bin/frankenphp` without the `php-cli` subcommand, causing `php artisan test` to fail with `Error: unknown command "vendor/phpunit/phpunit/phpunit" for "frankenphp"`. Always invoke `phpunit` (`/usr/local/bin/phpunit`) directly.
### ERR-39 [LOW] — `ContactUs` Widget Instantiates Duplicate `AuthApi()` Ignoring Injected `widget.api`
- **Location:** `review/lib/screens/frontend/widgets/contact_us.dart:39`
- **Description:** `_ContactUsState` creates `final _api = AuthApi();` (and never closes its `http.Client`) instead of using `widget.api`.
