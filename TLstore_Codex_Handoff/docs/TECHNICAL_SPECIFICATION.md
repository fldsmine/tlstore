# TLstore Technical Specification and Implementation Plan

Version: 1.0
Planning date: 2026-10-29
Status: Proposed specification; business decisions remain subject to confirmation.
Flutter/Dart: - android,web and ios
Application ID: pynith.tlstore.review

## 1. Goals
Build an app-trust assessment platform where users perform structured, evidence-based app assessments and eligible advertiser-funded tasks. Administrators manage app listings, questionnaires, campaigns, memberships, task validation, rewards, financial operations, referrals, rankings, regional eligibility, support and reporting.

The system must not pay for positive public app-store ratings, fabricated reviews, artificial ad engagement, or circumvention of platform/advertiser rules.

## 2. Architecture
- `mobile/`: Flutter/Dart, Riverpod, GoRouter, Dio, secure token storage, feature-first Clean Architecture.
- `backend/`: Laravel REST API and Blade/Livewire admin in one application initially.
- API prefix `/api/v1`; JSON contracts documented with OpenAPI.
- MySQL/InnoDB; Redis/cache/queues where useful.
- Laravel Sanctum bearer tokens for mobile; separate session authentication for admin.
- FCM for push notifications; private S3-compatible storage for evidence.
- Provider adapters for Paystack, Korapay and BACHS Pay, using current official docs.
- Deploy API/admin behind HTTPS; database and Redis private; use supervised queue workers, scheduler, monitoring, backups and restore tests.

## 3. Suggested repository
- `mobile/lib/app`: router, theme, bootstrap
- `mobile/lib/core`: API, auth, errors, storage, widgets, utilities
- `mobile/lib/features`: authentication, dashboard, app catalog, assessments, advertising, memberships, wallet, referrals, rankings, notifications, profile
- `backend/app/Domain`: Identity, Assessments, Advertising, Memberships, Wallet, Payments, Referrals, Rankings
- `backend/app/Http`: controllers, middleware, requests, resources
- `backend/app/Policies`, `Jobs`, `Listeners`, `Models`, `Services`
- `backend/database/migrations`, `factories`, `seeders`
- `backend/routes/api.php`, `web.php`
- `backend/tests`, `backend/docs/openapi.yaml`
- root `docs/`: architecture, business rules, API contracts, ERD, deployment runbook

## 4. Database domains and proposed tables

### Identity and access
`users`, `user_profiles`, `roles`, `permissions`, `role_user`, `permission_role`, `personal_access_tokens`, `user_devices`, `user_consents`, `password_reset_tokens`.

### App catalog and assessments
`app_publishers`, `apps`, `app_stores`, `app_store_listings`, `app_versions`, `app_screenshots`, `questionnaires`, `questionnaire_questions`, `question_options`, `review_tasks`, `task_assignments`, `assessment_submissions`, `assessment_answers`, `assessment_evidence`, `assessment_reviews`, `assessment_reports`.

### Advertising
`advertisers`, `ad_campaigns`, `ad_creatives`, `campaign_targeting_rules`, `campaign_reward_rules`, `campaign_events`, `campaign_daily_stats`, `campaign_budget_reservations`.

### Memberships
`packages`, `package_features`, `package_limits`, `subscriptions`, `subscription_payments`, `subscription_events`.

### Finance
`currencies`, `wallet_accounts`, `ledger_accounts`, `journal_entries`, `journal_lines`, `wallet_balance_snapshots`, `payment_transactions`, `payment_webhook_events`, `withdrawal_requests`, `payout_attempts`, `refund_transactions`, `exchange_rates`, `financial_adjustments`.

### Referrals and rankings
`referral_relationships`, `referral_commission_rules`, `referral_commission_entries`, `rank_definitions`, `rank_qualifications`, `rank_rewards`.

