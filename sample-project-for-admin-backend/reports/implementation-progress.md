# Standalone Share Access — Implementation Report

- **Date:** 2026-10-19
- **Branch:** `arena/b8796125-nextel`
- **PR:** [#13 — Implement share plan entitlements and dashboard access](https://github.com/fldsmine/nextel/pull/13) — **OPEN**, base `main`
- **Implementation commit:** `ca187a1`

## Outcome

Implemented independent eSIM and share-earning access paths. A user can buy or activate standalone share access without activating an eSIM package; standalone access is attached to exactly one CallType and snapshots that CallType’s quota, term, daily earning cap, and region. Existing no-stacking and claim-limit behavior remains in place.

## Delivered

- Added per-CallType USD share pricing, primary-region selection (default Nigeria), alternate-region customer/vendor prices, share-minute quota, term days, and daily earning cap. Backoffice CallType screens expose these fields and the share price.
- Added the distinct `SHARE_ACTIVATION` coupon type. Agent coupon minting, dashboard quick-mint, coupon batch/list/export relationships, and redemption now carry the selected CallType.
- Added direct share-access and share-minute top-up purchase services, controllers, routes, and settlement dispatch through `PaymentGatewayManager`. Entitlements are granted only after server-side payment verification. Share-only accounts cannot receive eSIM minutes or Call IDs from share top-ups; top-ups require an active share term.
- Added a missing/inactive CallType guard during verified share-purchase settlement and prevented a second different share checkout while another share purchase is pending.
- Updated the Shareholder Portal and `/dashboard/plans` for direct share purchase, share-code activation, active-term minute top-ups, and a portal route when there is no active share term.
- Kept share-only users out of the generic Earnings surface: the page redirects to the Shareholder Portal, its sidebar link is hidden, and blocked-claim copy points to the portal. Removed `open-activation` dispatches from the blocked claim flow.
- Updated API claim copy, the backoffice share-access activation count, and CallType factory defaults. Added feature coverage for regional pricing, no-eSIM access, CallType-bound coupon activation/minting, direct verified purchases, missing CallType settlement, and top-ups.

## Checks and validation

- `git diff --check` — passed.
- `node --check resources/js/mini-app/screens/earnings.js` — passed.
- Zero-width formatting-character scan — clean.
- PHP lint and Pest tests — **not run**: PHP and Composer are unavailable in this environment. The new feature tests therefore remain unexecuted and need CI/local PHP validation.
- The share-entitlement migration is present but **not applied**.

## Findings and safety notes

- Daily call-pool records still have no independent carrier-record verification source. The Shareholder Portal and API continue to disclose that usage is not independently verified; no synthetic record is described as verified carrier usage.
- No historical share-term grants or claim backfills were introduced. Legacy shareholder balances remain separately locked by the existing migration logic.
- No migration, payment, production job, email, or other business operation was run in this task.

## Remaining operational follow-up

Run PHP lint and the relevant Pest suite in an environment with PHP/Composer, review the pending migration through the project’s normal deployment process, and only then apply it in the appropriate environment. This implementation report is the progress/findings record for future updates to this work.

## Payment-request backoffice follow-up — implementation complete, validation pending

**Requested follow-up:** provide an admin workspace for payment requests across all channels, so staff can safely recover payments whose callback or automatic verification did not complete. This includes provider-backed account charges and guest storefront orders, including manual bank transfer.

### Delivered

- Added `backoffice.payment-requests.index` under the authenticated backoffice, with sidebar/search access. The queue separates account charges from guest orders and supports provider/status filters, payer/reference search, page-size selection, and pagination.
- Scoped the queue, selected-record actions, and summary counts to recognized providers (`PaymentProvider::values()`) and gateway-charge transaction types. Failed records are reviewable only when checkout evidence indicates the attempt was initiated; unresolved provider responses are not treated as payment.
- Added a provider recheck action that uses the provider pinned on the request via `PaymentGatewayManager`. A success still has to pass the existing reference, amount, currency, duplicate-reference ledger, and normal entitlement/fulfilment checks.
- Added super-admin-only manual confirmation requiring an explicit attestation, exact currency, sufficient amount, a distinct provider/bank transaction reference, evidence locator/source, and a reasoned note. Account charges and guest orders both go through their established settlement paths; duplicate settlement remains blocked by the shared single-use ledger.
- Added super-admin close-as-unpaid for still-pending requests, with an attestation and audit note. It does not create a ledger entry or grant the purchase; subsequent provider rechecks remain possible if new evidence is found.
- Added `PaymentResolutionLog` and its migration for provider rechecks, manual decisions, and closure actions. Resolution history is shown per request, with admin, status transition, verification source/evidence, reference, amount/currency, and notes. Manual resolution metadata is also attached to the settlement ledger.
- Added ten feature tests covering queue access/channel listing, provider recovery after failure, manual settlement/idempotency, cross-request provider-reference reuse, internal-reference rejection, underpayment, role restrictions, unresolved provider results, unpaid closure, and guest-order settlement.

### Validation and safety

- `git diff --check` — passed.
- Static review confirmed the route, provider/type scopes, selected-record scopes, service-level authorization, and the reference-distinctness check. The payment-request Blade select markup was corrected during review.
- PHP and Composer are unavailable and `vendor/bin/pest` is absent, so PHP lint and the new feature tests **could not be run**. CI or a local PHP environment must validate them.
- The new payment-resolution audit migration is present but **not applied**. No payment, migration, email, job, or other business operation was run.

### Remaining follow-up

Run PHP lint and `tests/Feature/BackofficePaymentRequestsTest.php` in CI/local PHP, review the migration through the normal deployment process, then apply it only in the intended environment. The backoffice can only be used after that migration is deployed.

## Admin mailing system — implementation complete, validation pending

**Requested follow-up:** build a real backoffice campaign system that selects eligible accounts from the app's current user/package/share-service data, previews the audience, composes and tests messages, and delivers each recipient asynchronously with progress, failure and retry controls.

### Delivered

- Added the campaign, campaign-recipient and reusable-template models and migration. Campaigns store filters/content/schedule/state; recipient logs retain only the user ID, state, attempt timestamps/count and a generic failure class—no copied recipient email addresses.
- Added the authenticated `/backoffice/mailing` Livewire workspace and navigation/search entries. Regular and super admins can write; support admins can review but cannot create, send, cancel, retry or modify templates.
- Added server-built, revalidated recipient queries for active/suspended status, account/package activation, exact or active/inactive package, active/inactive share subscription, website service, role, email verification and registration dates. Searchable paginated user lists support selected IDs across pages. System users are excluded, duplicates are blocked by a unique campaign/user key, email syntax is rechecked before recipient-row creation, and campaign recipient limits are enforced during both review and asynchronous audience building.
- Added a composer with subject, reply-to, sanitized HTML, optional plain text, synthetic-profile preview, supported personalization tokens, test-email queueing, drafts, duplicable campaigns, reusable templates, and an explicit confirmation before any send/schedule action.
- Added bounded audience-building and recipient-dispatch coordinator jobs, per-recipient queued delivery, a shared paced schedule plus global queue rate limiter, and a hard-capped in-flight window (up to 200 recipients globally / 20 per campaign by default). Delayed jobs are scheduled just in time rather than creating an unbounded ready-queue burst. Added queue retries/backoff, recipient-level status/failure logging, idempotent sent/failed/cancelled state transitions, cancellation of queued recipients, safe retry of failed rows only, and a minute scheduler tick for due/stale campaigns. Messages go individually through the app's existing default mailer; no To/Bcc recipient lists are constructed.
- Added non-secret readiness checks for the configured mailer, durable queue and persistent cache/lock store. The interface blocks sends/tests for log/array/sync mail/queue or missing infrastructure and explains that a worker and scheduler must be active. Existing SMTP/Resend Settings → Services configuration is reused; no parallel mail settings were added.
- Default limits: 500 users per audience-builder job, 200 max dispatch chunk, 20 in-flight recipients per campaign / 200 globally, 10,000 selected users, 250,000 recipients per campaign, 60 deliveries per minute, three configured transport attempts with 60/300/900-second backoff, and 100 KB each for HTML and text.
- Added feature tests for role authorization, audience filters and deduplication, HTML sanitization/personalization, draft/template workflow, asynchronous launch, invalid addresses, queued scheduled campaigns, bounded/rate-aware dispatch, backoff, recipient failure, cancellation and safe failed-recipient retry. Tests are authored but unexecuted.

### Validation and safety

- No campaign, email, job, migration, queue worker or scheduler was run during implementation. The tests use queue/mail fakes and do not send email.
- The mailing migration `2026_10_19_000003_create_mailing_campaign_tables.php` is present but **not applied**.
- PHP and Composer are unavailable in this environment; PHP lint and Pest tests could not be run. Review/execute `tests/Feature/BackofficeMailingCampaignsTest.php` plus the broader relevant suite in CI or a local PHP 8.4 environment.
- A durable queue worker and Laravel scheduler are operational prerequisites. Multi-node workers also need a shared cache store for the global dispatch schedule, rate limiter and unique-job locks. Email transport delivery is at-least-once across a process crash in the narrow provider-acceptance/database-acknowledgement window; normal queue retries and duplicates are protected by per-recipient state and unique queue jobs, but SMTP/API transports do not provide a universal exactly-once guarantee.

### Remaining operational follow-up

Run PHP lint and the new feature suite in CI/local PHP, review the pending migration and deployment settings, then apply it through the normal release process. Configure the existing SMTP or Resend mailer, a durable queue, a worker and the Laravel scheduler before creating or sending a campaign. No campaign or test email has been sent.

## Cash loan feature audit — read-only, 2026-10-20

### Source refresh and scope

- Fetched `origin/main` and refreshed the working tree to its `fbf75a5f1db8525c1f59e9da090e299a69d05afa` tree (`Merge pull request #14 from fldsmine/offline`) while staying on `arena/b8796125-nextel`. To keep the existing PR branch fast-forwardable, its prior head was merged into that refreshed tree; overlapping application files were resolved to the `main` versions. The pre-sync local working tree is retained in the Git stash `pre-main-sync backup for loan audit`; this audit report is the only intended difference from `main`.
- This is a static code audit of the main-branch loan implementation. No migration, database read/write, loan action, schedule command, or test was run.

### Structure and settings

- `routes/backoffice.php` registers `backoffice.loans.index` (`/backoffice/loans`) inside `auth:admin`; `backoffice.settings.features` (`/backoffice/settings/features`) is additionally restricted to the `regular` role, with `super` allowed by `EnsureAdminHasRole`. Support admins can read the loans page but cannot write: `AdminRole::canWrite()` excludes support and the Livewire actions also call `authorizeWrite()`.
- The Loan card in `resources/views/livewire/backoffice/settings/feature-setting.blade.php` is backed by `FeatureSetting`. It loads the effective `settings.loan.*` config values on mount, validates the form, and persists all feature settings together through `update_setting()`. That helper upserts rows in `settings`, clears the settings cache and clears Laravel's config cache; `SettingServiceProvider` maps the stored loan keys back onto `settings.loan.*` at application boot. The settings seeder does not seed loan-specific keys, so environment/config defaults apply unless rows have been saved. A live deployment's database settings can therefore override the code defaults below.
- UI/config key → persisted settings key: `enabled` → `enable_loan`; `disabled_title` → `loan_disabled_title`; `disabled_message` → `loan_disabled_message`; `max_amount` → `loan_max_amount`; `repayment_days` → `loan_repayment_days`; `service_fee_percent` → `loan_service_fee_percent`; `auto_reject` → `enable_loan_auto_reject`; `auto_reject_minutes` → `loan_auto_reject_minutes`; `min_account_age_days` → `loan_min_account_age_days`; `rejection_reason` → `loan_rejection_reason`.
- `config/settings.php` defaults: loans enabled; max `$100` USD; repayment window 30 days; service fee 0%; auto-rejection enabled after 5 minutes for accounts younger than 30 days; default rejection message says an account must be active for at least a month. Disabled title/message also have defaults. These values are from code, not a claim about the live database.
- Form validation requires max amount ≥ 0, repayment days ≥ 1, fee 0–100%, auto-reject delay ≥ 1 minute, minimum age ≥ 0, and rejection reason ≤ 500 characters. It validates the whole Feature Settings form, not just the Loan section. The rejection-reason input is hidden when auto-rejection is toggled off, although the same setting is still used for manual admin rejections.

### Data model and user application flow

- `Loan` is a UUID model related to one `User`; the `loans` table stores `amount`, `total_repayment`, `due_date`, string `status`, timestamps, and a `(user_id, status)` index. A later migration adds nullable `rejection_reason` (500 characters). Statuses are only `processing`, `approved`, and `rejected`. The `PayLaterService`/`minute_credits` feature is a separate minutes-on-credit product, not this cash-loan workflow.
- The web route is `/dashboard/loan` (`routes/web.php`): it is behind authentication/onboarding and `EnsureActivated`, which requires `user.package_id`. When loans are disabled it renders the configured lock title/message. Otherwise it shows terms, amount presets/input, the fee and total, a user-chosen due date, and the user's latest 15 applications. Local-currency entry is converted to USD before submission; other entries are treated as USD.
- The mobile/Telegram Mini App uses authenticated `GET /api/v1/loans` to get enabled state, max amount, repayment days, fee, pending flag and latest 15 loan records; it submits `POST /api/v1/loans` with `amount` in USD and `due_date`. The API route group requires Sanctum authentication and a full API token; the POST also has `throttle:api-auth`. `LoanApplyRequest` validates positive numeric amount and a date after today, then the shared `LoanService` applies the business rules.
- `LoanService::apply()` rejects applications when the feature is disabled, when an existing `processing` loan exists, when the amount is below $1 or above the configured maximum, or when the due date is not in the future/within the configured window. It recalculates the pending-loan check under a transaction after locking the user row, rounds the amount and fee/repayment total to cents, and creates a `processing` record. A fee is calculated at application time as `amount × service_fee_percent`; total repayment is saved on the record. An approved or rejected loan does not count as `processing`, so the current rule only prevents concurrent pending applications.

### Review, approval, rejection, and scheduling

- Backoffice `/backoffice/loans` lists records with status filters, username search and 20-row pagination. Selecting a row shows the user, amount, repayment total, submitted date and due date. The UI presents confirmed Approve/Reject actions only while `processing`; approval sets status to `approved`, rejection sets `rejected` and copies the configured generic `settings.loan.rejection_reason`. There is no per-application reason field. Approved loans can have their due date extended by a fixed 30 days.
- `loans:auto-reject` is scheduled every five minutes with `withoutOverlapping()`. When enabled, it bulk-rejects only still-processing applications older than `auto_reject_minutes` whose user account `created_at` is strictly newer than `now() - min_account_age_days`, and stores the configured rejection reason. This is a delayed post-submission rule, not a pre-application age check. Applications from older accounts are not automatically approved; they remain pending for manual review.
- User history/API resources expose the resulting status and rejection reason. The status/action implementation does not record reviewer identity, review timestamp, or an audit event.

### Findings to keep in view before changing the feature

1. **Activation differs between clients:** the web page requires an activated package, but `/api/v1/loans` is inside the general authenticated API group and does not attach `api.activated`; `LoanService` also does not check `package_id`. An authenticated full-token user without a package can therefore submit through the API even though the web route redirects them.
2. **Eligibility is not checked before submission:** the UI always renders “Eligible / Good standing account” when the feature is on. Minimum account age is enforced only later by the scheduler, if the scheduler is running and auto-reject is enabled. If auto-reject is disabled, no account-age rule remains in the application service.
3. **Approval currently changes state only:** no loan disbursement, wallet credit, payout transaction, repayment endpoint, repayment/settled/overdue status, or overdue-loan job was found. The user view says a confirmation will be sent to their registered contact, but no loan-specific notification/mail dispatch was found in the request or review paths.
4. **Review transitions are not fully enforced server-side:** `setLoanStatus()` accepts any enum status and updates the row without checking its current status, although the UI only offers approve/reject while processing. A direct Livewire call can therefore reopen/change a rejected or approved row. The update also reports success without checking whether a row was affected. `extendDue()` checks that the current status is approved, but adds a hard-coded 30 days, independent of the configured repayment window.
5. **Generic rejection copy is shared:** auto-rejection and manual rejection use the same configured reason. Admins cannot enter a case-specific reason; the setting field is hidden when auto-reject is off even though manual rejection still reads it.
6. **Current loan tests are narrow:** `tests/Feature/Api/PayLaterLoanTest.php` covers terms/history, a valid request with a fee, duplicate pending request, amount above the cap, and due date beyond the window. The test suite has no loan-specific coverage for activation parity, minimum amount, disabled feature, age-based auto-rejection/scheduler, admin authorization or state transitions, actual disbursement/repayment, or notifications.
7. **Disabled copy is not shared with the Mini App:** web renders the configured disabled title/message, but `GET /api/v1/loans` returns only the `enabled` flag and `resources/js/mini-app/screens/loan.js` uses hard-coded disabled copy. Also, the settings validator permits `loan_max_amount = 0`; with the feature still enabled, the service's $1 minimum means no request can pass.

**Bottom line:** the current implementation is a configurable loan-request and manual-review/status tracker with one scheduled age-based rejection rule. It does not yet implement the financial lifecycle after approval. The points above are audit findings, not changes made to the loan code.

## Shareholder Portal view adaptation — 2026-10-20

### UI changes

- Updated `resources/views/livewire/dashboard/earnings/shareholder.blade.php` to offer selectable, configured CallType share products when the user has no active share term. Product descriptions and details come from the actual CallType fields; the shown price comes from each existing `$cards[*]['sharePrice']` value (regional price resolution with the existing currency formatter).
- The selected product detail panel shows its configured daily earnings cap, revenue-share percentage (`claimable_percentage`), share-term minute quota, term length, and the minimum shareholder withdrawal from the existing settings configuration. It does not make unsupported resale/withdrawal-frequency or product-benefit claims.
- Kept direct checkout and activation-code redemption in separate sibling forms. The direct-purchase action posts the selected `call_type_id` to the existing `share-access.buy.start` route and is shown only when the existing per-card `canBuyShare` flag allows it. Code redemption continues to use the existing Livewire `redeemShare` action and selected product ID.
- The earnings/claim-card grid is now rendered only for an active share plan. Existing package-share renewal, add-share-minutes, claim/breakdown, and share-access purchase routes remain in place; no payment, subscription, claim, or other business action was executed.

### Validation and scope

- `git diff --check` passed.
- PHP, Composer, `vendor/autoload.php`, and the Pest executable are absent from this checkout, so Blade compilation and automated tests could not be run. No dependency installation was attempted.
- This is a Blade view-only change; no Livewire action, route, payment routing, data model, or database behavior was changed.

## `call_records` generation and share-claim source audit — 2026-10-20

### Finding

The `call_records` available for shareholder claims are a **synthetically generated shared daily pool**, not a feed of verified carrier call-detail records. In the checked-out code, the only application writer is `app/Services/CallLogService.php`; I found no path that imports a provider CDR or creates these rows from completed user call sessions. The API explicitly labels earnings records as `record_source: daily_pool` and `usage_verified: false` (`app/Http/Controllers/Api/V1/EarningsController.php`). The separate Nextel Cloud session model/table is `cloud_calls`; it is not written into `call_records` by `CloudCallService`.

### Generation mechanics and cadence

- `CallLogService::generatePool()` selects active `CallType` rows, then for each one generates `random_int(min_per_batch, max_per_batch) * 3` rows. It selects duration randomly within that CallType's configured `min_minutes`–`max_minutes`, picks `incoming`/`outgoing` randomly, constructs a counterparty from a random network prefix and random seven-digit suffix, copies `rate_per_minute`, and calculates `earnings = duration_minutes × rate_per_minute` rounded to six decimals. Every row in one generation call gets the same `created_at` timestamp. The rows are inserted in chunks of 500. See `app/Services/CallLogService.php:15-77` and `app/Models/AllocatedNumber.php:111-119`.
- At the time of the read-only audit, `calls:generate` was scheduled at 00:00, 08:00, and 16:00 (`routes/console.php:22-25`), and the command called `generatePool()` directly (`app/Console/Commands/GenerateCallsCommand.php:14-18`), so each invocation appended another randomized batch that day. This follow-up changes the command to use `ensureTodaysPool()` and schedules it once daily; the updated behavior is recorded in the implementation section below. The Shareholder page and earnings API also use `ensureTodaysPool()` as a lazy fallback when no rows for today exist.
- `calls:prune` runs at 00:01 and deletes rows whose `created_at` is before the start of the current day (`app/Console/Commands/PruneCallLogsCommand.php:14-20`). This makes unclaimed pool entries same-day only. The table migration describes the pool as shared and ownerless (`database/migrations/2026_06_08_144626_create_call_records_table.php:10-23`).
- `DatabaseSeeder` runs `TestDataSeeder` only outside production; that seeder also calls `CallLogService::generatePool()` to populate a demo pool (`database/seeders/DatabaseSeeder.php:22-30`, `database/seeders/TestDataSeeder.php:1420-1427`). The demo seeder has separate fabricated historical `CallClaim` entries as well; those are not evidence of real calls either.

### What a share claim does with the pool

`ClaimService` calculates a full-row amount as the generated row's `earnings` multiplied by the user's configured claim percentage; when a claim is partial, it scales that amount by the used-duration fraction. It checks that the record is from today and the user has an active share subscription covering the CallType, then applies the accrued share-minute quota and daily earning cap. The same pool record can be claimed once by each eligible user; uniqueness is per `(user_id, call_record_id)` (`database/migrations/2026_06_08_144627_create_call_claims_table.php:15-29`). A successful claim increments the shareholder balance and lifetime earnings and creates a completed call-earning transaction. Consequently, these credits are calculated from the generated pool, not independently verified network usage or cash receipts.

### Verification scope

This was a read-only source audit. I did not query a production database, inspect live scheduler/worker execution, run the generator/pruner, run tests, or perform a claim. Live CallType values and actual scheduled-run counts therefore remain unverified. Do not present these pool entries or resulting claims as verified real-world call usage without a separate trusted call-record source.

## Daily pool target and share-minute pacing — implementation authored, validation pending — 2026-10-20

### Confirmed behavior

- `max_daily_shareholder_earning` remains a **per-user payout ceiling**. The daily pool should provide enough aggregate claimable value to reach that ceiling when the configured rates, percentages, term-paced minutes, and valid record bounds make it feasible; a full pool row need not fit one user's remaining quota or cap because claims can be proportional partials. The cap is not a per-record earnings floor.
- `monthly_share_minutes` is a recurring monthly quota. New term allocations are prorated across the configured term using a 30-day month; base minutes accrue evenly by day, and unused accrued minutes roll forward within the term. Per-user pacing is enforced at claim time, not by the shared pool.
- Generated row earnings remain `duration_minutes × rate_per_minute` (six-decimal rounding). Infeasible targets are logged rather than met by inflating earnings.

### Implementation authored; not yet linted or tested

- `app/Services/CallLogService.php` retains the existing random daily pool and appends bounded target rows for configured standalone CallTypes and active Packages. Package targets share that package's quota and try eligible included CallTypes in descending claimable return, falling back when a candidate has invalid duration bounds. The target minute budget comes from the term-prorated allocation divided by term days, matching `ShareSubscriptionService` and claim-time accrual. Full rows may exceed one user's accrued minutes or daily cap; `ClaimService` applies proportional partials at claim time. Infeasible settings emit a structured `Log::warning` with product and reason context; no earnings formula is overridden.
- `calls:generate` now uses `ensureTodaysPool()` and is scheduled once daily at 00:00 with overlap protection. Existing earnings/shareholder views and API lazy generation remain the fallback if the scheduler has not run; this prevents the old three-times-daily schedule from appending duplicate target rows.
- `ShareSubscriptionService` stores the term total as `monthly_share_minutes × share_term_days / 30`, rounded to two decimals, for package and standalone CallType terms. A new one-time migration converts still-active legacy subscriptions from their stored monthly snapshot to a term-prorated total using the saved start/expiry interval; historical inactive rows are left unchanged. The migration has a deliberately non-reversing `down()` because claims may have consumed the corrected allocation after deployment. It has **not** been applied.
- `ClaimService::remainingMinutes()` remains the full term-wide unspent quota for progress displays. The new `availableMinutes()` is the accrued base quota plus active share-minute top-ups; claim eligibility and the locked claim allocation use this paced amount. Unused accrued base quota carries forward; top-ups retain their existing expiry behavior. The 2026-10-20 partial-claim follow-up below supersedes the original all-or-nothing row-fit rule.
- Updated the web Earnings, Shareholder, CallType, Dashboard and Plans views, the earnings/portal/minute-plan APIs, and the Mini App to display currently claimable minutes separately from term-wide remaining minutes and to label the not-yet-accrued case. Added clear disclosure in the web Earnings/Shareholder pages and Mini App that pool entries are synthetic and not independently carrier-verified.
- Added feature tests for standalone and package pool targets, fallback to a feasible package CallType, preserved earnings arithmetic, infeasible-target warning/no row inflation, term proration, and claim-time daily accrual/roll-forward. Tests are authored but unexecuted.

### Audit continuity and safety

- The preceding `call_records` audit remains valid: pool rows and resulting credits are synthetic and are not independently verified carrier usage. The Earnings and Portal APIs continue to return `record_source: daily_pool` and `usage_verified: false`; no code or copy presents these rows as verified usage.
- This implementation does not run a migration, generator, pruner, scheduler, claim, payment, email, or other business operation. No dependency installation was attempted.

### Validation and follow-up

- `node --check resources/js/mini-app/screens/earnings.js` — passed. `git diff --check` and the untracked-deliverable trailing-whitespace scan — passed.
- PHP and Composer are not installed and `vendor/bin/pest` is absent, so PHP lint, Blade compilation, and feature tests have not been run. Validate the changed PHP/Blade and run `tests/Feature/CallLogServiceTest.php`, `tests/Feature/ShareSubscriptionServiceTest.php`, `tests/Feature/Api/EarningsTest.php`, plus the relevant broader suite in CI/local PHP before relying on the change.
- Review the new legacy-allocation conversion through the normal deployment process, then apply it only in the intended environment. The migration is only a file change at this point; no database was touched.

## Proportional partial call claims — implementation authored, PHP validation pending — 2026-10-20

### Confirmed behavior implemented

- Claims may now use the portion of a pool row that fits the user's currently accrued share minutes, even when the row's full duration is longer. A claim can also be proportionally capped to the user's remaining daily earning cap; no credit can exceed either limit.
- The used duration is rounded down to the claim ledger's 0.01-minute precision. Payout is the row's stored earnings multiplied by `used duration / full row duration`, then multiplied by the applicable claim percentage. Full-duration claims retain the existing payout calculation, and the shared `CallRecord` row/earnings are never mutated.
- A partial claim still creates one `CallClaim` for the user and pool record, records only the used duration, and allocates only those minutes across the share-minute pools. Existing per-user/per-record uniqueness remains in force; another eligible user can still claim the shared record independently.
- API rows now expose full `duration_minutes`, current `claimable_minutes`, and any `claimed_minutes`. API claim success returns the actual claimed duration and original pool duration. Web Earnings, Shareholder Portal, CallType breakdown, and Mini App summaries/rows calculate partial claim amounts and label partial durations. Aggregate summaries allocate the shared minute/cap budget across records deterministically so totals do not multiply the same remaining quota. Synthetic-pool/non-verification disclosures remain intact.

### Implementation and tests authored

- `app/Services/ClaimService.php` centralizes duration selection and proportional amount calculation, uses both accrued minutes and daily cap remaining, and rechecks the claim under the existing transaction/user/subscription locks before recording the actual duration and allocations.
- `app/Http/Controllers/Api/V1/EarningsController.php`, `app/Http/Resources/Api/V1/CallRecordRowResource.php`, Livewire Earnings/Shareholder/CallType components, the corresponding Blade views, and `resources/js/mini-app/screens/earnings.js` now follow the partial-claim calculation and disclose the duration/amount to users.
- `tests/Feature/Api/EarningsTest.php` now covers a row longer than accrued minutes and verifies proportional amount, stored duration, allocation sum, and daily-cap-limited partial payout. `tests/Feature/ShareSubscriptionServiceTest.php` covers partial use of the remaining accrued minutes, carry-forward after consuming the partial duration, and deterministic aggregate allocation so summaries do not count the same cap/quota for multiple rows.

### Validation and safety

- `node --check resources/js/mini-app/screens/earnings.js` and `git diff --check` passed. PHP/Pest validation remains pending: PHP, Composer, and `vendor/bin/pest` are unavailable, and dependency installation was not attempted. Run PHP lint, Blade compilation, and `tests/Feature/Api/EarningsTest.php` plus `tests/Feature/ShareSubscriptionServiceTest.php` in CI or a local PHP environment.
- This change only edits application code, tests, and this report. No migration, database mutation, production/business operation, payment, job, email, or dependency operation was run.

## Daily-cap reachability and one-click claim-all — implementation authored, validation pending — 2026-10-20

### Business decision and quota floors

- Keep each configured `max_daily_shareholder_earning` as a **hard per-user ceiling**. Do not lower the caps, change the claim percentages/rates, or invent earnings. Instead, raise the recurring `monthly_share_minutes` quota to the minimum 0.01-minute daily pace needed at the configured claimable rate; reject positive caps with no positive effective claim rate.
- For the provided CallType values, the resulting floors are:
  - **On-Net:** `$0.0104/min × 70% = $0.00728/min`; a `$0.65` cap needs `89.29` claimable minutes/day, so the monthly quota floor is **2,678.70** minutes (up from 190).
  - **Off-Net:** `$0.102/min × 85% = $0.0867/min`; a `$2.00` cap needs `23.07` claimable minutes/day, so the monthly quota floor is **692.10** minutes (up from 600).
- `ShareQuotaService` is the shared arithmetic for the quota floor, the 30-day monthly basis, and daily budget units derived from the rounded term allocation. Package quotas use the best active, available included CallType's effective claim percentage. The quota floor uses each CallType's base rate because `CallLogService` writes pool earnings at that stored rate; package `rate_per_minute` overrides are currently used by call preview, not by pool-record payouts. Standalone products use their CallType rate and percentage.
- Backoffice CallType saves automatically raise an insufficient monthly quota, reject infeasible positive caps, and validate that duration bounds contain at least one 0.01-minute step. Package saves validate the nested CallType configuration/enable flags and do the same across enabled active CallTypes; editing a CallType also raises any affected package quota that would otherwise fall below its cap floor. UI labels now distinguish monthly minutes from the prorated term total.
- Added `2026_10_20_000002_raise_share_quotas_to_support_daily_caps.php`. On deployment it raises configured CallType/package monthly quotas, any still-active subscription's `daily_earning_cap` to at least the product's configured cap, and `share_subscriptions.allocated_minutes` to the required term total, never reducing an existing entitlement. It uses the actual start/expiry term length and preserves the 30-day monthly basis. It deliberately has a non-reversing `down()` so already-granted or consumed minutes cannot be silently removed. **The migration has not been run.**

### Pool generation and claim-all

- The original PR implementation used term-prorated base-rate target rows and structured warnings for infeasible settings. That generator was superseded by the offline-branch refresh below. `ClaimService` still proportionally limits each claim to the user's actual available minutes and remaining daily cap.
- `ClaimService::claimBatch()` previews the entire supplied pool in high-return-first order, filters already-claimed rows, and executes each record through the existing transactional locks and limits inside one batch transaction.
- Implemented the existing web Earnings button (all today's records, not only the current paginated page), added the same action to the Shareholder Portal for share-only accounts, and exposed `POST /api/v1/earnings/claim-all` for the Mini App. All surfaces retain synthetic-pool / unverified-usage disclosures and the same quota/cap rules.
- A Decimal simulation of the pre-refresh base-rate generator yielded `$0.649956` for On-Net and `$1.999302` for Off-Net. Those figures do not validate the offline-branch generator added below. Hard daily caps continue to be enforced by `ClaimService`; the new target-rate path needs application-level validation before relying on its payout totals.

### Tests authored and safety

- Added tests for the On-Net/Off-Net quota floors and term pacing, pool target reachability and actual cap-limited claims, batch claims across all eligible records with partial-minute/cap limits, and web claim-all across records beyond the 12-row visible page. Updated the package target test to verify proportional cap-limited claims from a high-return row.
- `node --check resources/js/mini-app/screens/earnings.js` — passed. `git diff --check` — passed. PHP/Pest and Blade validation remain pending because PHP, Composer and `vendor/bin/pest` are unavailable; dependency installation was not attempted.
- No migrations were run. No database, production/business state, payment, scheduled job, email, or other operation was triggered.

## Offline-branch refresh — 2026-10-21

### Source and merge

- Fetched `origin/offline` at `df5f77d` and merged it into the existing `arena/b8796125-nextel` branch without switching branches. The PR branch's partial-claim and claim-all work remains present. For overlapping Shareholder views, retained the current PR versions so the newer product details, quota summaries, and claim-all action remain available.
- Took the offline branch's `CallLogService::generatePool()` implementation and its 12-decimal CallRecord precision migration. `calls:generate` continues through `ensureTodaysPool()`; the empty-pool path now correctly returns integer `0`, and the unused legacy `generatePools()` helper returns its declared integer row count.

### Current pool calculation and behavior

- The offline generator allocates each active CallType's daily duration across rows within configured min/max bounds, computes gross target earnings as `max_daily_shareholder_earning ÷ claimable_percentage`, and apportions that target across the generated durations. It stores each row's derived `rate_per_minute` and earnings at 12-decimal precision; the stored value remains duration × stored per-row rate, but the rate is target-derived rather than the configured CallType base rate. This replaces the PR branch's base-rate target-row generator; the imported generator has no separate package-cap target pass, although ClaimService still enforces each subscriber's hard cap.
- The offline calculation uses `monthly_share_minutes ÷ share_term_days` for its daily duration. This is the imported branch's behavior and differs from the earlier 30-day monthly-basis pacing formula; review that term convention when validating product settings. Infeasible duration splits now throw `InvalidArgumentException` before rows are inserted rather than emitting the previous structured warning.
- Added `2026_10_21_042253_increase_call_records_earnings_precision.php` to widen `call_records.rate_per_minute` and `call_records.earnings` to 12 decimal places. It is present but **not applied**.
- Updated `tests/Feature/CallLogServiceTest.php` for target-derived row rates, the supplied On-Net/Off-Net caps, proportional claims, infeasible settings, and an empty CallType set. Tests remain authored but unexecuted.

### Validation and safety

- `node --check resources/js/mini-app/screens/earnings.js` and `git diff --check` — passed after the merge resolution. PHP, Composer, Pest, and Blade validation remain unavailable in this environment. No dependency installation was attempted.
- No migration or database/business operation was run. Daily pool entries remain disclosed as synthetic and not independently carrier-verified.

## Loan application and manual review workflow — implementation authored, validation pending — 2026-10-21

### Delivered

- Added configurable Student, Business, Agro Farm, and Trade loan types, USD/NGN application-fee defaults ($1 / ₦1,000), customer fee display, vendor coupon prices, amount/service-fee bounds, and repayment-window settings. Applicants enter a loan type, amount, repayment date, accept the agreement, and review a repayment summary before submitting.
- A qualifying unused loan-fee coupon is claimed atomically before a processing loan is created. Without a coupon, a pending fee transaction reserves the user's application slot; the loan is created only after the pinned active payment channel returns a reconciled successful payment. User-row locking serializes coupon redemption and payment reservation. Approved/rejected applications no longer block a new request; unresolved applications do.
- Added vendor loan-fee coupon minting at configurable vendor/customer prices and country-eligible single-use coupon redemption. Added API validation/checkout support and application/fee/receipt fields to loan API resources.
- Added owner-scoped, temporary-signed PDF receipts and a browser-generated high-resolution PNG export. Receipts show application state, amount/type, due date, repayment, fee and agreement details. Backoffice loan previews include fee/payment/agreement data; staff can explicitly approve/reject processing applications only.
- Removed the automatic rejection schedule, command, and related settings. No automatic rejection remains in the loan review flow.
- Added/updated `tests/Feature/Api/PayLaterLoanTest.php`, `tests/Feature/BackofficeLoanReviewTest.php`, and `tests/Feature/LoanReceiptTest.php` for fee/coupon/payment behavior, unresolved/resolved applications, vendor pricing/minting, explicit staff decisions, and signed receipt ownership.

### Validation and safety

- `git diff --check` and `node --check` on the extracted PNG-export script — passed. PHP and Composer are unavailable and `vendor/bin/pest` is absent, so PHP lint, Blade compilation, and Pest tests have **not** run. The browser-side PNG export has not been manually exercised.
- Added `2026_10_21_000003_add_application_details_to_loans_table.php`; it is present but **not applied**.
- No migration, database/business-state mutation, payment, scheduled job, email, or other business operation was run. The new flow remains pending CI/local PHP validation and browser verification.

## Loan fee mode and applicant disbursement details — implementation authored, validation pending — 2026-10-21

### Delivered

- Added a backoffice setting for fixed versus percentage application fees. Percentage and fixed USD/NGN fee values remain separate; the existing fixed fee is the default.
- Confirmed calculation basis: the requested loan principal, excluding the loan service fee. The service converts that principal to the active gateway currency when needed, rounds the fee to the currency's minor unit, and snapshots fee type/percentage/amount/currency on the pending transaction and resulting loan. The exact channel quote is shown in the web summary and returned by the API before checkout.
- Percentage fees require a configured active payment channel and cannot be paid using vendor coupons. Vendor minting options and dashboard choices are hidden in percentage mode, and `LoanService` rejects a coupon attempt server-side. Fixed fees retain payment-channel and eligible single-use vendor-coupon paths.
- Added required account name, account number and bank name inputs to the web and Mini App/API application paths. Values are validated, snapshotted in pending payment metadata, and saved to each loan. Pending checkout reuse also requires the same account details.
- Added backoffice review fields for the disbursement account and a manual-disbursement reminder after approval. Staff still make the approval/rejection decision; no automated transfer or automatic rejection was introduced. Applicant views and the owner-scoped API resource show the saved account details.
- Added loan fee type/percentage snapshots and nullable disbursement details for legacy records in `2026_10_21_000004_add_fee_mode_and_disbursement_details_to_loans_table.php`. This migration, like the earlier loan migration, is present but **not applied**.
- Updated API validation and the Mini App so it submits the loan type, agreement acceptance, and required bank details, supports fixed-mode coupon entry, opens a channel checkout, and can resume an outstanding checkout. Added tests for percentage-only channel payment, coupon rejection/hiding, fee snapshots, account persistence/required fields, and backoffice account visibility.

### Validation and safety

- `git diff --check`, `node --check resources/js/mini-app/screens/loan.js`, and a trailing-whitespace scan of the changed PHP/test files — passed. PHP, Composer, and `vendor/bin/pest` are unavailable in this environment, so PHP lint, Blade compilation, and Pest tests cannot be run here.
- No migration, database/business-state mutation, payment, scheduled job, email, or other business operation was run. The percentage basis is confirmed; run the loan API, backoffice and receipt tests plus PHP/Blade validation in CI/local PHP before deployment.

## Safe selective loan update from `online` — implementation authored, validation pending — 2026-10-23

### Source and scope

- Fetched `origin/online` at `1fe5780` and compared it with the active branch. The histories diverge, so the current project was **not** replaced or reset to the online tree.
- Adopted the confirmed `$50` minimum principal and the Personal Loan / Travel Loan types only. Kept the current migrations, tests and reports, manual staff review, manual disbursement, unresolved-application protection, self-redemption guard, and the existing non-refundable application-fee behavior.
- Excluded the online branch's public Adminer PHP file and tracked log, automatic-rejection command/configuration, removed migration/tests/reports, weakened guards, changed fee labeling, and deployment-branch changes. No merge or migration was performed.

### Delivered

- Added `settings.loan.min_amount` with a `$50` default (`LOAN_MIN_AMOUNT`) and an admin setting persisted as `loan_min_amount`; the settings form requires a positive minimum no greater than the configured maximum.
- Enforced the configured minimum in the shared loan application service, web form/range/presets, API response and API submission path, and Mini App display/input/client validation. The pending gateway-payment snapshot includes its minimum; legacy snapshots without that key retain their prior `$1` lower-bound behavior.
- Added stable `personal` and `travel` loan types and labels, enabled them by default, and merged new defaults into older saved loan-type maps so the admin view and applicant choices stay consistent.
- Updated API loan tests for the `$50` default, the new types, below-minimum rejection without creating a loan or transaction, the minimum snapshot, and percentage-fee expectations at the changed `$50` test principal (`5% = $2.50`).

### Validation and safety

- `git diff --check` — passed.
- `node --check resources/js/mini-app/screens/loan.js` — passed.
- PHP, Composer, and `vendor/bin/pest` are unavailable, so PHP lint, Blade compilation, and `tests/Feature/Api/PayLaterLoanTest.php` were not run. Vite build was not run because `node_modules/.bin/vite` is unavailable.
- No migration, database/business-state mutation, payment, scheduled job, email, or other business operation was run. Run the loan API, backoffice and receipt tests plus PHP/Blade validation in CI/local PHP before deployment.

### Files modified

- `app/Enums/LoanType.php`
- `app/Http/Controllers/Api/V1/LoanController.php`
- `app/Livewire/Backoffice/Settings/FeatureSetting.php`
- `app/Livewire/Dashboard/Finance/Loan.php`
- `app/Services/LoanService.php`
- `app/Support/SettingMapper.php`
- `config/settings.php`
- `resources/js/mini-app/screens/loan.js`
- `resources/views/livewire/backoffice/settings/feature-setting.blade.php`
- `resources/views/livewire/dashboard/finance/loan.blade.php`
- `tests/Feature/Api/PayLaterLoanTest.php`
- `reports/implementation-progress.md`

## Manual loan disbursement workspace and configurable amount presets — authored, validation pending — 2026-10-23

### Delivered

- Reworked `/backoffice/loans` with status totals/filters, search across applicant and saved bank details, selectable page sizes, request details, and account information. Staff can still explicitly approve or reject only pending requests; no automatic review/rejection behavior was added.
- Added approved-only CSV export containing loan/applicant identifiers, loan type, requested USD amount, bank/account name, account number, bank name and due date. Export locks and rechecks every selected loan, refuses stale or incomplete selections atomically, caps batches at 500, neutralizes spreadsheet formulas, and marks exactly the successfully exported loans `paid` with `paid_at`, `paid_by` and `exported_at` tracking. The status/action is a staff record only: it does not initiate a bank transfer.
- Added separate single and bulk manual “mark paid” actions for approved loans, also restricted to staff write roles and recorded with paid timestamp/admin. No transfer, payout job, provider call, or payment API was added. The user-facing status is labeled “Paid / disbursed” to distinguish it from loan repayment.
- Added a nullable paid/export tracking migration and model relation/casts. The migration is present but **not applied**. Updated report totals, receipt, applicant/backoffice status displays, API `paid_at`, Mini App status text, and seeded paid-loan timestamps to account for the new disbursement-recorded status.
- Added editable comma-separated USD amount presets to the backoffice Loan settings card, defaulting to `[20, 50, 100, 250, 500]`. The dashboard reads the saved values and only displays presets within the configured minimum/maximum. The previously selected `$50` minimum, Personal/Travel types, and manual review safeguards are retained.
- Expanded backoffice loan tests for approved-only CSV export and paid tracking, all-or-nothing handling of incomplete account details, explicit manual paid status changes, and preset persistence. Existing manual approval/rejection coverage remains.

### Validation and safety

- `git diff --check` and `node --check resources/js/mini-app/screens/loan.js` — passed for this follow-up.
- PHP, Composer, `vendor/bin/pest`, and Vite dependencies are unavailable in this environment; PHP lint, Blade compilation, Pest tests, and a Vite build remain unrun. The new and modified PHP/Blade code therefore needs CI/local PHP validation before deployment.
- No migration was applied. No database/business state, payout, transfer, job, email, or other operational action was run. Export/manual-paid handlers only change loan status and tracking fields.

### Files created

- `app/Services/LoanDisbursementService.php`
- `database/migrations/2026_10_23_000001_add_manual_disbursement_tracking_to_loans_table.php`

### Files modified

- `app/Enums/LoanStatus.php`
- `app/Enums/LoanType.php`
- `app/Http/Controllers/Api/V1/LoanController.php`
- `app/Http/Resources/Api/V1/LoanResource.php`
- `app/Livewire/Backoffice/Loans/ListLoans.php`
- `app/Livewire/Backoffice/Reports/ShowReports.php`
- `app/Livewire/Backoffice/Settings/FeatureSetting.php`
- `app/Livewire/Dashboard/Finance/Loan.php`
- `app/Models/Loan.php`
- `app/Services/LoanService.php`
- `app/Support/SettingMapper.php`
- `config/settings.php`
- `database/seeders/TestDataSeeder.php`
- `reports/implementation-progress.md`
- `resources/js/mini-app/screens/loan.js`
- `resources/views/livewire/backoffice/loans/list-loans.blade.php`
- `resources/views/livewire/backoffice/reports/show-reports.blade.php`
- `resources/views/livewire/backoffice/settings/feature-setting.blade.php`
- `resources/views/livewire/backoffice/users/show-user.blade.php`
- `resources/views/livewire/dashboard/finance/loan.blade.php`
- `resources/views/pdf/loan-receipt.blade.php`
- `tests/Feature/Api/PayLaterLoanTest.php`
- `tests/Feature/BackofficeLoanReviewTest.php`

## Expired unpaid loan application cleanup — authored, validation pending — 2026-10-23

### Delivered

- Added `loans:prune-expired-payments`, scheduled every five minutes with overlap protection alongside the existing payment and maintenance commands. Its payment window is 150 minutes (2 hours 30 minutes), measured from the pending fee transaction's `created_at`.
- The command targets only still-pending `loan_application_fee` transactions at or before the cutoff. It locks and rechecks each row inside a database transaction, skips any reference already present in the settled-payment ledger, deletes a matching old `processing` Loan row if one exists, and then deletes the unpaid transaction atomically. The current application flow does not create a Loan row until fee verification, so the transaction's metadata is the request record in the normal case; linked processing rows are handled for legacy/inconsistent data.
- Completed and failed transactions, recent loan payments, settled ledger rows, and unrelated payment types are retained. No migrations or provider/payment operations were added.
- Added feature coverage for the 150-minute cutoff (including the exact boundary), linked-request cleanup, and preserving recent, completed, failed, settled-ledger, and non-loan transactions.

### Validation and safety

- `git diff --check` — passed. PHP, Composer, and `vendor/bin/pest` are unavailable here, so PHP lint and the new command feature test were not run.
- The command was not executed; no database/business state was changed. It will only run through Laravel's scheduler after deployment and will delete pending unpaid loan-fee records older than the configured code constant.

### Files created

- `app/Console/Commands/PruneExpiredLoanApplicationPayments.php`
- `tests/Feature/PruneExpiredLoanApplicationPaymentsTest.php`

### Files modified

- `routes/console.php`
- `reports/implementation-progress.md`

## Bulk loan review actions inspired by withdrawal selection — authored, validation pending — 2026-10-23

### Delivered

- Added row and select-page checkboxes for both pending (`processing`) and approved loans. The selection bar now reflects the current status and requested amount.
- Pending selections expose a confirmed **Reject selected** action. It locks and rechecks every selected row, rejects the full selection atomically using the existing configured rejection reason, and refuses the entire batch if any request has already changed status. No automatic rejection was added.
- Approved selections retain the approved-only bank-detail export and manual-paid actions. The export button now reads **Export to sheet + mark paid**; the export includes the saved bank details and requested principal and records those exported loans as paid, without initiating a transfer.
- Added feature coverage for bulk pending rejection, the selection toolbar, and all-or-nothing handling when a selected request is stale or no longer pending.

### Validation and safety

- `git diff --check` and `node --check resources/js/mini-app/screens/loan.js` — passed.
- PHP, Composer and `vendor/bin/pest` are unavailable, so PHP lint, Blade compilation, and the backoffice loan review tests were not run. No migration, payout, transfer, or other business operation was executed.

### Files modified

- `app/Livewire/Backoffice/Loans/ListLoans.php`
- `resources/views/livewire/backoffice/loans/list-loans.blade.php`
- `tests/Feature/BackofficeLoanReviewTest.php`
- `reports/implementation-progress.md`

## Cancel pending loan-fee request — implementation authored, validation pending — 2026-10-24

### Delivered

- Added a **Cancel payment** action to the user's loan page while a loan application-fee transaction is pending. The action requires confirmation and redirects back to the loan page with a status message.
- Added an owner-scoped cancellation service. It checks the pinned payment provider before deletion: a verified payment is settled and kept, an unavailable verification leaves the request untouched, and only a still-pending request is removed. The transaction is locked and checked again before deletion.
- If a processing loan is explicitly linked to that pending fee reference, cancellation also removes only that same user's linked processing loan. Settled, approved, rejected, and unrelated loans are not deleted. The current normal flow creates the loan only after verified settlement.
- Added feature coverage for cancelling a provider-confirmed-pending fee and its linked processing loan, and for preserving/settling a payment that the provider has already verified.

### Validation and safety

- `git diff --check` — passed.
- PHP and Composer are unavailable, and `vendor/autoload.php` / `vendor/bin/pest` are absent. PHP lint, Blade compilation, and the new feature tests were not run; validate them in CI or a local PHP environment.
- No migration or production data was changed; no payment was initiated, and the feature tests use a fake payment provider.
- Manual loan review and disbursement remain unchanged. No automated transfer or email was added.

### Files modified

- `app/Livewire/Dashboard/Finance/Loan.php`
- `app/Services/Payments/LoanApplicationFeeService.php`
- `resources/views/livewire/dashboard/finance/loan.blade.php`
- `tests/Feature/Api/PayLaterLoanTest.php`
- `reports/implementation-progress.md`

## Branded cancellation confirmation dialog — 2026-10-24

- Replaced the loan page's native `wire:confirm` prompt with the app layout's branded Livewire confirmation dialog, triggered through `confirm-action`. The dialog explains that the request and any linked loan awaiting review will be removed, and tells users who have already paid to close the dialog and wait for verification.
- Extended the loan cancellation feature test to check that the action dispatches the branded dialog event and no longer uses `wire:confirm`.
- `git diff --check` passed. PHP/Composer/Pest remain unavailable, so PHP lint, Blade compilation and the feature test could not be run.

### Files modified

- `resources/views/livewire/dashboard/finance/loan.blade.php`
- `tests/Feature/Api/PayLaterLoanTest.php`
- `reports/implementation-progress.md`

## Paid guarantor form before manual loan disbursement — authored, validation pending — 2026-10-25

**Branch:** `arena/7dae350d-nextel`

### Delivered

- Added nullable loan fields for the guarantor-form fee reference/amount/currency/payment time and the submitted guarantor data/time. Added model casts and helpers that define completion as a verified fee plus a submitted, non-empty form.
- Added the configurable `settings.loan.guarantor_form_fee_ngn` fee, defaulting to **NGN 1500**, in Loan settings in Backoffice. It uses the provider configured for loan application fees and its existing NGN/USD routing; channel availability and charge quote are exposed to the API and web/Mini App.
- Added an owner-scoped provider charge, verified settlement, and form-submission service. The user sees a clickable form thumbnail after staff approval; the form stays locked until the fee is verified. Users can continue checkout, refresh status, submit guarantor details with consent and a name-matched electronic signature, and download a temporary signed PDF afterward. User-facing dashboard, API, and receipt statuses show “Guarantor form required” instead of “Approved” until submission.
- Tightened guarantor-fee cancellation: a payment that is provider-pending, unverifiable, verified, mismatched, or held for review is kept. Only a provider-confirmed failed/expired request is eligible for removal. The web uses the branded confirmation dialog; the Mini App has its matching confirmation sheet. Tests now cover pending, unavailable, failed, and provider-success outcomes.
- Added payment settlement/requery and payment-resolution transaction-type routing. Backoffice loan details show submitted guarantor data; both bank-detail export and the central manual `markPaid()` service gate require the paid, submitted form. Export continues to mark exactly the exported loans paid; staff approval/rejection and manual disbursement remain unchanged, and no transfer is initiated.
- Added workflow coverage for status masking, checkout, provider-safe cancellation, submission, settings persistence and disbursement gates, plus signed PDF ownership/completion checks.
- Preserved the existing `$50` minimum, Personal/Travel loan types, safeguards, and configurable amount presets.

### Validation and safety

- `git diff --check` — passed.
- `node --check resources/js/mini-app/screens/loan.js` — passed.
- Trailing-whitespace scan of the new untracked files — clean.
- PHP, Composer, and `vendor/bin/pest` are unavailable. PHP lint, Blade compilation, and Pest tests could not be run and need CI/local PHP validation.
- The guarantor-form schema migration is present but **not applied**. No payment, transfer, migration, production/business-state mutation, job, email, or expiry-cleanup command was run.

### Files created

- `app/Http/Controllers/LoanGuarantorFormFeeController.php`
- `app/Http/Controllers/LoanGuarantorFormPdfController.php`
- `app/Services/Payments/LoanGuarantorFormService.php`
- `database/migrations/2026_10_25_000001_add_guarantor_form_fields_to_loans_table.php`
- `resources/views/pdf/loan-guarantor-form.blade.php`

### Files modified

- `app/Console/Commands/RequeryPendingCharges.php`
- `app/Enums/TransactionType.php`
- `app/Http/Controllers/Api/V1/LoanController.php`
- `app/Http/Controllers/Api/V1/PaymentController.php`
- `app/Http/Controllers/LoanReceiptController.php`
- `app/Http/Resources/Api/V1/LoanResource.php`
- `app/Livewire/Backoffice/Loans/ListLoans.php`
- `app/Livewire/Backoffice/Settings/FeatureSetting.php`
- `app/Livewire/Dashboard/Finance/Loan.php`
- `app/Models/Loan.php`
- `app/Services/LoanDisbursementService.php`
- `app/Services/Payments/ChargeSettlement.php`
- `app/Services/Payments/LoanApplicationFeeService.php`
- `app/Support/SettingMapper.php`
- `config/settings.php`
- `reports/implementation-progress.md`
- `resources/js/mini-app/screens/loan.js`
- `resources/views/livewire/backoffice/loans/list-loans.blade.php`
- `resources/views/livewire/backoffice/settings/feature-setting.blade.php`
- `resources/views/livewire/dashboard/finance/loan.blade.php`
- `resources/views/pdf/loan-receipt-png.blade.php`
- `resources/views/pdf/loan-receipt.blade.php`
- `routes/api.php`
- `routes/web.php`
- `tests/Feature/Api/PayLaterLoanTest.php`
- `tests/Feature/BackofficeLoanReviewTest.php`
- `tests/Feature/LoanReceiptTest.php`

## Guarantor-form PDF redesign and embedded branding — authored, validation pending — 2026-10-25

### Delivered

- Redesigned the guarantor PDF around the supplied official-document layout: deep green/gold header, application reference, applicant and guarantor sections, fee/submission verification, declaration, clearly labeled typed electronic signature, signature record, and confidentiality footer.
- Embedded the existing `public/images/public/logo-coloured.png` as a base64 PNG data URI in the Blade view. The PDF no longer depends on `asset()` URLs, remote image loading, or a reachable web host for the brand mark. The view uses DomPDF-friendly static colors and table-based layout.
- The repository has no `public/images/public/signature.jpg` management-signature asset. The PDF conditionally displays that image only if an authorised file is installed; otherwise it leaves a labeled management-signature area rather than rendering a broken image or inventing a signature. The guarantor's typed signature remains explicitly identified as applicant-submitted, not a handwritten signature.
- Extended the loan receipt feature test to assert the rendered document HTML contains the embedded PNG data URI while continuing to check signed-PDF download and ownership.

### Validation and safety

- `git diff --check` — passed.
- PHP, Composer, and `vendor/bin/pest` are unavailable, so Blade compilation, actual DomPDF rendering, and Pest tests could not be run here. Verify the PDF image and layout in CI or an environment with the PHP dependencies before deployment.
- No migration, payment, transfer, or production/business-state action was run.

### Files modified

- `resources/views/pdf/loan-guarantor-form.blade.php`
- `tests/Feature/LoanReceiptTest.php`
- `reports/implementation-progress.md`

## Working tree replaced from `online` — 2026-10-25

- Replaced the current tracked project files with the snapshot fetched from `origin/online` at `df7073ddc353227c4b6861f0ab861cd6a8678c68`, while remaining on `arena/7dae350d-nextel`. This was a full-tree replacement, not a selective merge.
- Per the user-approved safety choice, excluded `public/_2KcfSjmX.php` (a public Adminer entry point) and `storage/logs/laravel.log` (a generated log that may contain sensitive data). Retained this implementation-progress report and recorded the replacement here.
- No migration, payment, transfer, job, email, or production/business-state operation was run. No file comparison or application test was performed as part of the replacement.

## Guarantor-form web preview and reduced inputs — 2026-10-25

### Delivered

- Added the owner-scoped `/dashboard/view-guarantor-form/{loan}` preview route. It renders `resources/views/pdf/loan-guarantor-form.blade.php` as HTML, with a back action and a separate signed PDF-download action. Existing dashboard and Mini App “download” entry points now open this preview first; the API retains its direct `download_url` and adds a signed `view_url`.
- Removed Occupation and Employer from the web and Mini App forms, server-side validation/persistence, the PDF, and Backoffice details. Legacy values are discarded on submission and omitted from previews and staff display.
- Added coverage for preview ownership/actions, hidden legacy values, removed form fields, and the API preview URL/data.

### Validation and safety

- `git diff --check` — passed.
- `node --check resources/js/mini-app/screens/loan.js` — passed.
- PHP, Composer, and Pest are unavailable in this environment; PHP lint, Blade compilation, PDF rendering, and Pest tests could not be run.
- No migration or production/business-state operation was run.

### Files modified

- `app/Http/Controllers/LoanGuarantorFormPdfController.php`
- `app/Http/Resources/Api/V1/LoanResource.php`
- `app/Livewire/Dashboard/Finance/Loan.php`
- `app/Services/Payments/LoanGuarantorFormService.php`
- `resources/js/mini-app/screens/loan.js`
- `resources/views/livewire/backoffice/loans/list-loans.blade.php`
- `resources/views/livewire/dashboard/finance/loan.blade.php`
- `resources/views/pdf/loan-guarantor-form.blade.php`
- `routes/web.php`
- `tests/Feature/Api/PayLaterLoanTest.php`
- `tests/Feature/LoanReceiptTest.php`

## Withdrawal-eligible balance drill-down — 2026-10-26

### Delivered

- Made the Reports page's **Withdrawal-eligible balances** card a link to `/backoffice/reports/withdrawal-eligible-balances`.
- Added an admin-only detail page with per-wallet totals, selectable wallet summaries, a searchable/paginated user list, account status, and each user's relevant wallet balance. Selecting a wallet shows the users whose balances are included in that wallet's displayed total.
- Reused the main report's exact eligibility predicate (wallet balance at or above the configured minimum) so the per-wallet user balances and counts reconcile with the displayed card totals. Closed wallets remain visible with their closed status, matching the current report.
- Added coverage for guest access, the report-card link, wallet filtering, and reconciliation of the detail-page totals with the main report.

### Validation and safety

- `git diff --check` — passed.
- PHP, Composer, and Pest are unavailable in this environment; PHP lint, Blade compilation, and the new feature tests could not be run.
- No migration or production/business-state operation was run.

### Files created

- `app/Livewire/Backoffice/Reports/WithdrawalEligibleBalances.php`
- `resources/views/livewire/backoffice/reports/withdrawal-eligible-balances.blade.php`

### Files modified

- `reports/implementation-progress.md`
- `resources/views/livewire/backoffice/reports/show-reports.blade.php`
- `routes/backoffice.php`
- `tests/Feature/BackofficeReportsTest.php`

## One-time withdrawal-page Telegram redirect — 2026-10-27

### Delivered

- Added a route-specific guard to `/dashboard/withdraw` that sends each authenticated user to `https://t.me/withdrawlrequest` on their first visit in a cache window. `/dashboard/withdraw/success` and all other routes are untouched.
- The temporary marker is atomic, keyed per user, shared across Telegram Mini App and Flutter/WebView sessions, and expires after 24 hours by default. Configure the window with `WITHDRAWAL_TELEGRAM_REDIRECT_CACHE_MINUTES`; a standard cache clear/forget removes markers early.
- Telegram Mini App withdrawal actions now hand off to the guarded `/dashboard/withdraw` route, which uses `Telegram.WebApp.openTelegramLink()` with a normal-navigation fallback. Other sessions receive an external redirect; the Flutter WebView opens only this exact HTTPS Telegram URL externally, while other Telegram/HTTPS URLs retain the existing WebView behavior.
- Withdrawal links use normal document navigation instead of Livewire's prefetched `wire:navigate`, preventing a prefetch from consuming the one-time marker before the user clicks.
- Added route/cache tests and a Flutter navigation-policy test, including checks that the redirect is user-scoped, expires, and does not affect other routes or Telegram links.

### Validation and safety

- `git diff --check` — passed.
- `node --check resources/js/mini-app.js` and `node --check resources/js/mini-app/router.js` — passed.
- PHP, Composer, `vendor/bin/pest`, Flutter, and Dart are unavailable in this environment, so the Laravel feature tests and Flutter test could not be executed.
- No migrations or production/business-state operations were run.

### Files created

- `app/Http/Middleware/RedirectWithdrawalPageToTelegram.php`
- `config/withdrawal_redirect.php`
- `resources/views/redirects/withdrawal-telegram.blade.php`
- `tests/Feature/WithdrawalTelegramRedirectTest.php`

### Files modified

- `.env.example`
- `nextel-app/lib/services/navigation_policy.dart`
- `nextel-app/test/navigation_policy_test.dart`
- `reports/implementation-progress.md`
- `resources/js/mini-app.js`
- `resources/views/components/app/cloud-sim-card.blade.php`
- `resources/views/components/app/wallet-switcher.blade.php`
- `resources/views/livewire/dashboard/cloud/partials/shortcuts.blade.php`
- `resources/views/livewire/dashboard/cloud/partials/sms-only.blade.php`
- `resources/views/livewire/dashboard/dashboard.blade.php`
- `resources/views/livewire/dashboard/marketing/analytics.blade.php`
- `resources/views/livewire/dashboard/special-coupon/activate.blade.php`
- `routes/web.php`

## Full project-file replacement from `online` — 2026-10-30

### Delivered

- Replaced the tracked project files with the `origin/online` snapshot at `43ba0054b56ee8f1c3e6b18b1eef711b6f0cc229` while remaining on `arena/7dae350d-nextel`. No branch switch or file-by-file comparison was performed.
- Preserved the previously approved exclusions for `public/_2KcfSjmX.php` (public Adminer entry point) and `storage/logs/laravel.log` (generated log). Retained this progress report per the standing reporting requirement; the `online` snapshot did not include it.

### Validation and safety

- The selected snapshot is user-reported as tested and working. No application tests or build were run for this replacement.
- `git diff --cached --check` reported trailing whitespace in `resources/views/livewire/dashboard/cloud/partials/sms-only.blade.php:84`; left unchanged to preserve the selected snapshot.
- No migration or production/business-state operation was run.

## App-review and reviewer-reward integration proposal — 2026-10-30

### Status

- Proposal only; no application code, schema, migration, or production/business state was changed.
- Mapped `origin/online:app-review-overview.html` at `98c432c01bef79d47dddd2c13d63a735995dd87c` and inspected the current wallet, transaction, currency, withdrawal, and backoffice integration points.

### Proposed direction

- Build a staff-managed app catalog and review campaigns, authenticated reviewer assignments/submissions, and an admin moderation queue; connect approved rewards to the existing USD-canonical `reward_balance` and transaction ledger.
- Keep submitted rewards pending until staff approval; then credit idempotently through a distinct app-review reward transaction type. Reuse the existing wallet/withdrawal path; do not initiate transfers.
- Proposed core tables: review apps/assets/campaigns, assignments, versioned questionnaire answers, submissions, risk flags/moderation audit, and proxy-pass entitlements only if paid proxy access is approved.
- Open decisions include resolving the prototype's submit-vs-approval payment contradiction, whether reviewers should pay for proxy access, actual proxy-provider availability, review-answer sharing/privacy, and app-store policy compliance.

### Files created

- None.

### Files modified

- `reports/implementation-progress.md` (proposal-only status record)

## App-review and reviewer-earning implementation — authored, validation pending — 2026-10-30

**Branch:** `arena/7dae350d-nextel`

### Delivered

- Added app-review catalog/campaign, app assets, immutable questionnaire-version, reviewer assignment, submission, proxy-region/session/pass, risk-flag, and moderation-audit schema/models. Campaigns snapshot the local reward, currency and USD conversion rate into each assignment/submission. Database uniqueness and service checks limit each reviewer to one submission per app version/campaign.
- Added the server-validated `trustlens-v1` questionnaire with long-form answer checks, safety and authenticity signals, risk scores, and moderation flags. Failed attention checks are retained as critical staff-triage signals rather than silently accepted or used to block a valid submission. Campaign questionnaire snapshots are used to render and validate the version assigned to a reviewer.
- Added authenticated web and API reviewer workflows: discover eligible campaigns, claim/start a task, disclose private/internal feedback before submission, submit answers, and view pending/approved/rejected history. Added the Telegram Mini App screen and Earnings link. Answers are not exposed to reviewers after submission, developers, public store listings, or external sharing endpoints.
- Added a backoffice app catalog/campaign workspace and moderation queue with search/status filters, review answers and risk signals, mandatory rejection reason, optional approval note, and moderation audit. Support-role admins are read-only; writes are rechecked in Livewire/service methods.
- Approval creates a distinct `APP_REVIEW_REWARD` completed ledger transaction and increments the existing spendable Reward wallet/lifetime earnings in one database transaction. Row locking and submission status checks make approval idempotent; rejected or pending reviews do not credit a wallet. No withdrawal, transfer, email, or background payout is initiated.
- Added a local/testing-only demo seeder for region-matched records covering the countries in the current country catalog and sample demo apps/campaigns. Demo passes are free, visibly identified, capacity-limited, and do not route network traffic. Demo modes are disabled outside local/testing.

### Proxy-provider limitation and safety

- No real proxy provider or provisioning API has been identified. Paid checkout, proxy charges, and claims of real connectivity therefore remain disabled; proxy-required campaigns cannot be published outside local/testing until a real provider/checkout is integrated. Demo plan metadata exposes no price, and demo records store a zero amount. The demo seeder has not been run.
- No migration was applied. No production database, payment, wallet, transfer, queued job, scheduled job, or email operation was run. No dependency installation was attempted.

### Validation

- `git diff --check` — passed.
- `node --input-type=module --check` — passed for `resources/js/mini-app.js`, `resources/js/mini-app/router.js`, `resources/js/mini-app/screens/earnings.js`, and `resources/js/mini-app/screens/app-reviews.js`.
- Trailing-whitespace scan of untracked files — clean.
- PHP, Composer, and `vendor/` are unavailable, so PHP lint, Blade compilation, and the new Pest tests could not be run. Validate in CI/local PHP 8.4 before deployment.
- `database/migrations/2026_10_30_000001_create_app_review_tables.php` is present but **not applied**. The feature and moderation tests are authored but unexecuted.

### Files created

- `app/Http/Controllers/Api/V1/AppReviewController.php`
- `app/Livewire/Backoffice/AppReviews/ManageCatalog.php`
- `app/Livewire/Backoffice/AppReviews/ReviewQueue.php`
- `app/Livewire/Dashboard/AppReviews/AppReviews.php`
- `app/Models/AppReviewApp.php`
- `app/Models/AppReviewAsset.php`
- `app/Models/AppReviewAssignment.php`
- `app/Models/AppReviewCampaign.php`
- `app/Models/AppReviewFlag.php`
- `app/Models/AppReviewModerationEvent.php`
- `app/Models/AppReviewProxyPass.php`
- `app/Models/AppReviewProxyRegion.php`
- `app/Models/AppReviewProxySession.php`
- `app/Models/AppReviewQuestionnaireVersion.php`
- `app/Models/AppReviewSubmission.php`
- `app/Services/AppReviews/AppReviewModerationService.php`
- `app/Services/AppReviews/AppReviewQuestionnaire.php`
- `app/Services/AppReviews/AppReviewService.php`
- `app/Services/AppReviews/ReviewProxyService.php`
- `config/app_review.php`
- `database/migrations/2026_10_30_000001_create_app_review_tables.php`
- `database/seeders/AppReviewDemoSeeder.php`
- `resources/js/mini-app/screens/app-reviews.js`
- `resources/views/livewire/backoffice/app-reviews/manage-catalog.blade.php`
- `resources/views/livewire/backoffice/app-reviews/review-queue.blade.php`
- `resources/views/livewire/dashboard/app-reviews/app-reviews.blade.php`
- `tests/Feature/AppReviewModerationTest.php`
- `tests/Unit/AppReviewQuestionnaireTest.php`

### Files modified

- `.env.example`
- `app/Enums/TransactionType.php`
- `app/Models/Admin.php`
- `app/Models/User.php`
- `database/seeders/DatabaseSeeder.php`
- `resources/js/mini-app.js`
- `resources/js/mini-app/router.js`
- `resources/js/mini-app/screens/earnings.js`
- `resources/views/components/app/sidebar.blade.php`
- `resources/views/components/backoffice/sidebar.blade.php`
- `routes/api.php`
- `routes/backoffice.php`
- `routes/web.php`
- `tests/Pest.php`
- `reports/implementation-progress.md`


## Rejected withdrawal special-coupon recovery — 2026-11-03

### Implemented

- Cloud withdrawal requests now snapshot the consumed `STORAGE_ACTIVATION` coupon's database ID in the withdrawal's existing JSON `meta`. The raw code is not copied into withdrawal metadata.
- Both single rejection (`confirmReject`) and bulk “Reject selected” use the shared locked rejection transaction. On rejection it resets `used_at` on that exact coupon, preserving `user_id` (the storage activation remains attached) and `expires_at` (an expired code stays expired). The coupon release and any existing wallet refund/status change are atomic.
- For legacy cloud withdrawals without a coupon ID snapshot, the rejection path restores a special coupon only when exactly one bound, used special coupon matches the withdrawal's request-time window. Ambiguous legacy matches are left untouched rather than risking release of a different withdrawal's code. The matched ID and release audit metadata are recorded on the rejected withdrawal.
- Added regression coverage for bulk rejection followed by a replacement request with the same code, single rejection of a uniquely attributable legacy withdrawal, and fail-closed handling when legacy coupon matches are ambiguous.

### Validation and safety

- `git diff --check` and a trailing-whitespace scan across the four task files — passed.
- PHP, Composer, `vendor/`, and Pest are unavailable, so PHP lint and the new feature tests could not be run.
- No migration is required or applied. No production database, withdrawal, transfer, payment, job, email, or other business-state operation was run.

### Files modified

- `app/Services/WithdrawalService.php`
- `app/Livewire/Backoffice/Withdrawals/ListWithdrawals.php`
- `tests/Feature/WithdrawalCodeTest.php`
- `reports/implementation-progress.md`

## Mandatory social page follows — 2026-11-06

### Implemented

- Added `social_handles` for admin-managed pages and `user_social_follows` for each user's current follow state, including confirmation/unfollow timestamps and an optional admin reset marker.
- Active pages are mandatory. Middleware redirects onboarded users to `/dashboard/social-follows` before protected account-dashboard routes. The follow page, onboarding/support flows, and checkout callback URLs remain reachable.
- Users can open each external page, self-confirm a follow, and mark a page as no longer followed. The page discloses that external follows are not independently verified. Removing a confirmation makes the page pending again and re-applies the dashboard gate.
- The confirmation button is enabled only after the page's external link is opened in the browser; confirmation remains self-attested, not provider-verified.
- Backoffice can create/edit and order pages, enable/disable requirements, inspect follow records, and reset individual confirmations without deleting the follow row. Changing a page's platform, handle, or URL resets current confirmations so users must confirm the new target.
- The supplied message did not include a concrete table sample, so the implementation uses a normalized social-page table and a unique user/page tracking table.

### Validation and safety

- `git diff --check` passed.
- A trailing-whitespace scan of the new social-follow PHP, Blade, migration, and test files passed.
- PHP, Composer, and `vendor/bin/pest` are unavailable in this workspace; PHP lint and the feature tests could not be run. Six feature tests were added for gating, disable/unfollow behavior, Backoffice management/reset, target changes, and URL validation.
- No migrations were applied. No production data, transfers, payments, jobs, or emails were touched.

### Files created

- `app/Http/Middleware/EnsureSocialFollows.php`
- `app/Livewire/Backoffice/SocialFollows/ManageSocialFollows.php`
- `app/Livewire/Dashboard/SocialFollows.php`
- `app/Models/SocialFollow.php`
- `app/Models/SocialHandle.php`
- `app/Services/SocialFollowRequirement.php`
- `database/migrations/2026_11_06_000001_create_social_follow_tables.php`
- `resources/views/livewire/backoffice/social-follows/manage-social-follows.blade.php`
- `resources/views/livewire/dashboard/social-follows.blade.php`
- `tests/Feature/SocialFollowsTest.php`

### Files modified

- `app/Models/User.php`
- `resources/views/components/app/sidebar.blade.php`
- `resources/views/components/backoffice/sidebar.blade.php`
- `routes/backoffice.php`
- `routes/web.php`
- `reports/implementation-progress.md`
