# API v1

All API routes use `/api/v1`. JSON validation errors use Laravel's standard `422` response. User routes require a user Sanctum token; `/admin/*` routes require an active token belonging to an `Admin` record. Admin tokens cannot call user routes, and user tokens cannot call admin routes.

## Public and account routes

| Method | Path | Purpose |
|---|---|---|
| `GET` | `/health` | API health |
| `GET` | `/app/version?platform=&build=` | Return latest published Android/iOS release and update requirement |
| `GET` | `/countries` | Available registration countries |
| `GET` | `/regions` | Available admin-defined regions and included active countries |
| `GET` | `/faqs` | Active FAQ and help-center entries ordered for display |
| `GET` | `/subscription-plans?product_type=` | Active marketplace or location plans |
| `GET` | `/payment-gateways` | Currently exposed active gateways |
| `GET` | `/exchange-rates?base=&quote=` | Dated FX snapshots for display |
| `GET` | `/currencies` | Currency codes and minor-unit precision |
| `POST` | `/auth/register` | Create user, wallet records and referral code |
| `POST` | `/auth/login` | Email/password login |
| `POST` | `/auth/forgot-password`, `/auth/reset-password` | Request a reset token or set a new password |
| `POST` | `/auth/social/{provider}` | Verify Google, Facebook, or Telegram credentials; create or sign in a linked user |
| `POST` | `/guest-support` | Public contact form — no token, returns a ticket reference and emails the guest plus the support inbox |
| `POST` | `/admin/auth/login` | Admin login |

### Public contact form (`POST /api/v1/guest-support`)

Open to anyone, signed in or not; rate limited per source address (4/minute) and per email
address (6/hour) by the `guest-support` limiter. Storefront page: `GET /contact` on the
marketing site (same validation contract, CSRF-protected form).

```json
{ "name": "Ada Lovelace", "email": "ada@example.com",
  "subject": "Cannot withdraw my balance", "message": "I tried twice today…",
  "category": "payments" }
```

| Field | Rule |
|---|---|
| `name` | required, 2–120 characters |
| `email` | required, valid address, up to 191 characters (stored lower-cased) |
| `subject` | required, 4–160 characters |
| `message` | required, 10–5000 characters |
| `category` | optional, one of `general`, `account`, `payments`, `subscriptions`, `app_reviews`, `ad_shares`, `vas`, `other` |
| `website` | honeypot — must be empty; a filled field is answered with `201` but stores nothing |

`201` response:

```json
{ "message": "Thanks, Ada — your message is with our support team…",
  "data": { "ticket_number": "TKT-261127-ABC123", "status": "open",
            "email": "ada@example.com", "subject": "Cannot withdraw my balance",
            "created_at": "2026-11-27T10:15:00+00:00" } }
```

The submission **never** attaches to an account, even when the email belongs to a registered
user, and never returns a token: a stranger cannot inject messages into someone else's ticket
list. Support sees the ticket in the admin inbox (marked *Guest form*) with the matching account
name when one exists; replies are emailed to the address the visitor typed. An identical
email + subject sent again within five minutes returns the existing ticket instead of opening a
second one.

## User routes

| Method | Path | Purpose |
|---|---|---|
| `POST` | `/auth/logout` | Revoke current user token |
| `GET`, `PATCH` | `/me` | Read/update profile |
| `DELETE` | `/me` | Soft-delete the account for 30 days after password confirmation when a password is set |
| `POST`, `DELETE` | `/me/media/{avatar|cover}` | Upload or remove profile images |
| `PUT` | `/me/password` | Change account password after verifying current password |
| `GET`, `PUT` | `/me/notification-preferences` | Read/update per-category push and email preferences |
| `GET` | `/me/referral-code`, `/me/referrals` | View referral code and signups |
| `GET` | `/listings/apps`, `/listings/ads` | List eligible campaigns; requires active marketplace subscription |
| `GET` | `/listings/apps/{campaign}`, `/listings/ads/{ad}` | View eligible campaign detail and app screenshots |
| `POST` | `/review-campaigns/{campaign}/claim` | Reserve an app-review slot; send an idempotency key |
| `POST` | `/review-tasks/{task}/submit` | Submit questionnaire answers for human review |
| `GET` | `/me/review-tasks`, `/me/review-submissions` | View claimed tasks and review decision status |
| `POST` | `/ads/{ad}/claim` | Reserve an ad-share slot; send an idempotency key |
| `POST` | `/ad-share-tasks/{task}/submit` | Submit public-post URL and optional screenshot |
| `GET` | `/me/ad-share-tasks`, `/me/ad-share-events` | View share claims and manual verification status |
| `POST`, `GET` | `/subscriptions`, `/me/subscriptions` | Purchase a configured plan from top-up funds and view subscriptions/entitlements |
| `POST`, `GET` | `/topups/manual-deposits`, `/me/manual-deposits` | Upload bank-deposit proof and view status |
| `GET` | `/wallets`, `/wallets/{wallet}/ledger` | View derived balances and ledger entries |
| `GET` | `/me/account-history?per_page=20&page=1` | Unified account transactions; `per_page` 1–50, default 20 |
| `POST` | `/wallets/topup-transfer` | Fee-free transfer from top-up funds to a `review_group_b`, `review_group_c`, `share`, `referral` or `bonus` wallet |
| `GET` | `/withdrawal-options` | List active payout options and required fields |
| `GET`, `POST` | `/withdrawals` | View or submit withdrawals from earned wallets |
| `GET`, `PATCH` | `/notifications`, `/notifications/{notification}/read` | In-app notification inbox |
| `POST`, `DELETE` | `/devices`, `/devices/{device}` | Register/revoke push tokens |