### Regional access and operations
`regions`, `task_region_rules`, `access_plans`, `user_access_grants`, `notifications`, `support_tickets`, `support_messages`, `risk_flags`, `risk_reviews`, `admin_audit_logs`, `system_settings`, `outbox_events`, `scheduled_reports`.

Use foreign keys, indexes, unique constraints and transaction-safe updates. Use DECIMAL for money, not DOUBLE/FLOAT. Preserve questionnaire versions. Use ULID/UUID public identifiers if desired. Seeders must be idempotent and must never create fake production wallet balances.

## 5. Migration ordering
1. Identity and access control.
2. Currencies and ledger foundation.
3. App catalog and publishers.
4. Questionnaires and options.
5. Tasks, assignments, submissions and evidence.
6. Advertisers and campaigns.
7. Packages and subscriptions.
8. Payments, journals and wallet operations.
9. Referrals, commissions and rankings.
10. Regional access.
11. Notifications, support, risk and audit.
12. Reporting indexes, outbox and operational optimizations.

## 6. API endpoint inventory
Base: `/api/v1`.

### Auth/profile
- POST `/auth/register`
- POST `/auth/login`
- POST `/auth/logout`
- POST `/auth/forgot-password`
- POST `/auth/reset-password`
- POST `/auth/email/verification-notification`
- GET `/auth/me`
- GET/PATCH `/profile`
- PUT `/profile/password`
- GET `/devices`
- DELETE `/devices/{device}`

### Dashboard and assessments
- GET `/dashboard`
- GET `/apps`, GET `/apps/{app}`
- GET `/assessments`
- POST `/assessments/{task}/claim`
- GET `/assignments/{assignment}`
- POST `/assignments/{assignment}/start`
- PUT `/assignments/{assignment}/answers`
- POST `/assignments/{assignment}/evidence`
- POST `/assignments/{assignment}/submit`
- GET `/assignments/{assignment}/status`
- POST `/assignments/{assignment}/report`
- GET `/assessment-history`

### Advertising
- GET `/campaigns`, GET `/campaigns/{campaign}`
- POST `/campaigns/{campaign}/claim`
- POST `/campaigns/{campaign}/events`
- GET `/campaign-events`

### Packages and payments
- GET `/packages`
- GET `/subscriptions/current`
- POST `/subscriptions/checkout`
- POST `/subscriptions/{subscription}/cancel`
- GET `/payments/{payment}`
- POST `/payments/{payment}/verify`
- POST `/webhooks/paystack`
- POST `/webhooks/korapay`
- POST `/webhooks/bachs-pay`

### Wallet
- GET `/wallet`
- GET `/wallet/transactions`
- GET `/wallet/deposit-options`
- POST `/wallet/deposits`
- GET `/wallet/withdrawal-options`
- POST `/wallet/withdrawals`
- GET `/wallet/withdrawals`
- GET `/wallet/withdrawals/{withdrawal}`
- POST `/wallet/withdrawals/{withdrawal}/cancel`

### Referrals, rankings, notifications and support
- GET `/referrals/summary`, `/referrals/tree`, `/referrals/commissions`
- GET `/rankings`, `/rankings/progress`
- GET `/notifications`
- POST `/notifications/{notification}/read`
- POST `/notifications/read-all`
- GET/POST `/support/tickets`
- GET `/support/tickets/{ticket}`
- POST `/support/tickets/{ticket}/messages`

These are proposed routes, not existing endpoints. Define exact request/response schemas and authorization in OpenAPI. Use consistent pagination and errors, HTTP 422 validation, 401 unauthenticated, 403 unauthorized, 404 inaccessible/nonexistent, 409 conflicts and 429 rate limits. Provider webhooks use provider authentication, not user bearer tokens.

## 7. Admin modules
Dashboard; users; app catalog; questionnaire builder; task management; assessment moderation and appeals; advertisers; campaigns; packages; subscriptions; payments; ledger and reconciliation; withdrawals; referrals; rankings; regions; support; notifications; risk/compliance; settings; reporting/export.