Money in wallet and campaign workflow payloads uses integer minor units; `/currencies` provides precision by code. App listing and ad creative fields retain their listing representation. Withdrawal `amount_minor` is the requested payout amount; the configured percentage fee is reserved in addition. The withdrawal option's configured `min_withdrawal` is expressed in major currency units.

## Admin routes

Admin routes are prefixed `/admin` and require an active admin token.

| Area | Routes |
|---|---|
| Admin session | `GET /admin/me`, `POST /admin/auth/logout` |
| App releases | `GET/POST /admin/app-versions`, `PATCH/DELETE /admin/app-versions/{appVersion}` |
| App catalog | `GET/POST /admin/apps`, `PATCH /admin/apps/{app}`, `POST /admin/apps/{app}/screenshots`, `DELETE /admin/apps/{app}/screenshots/{screenshot}` |
| Review campaigns | `GET/POST /admin/review-campaigns`, `PATCH /admin/review-campaigns/{campaign}` |
| Questionnaires | `GET/POST /admin/review-questions`, `PATCH /admin/review-questions/{question}`, `GET/POST /admin/review-templates`, `POST /admin/review-templates/{template}/versions`, `POST /admin/review-templates/{template}/versions/{version}/publish` |
| Sponsor ads | `GET/POST /admin/ads`, `PATCH /admin/ads/{ad}` |
| Plans and coverage | `GET /admin/subscription-plans`, `PATCH /admin/subscription-plans/{plan}`, `PUT /admin/subscription-plans/{plan}/coverage` |
| Countries and regions | `GET/POST /admin/countries`, `PATCH /admin/countries/{country}`, `GET/POST /admin/regions`, `PATCH /admin/regions/{region}` |
| Users and payout methods | `GET /admin/users`, `PATCH /admin/users/{user}`, `GET/POST /admin/withdrawal-options`, `PATCH /admin/withdrawal-options/{option}` |
| Runtime settings | `GET /admin/settings`, `PUT /admin/settings/{settingKey}` |
| Support inbox | `GET /admin/support-tickets`, `GET /admin/support-tickets/{ticket}`, `POST /admin/support-tickets/{ticket}/messages`, `PATCH /admin/support-tickets/{ticket}` — includes guest tickets submitted through the public contact form (list rows carry a `guest` block) |
| Moderation | Queues and decision endpoints under `/admin/moderation/reviews`, `/shares`, `/manual-deposits`, and `/withdrawals` |

Approvals credit wallets transactionally and use unique ledger idempotency keys. Rejections release reservations or return reserved withdrawal funds and retain the admin reason in the audit trail.

## Integration boundaries

Email/password registration, Google/Facebook/Telegram identity verification, manual deposits, admin moderation, task eligibility, subscription purchases from top-up funds, ledger transfers, withdrawals, in-app notifications, notification preferences, app-release management, and device-token storage are implemented as API workflows. Social login requires `GOOGLE_CLIENT_ID`, `FACEBOOK_APP_ID`, `FACEBOOK_APP_SECRET`, and/or `TELEGRAM_BOT_TOKEN` in server environment configuration. Online gateways require their credentials, and FCM/email delivery require provider credentials/configuration before delivery is active.

The scheduled `tasks:expire-reservations` command should run every minute through Laravel's scheduler. It releases unsubmitted expired app-review and ad-share reservations. Submitted evidence remains reserved until a human admin decision.

User ranking rewards use `GET /api/v1/me/ranking` for the signed-in user's progress and qualification history. Admins manage level requirements at `/api/v1/admin/rank-levels` and enable the program with the `rewards.user_ranking_active` system setting. Run `rankings:check-eligibility` daily to qualify eligible users and `rankings:reward-monthly` daily to issue any due monthly bonus wallet reward; the command is idempotent per qualification and reward month. Both commands are registered in Laravel's scheduler, so the production scheduler must invoke `php artisan schedule:run` every minute.

After deploying the migration, seed the six initial rank levels with `php artisan db:seed --class=UserRankLevelSeeder --force` (the main demo seeder intentionally skips production environments).