Suggested roles: Super Admin, Operations Manager, Finance Officer, Content Manager, Compliance Officer and Support Agent. Enforce named permissions server-side. Use maker-checker approvals for higher-risk payouts and adjustments.

## 8. Flutter screens
Onboarding, registration, login, verification, dashboard, app catalog, assessment details/instructions/questions/evidence/submission/history, campaign catalog/details/event status, package comparison/checkout/subscription history, wallet/balances/deposit/withdrawal/transactions, referrals, rankings, notifications, profile/security/devices, support tickets.

All async screens need loading, success, empty, error/retry and form-validation states. Cache only appropriate data. Never generate financial outcomes offline.

Application id & package name use 'pynith.tlstore.review' for flutter app;

## 9. Task workflow
Eligibility check -> atomic assignment -> assessment -> validated submission -> moderation -> decision -> idempotent ledger posting.

States include available, assigned, in_progress, submitted, under_review, approved, rejected, expired, cancelled and withdrawn where appropriate. Enforce transitions in a domain service. Prevent duplicate claims and over-allocation under concurrency.

## 10. Financial invariants
- Append-only double-entry ledger; each posted journal balances per currency.
- Never mutate posted journal lines; corrections use reversal entries.
- Idempotency keys and unique source references for payments, rewards, commissions and payouts.
- Provider callbacks must be authenticated and validated against amount, currency, merchant and transaction status.
- Client-supplied success, balance, reward amount and task state are never trusted.
- Reserve withdrawal funds before payout; handle failure through auditable release/reversal.
- Keep available, pending, reserved and held balances conceptually distinct.
- Record currency conversion rate, source, timestamp and rounding policy.
- Referral rates from the initial proposal are 5%, 2%, 1% for three levels, but qualifying event/base, reversal behavior and legal approval must be decided before activation.
- Package pricing, thresholds, daily caps, minimum withdrawal and subscription renewal behavior need explicit approved business rules.
- No guaranteed-return or investment-style earning promises without appropriate legal review.

## 11. Security
- Sanctum bearer auth for mobile; separate session auth for admin.
- Policies on every resource/action; avoid IDOR.
- Rate limiting, verification, token revocation, secure cookies and CSRF protection.
- Validate uploads; keep evidence private.
- Protect secrets and redact logs.
- Audit sensitive admin and finance actions.
- Test race conditions, duplicate webhooks and concurrent task claims.
- Never deploy or execute destructive database commands without explicit approval.

## 12. Testing
Backend: auth, policies, task state transitions, concurrency/caps, evidence access, idempotent rewards, webhook validation, withdrawal reservations, reversals, referral uniqueness, currency rounding and audit trails.
Flutter: widget/unit/integration tests, routing, session expiry, retries, empty states, payment status and withdrawal flows.
Admin: permissions, moderation, campaign budgets, maker-checker, exports and audit.
Security/ops: IDOR, CSRF, XSS, upload safety, rate limits, queue retries, load tests, backup restore and incident response.

## 13. Phases
0. Confirm requirements, business rules, ERD, OpenAPI and wireframes.
1. Laravel skeleton, migrations, auth, policies, API conventions, audit and CI.
2. Livewire admin foundation, catalog, questionnaire builder and moderation.
3. Flutter auth, dashboard, catalog, assessment flow and history.
4. Ledger, provider adapters, subscriptions, rewards, withdrawals and reconciliation.
5. Campaigns, referrals, rankings, regional eligibility, support and reporting.
6. Security review, load testing, backups, monitoring, staging and controlled production rollout.

## 14. Production acceptance gates
- No unauthorized access to another user's data.
- Payment callbacks cannot credit twice.
- Reward source events cannot post twice.
- Task caps hold under concurrency.
- Withdrawals cannot overspend reserved funds.
- Ledger reconciliation works.
- Admin financial permissions and audit trails work.
- Critical CI tests pass.
- Monitoring and backup restoration verified.
