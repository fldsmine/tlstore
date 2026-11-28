# Admin users console

The admin console used to manage end users through the generic module CRUD
(`/admin/modules/users`). That screen could edit `full_name`, `username`,
`email`, `phone`, `avatar` and `cover_image` — and nothing else. Everything an
administrator actually needs (wallets, ledger, withdrawals, referrals, task
history, notification history, sessions, audit trail) lived somewhere else or
was not surfaced at all.

The Users area (`/admin/users`) replaces it. The `users` module has been removed
from `config/admin.php`, so `/admin/modules/users` returns `404`; the module
index/forms no longer special-case users.

## Screens

| Screen | Route name | URL | What it is for |
| --- | --- | --- | --- |
| Account list | `admin.users.index` | `/admin/users` | Search, filter, sort and page through every account. |
| Overview | `admin.users.show` | `/admin/users/{user}` | Identity snapshot, wallets, subscription, counters, profile editor, account actions. |
| Wallet | `admin.users.wallet` | `/admin/users/{user}/wallet` | Per-wallet balances, full ledger with a wallet filter, manual adjustment form. |
| Tasks & campaigns | `admin.users.tasks` | `/admin/users/{user}/tasks` | Review submissions, ad share tasks, campaign orders, VAS orders. |
| Withdrawals | `admin.users.withdrawals` | `/admin/users/{user}/withdrawals` | Withdrawal summary by status/currency, payout method, requests, manual deposits. |
| Referrals | `admin.users.referrals` | `/admin/users/{user}/referrals` | Who referred the user, who they referred, referral wallet earnings. |
| Notifications | `admin.users.notifications` | `/admin/users/{user}/notifications` | Compose an in-app message, delivery history, preferences, device tokens. |
| Activity & security | `admin.users.activity` | `/admin/users/{user}/activity` | API sessions, devices, 2FA/PIN state, support tickets, app reports, audit trail. |

Every tab shares the same header (`resources/views/admin/users/_header.blade.php`):
avatar, name, username, email, country, currencies, status/banned/deleted,
email-verified and 2FA badges, and the tab strip with live counts.

## List page

Filters (all combined, all preserved across pagination):

- `q` — name, username, email, phone, referral code, and the numeric account ID.
  Wildcards in the input are escaped before they reach `LIKE`.
- `status` — any `UserStatus` value (`active`, `restricted`, `suspended`,
  `pending`, `banned`).
- `state` — `active` (not banned), `banned`, `verified`, `unverified`,
  `subscribed` (active package that has not expired), `no_subscription`,
  `with_balance`.
- `country` — ISO country code.
- `deleted` — `exclude` (default), `include`, `only` (soft-deleted accounts).
- `sort` — newest, oldest, name, recently active (latest device `last_seen_at`).
- `per_page` — 25, 50 or 100.

The page header shows unfiltered totals (all accounts, active, banned, joined
today, new in seven days, active packages, deleted). Rows show per-currency
wallet totals, withdrawal/referral counts, last device activity and an **Open**
button into the account workspace.

## Account actions

All actions are `POST /admin/users/{user}/actions/{action}` (`admin.users.action`)
and every one writes an `audit_logs` row through
`App\Features\Admin\Support\AdminAuditLogger` (secret-like keys are redacted).

| Action | Roles | Effect |
| --- | --- | --- |
| `wallet-adjustment` | super_admin, admin, finance | Posts a `posted` ledger entry (creates the wallet on demand), refuses an overdraft, tells you the currency it used. |
| `ban` | super_admin, admin, moderator | Sets `is_banned`, status `banned`, stores the reason, revokes every API token. |
| `unban` | super_admin, admin, moderator | Clears the ban, sets status `active`, revokes API tokens. |
| `status` | super_admin, admin | Sets `active`/`restricted`/`suspended`/`pending`. Choosing `active` also lifts an existing ban. |
| `verify-email` | super_admin, admin | Stamps `email_verified_at` when it is empty. |
| `revoke-sessions` | super_admin, admin | Deletes the account's Sanctum tokens. |
| `reset-password` | super_admin, admin | Generates a one-time temporary password (flashed once), revokes tokens. |
| `delete` | super_admin, admin | Soft delete + token revocation. |
| `restore` | super_admin, admin | Restores the row and returns it to `active`, not banned. |
| `notify` | super_admin, admin, moderator | Creates an `admin_message` notification in the app feed; a push is attempted only when `services.fcm.enabled` is true. |
| `revoke-device` | super_admin, admin, moderator | Stamps `revoked_at` on one device token so push stops. |

Profile editing is `PATCH /admin/users/{user}/profile`
(`admin.users.profile.update`, super_admin and admin only): name, username,
email, phone, country, preferred currency, avatar, cover image and the
`is_member` flag. `username`/`email` uniqueness ignores the account itself and
includes soft-deleted rows, because the database unique indexes do. The wallet
currency is intentionally not editable here — it belongs to the ledger, not to
the profile.

Moderator and finance accounts can open every read-only tab; the screens hide or
disable the controls their role cannot use rather than failing after submit.

## Impersonation (super admins)

A super administrator can open the Flutter web app **already signed in as the account** — to
reproduce a bug, walk a user through a flow, or check what they can see. Two entry points:

- **Admin console** → Users → account → Overview → *Impersonate — open the web app as this user*.
  The button posts to `admin.users.impersonate` (`POST /admin/users/{user}/impersonate`) and the
  redirect opens in a **new tab** (`target="_blank"`).
- **Flutter admin screens** → Users → account → ⋯ → *Impersonate (open web app)*, or the
  **Impersonate** button on the action row. The app calls
  `POST /api/v1/admin/users/{user}/impersonation` with its admin token and opens the returned URL
  with `url_launcher` (`webOnlyWindowName: '_blank'` on web, the system browser on mobile).

Both entry points return a link to `/?impersonation=<code>`. The Flutter web app redeems it once
via `POST /api/v1/auth/impersonation`, stores the returned user token as its session, and strips
the code from the address bar (`history.replaceState`). While that session lasts, the home screen
shows an **Impersonation session — … started by <admin> · Exit** banner; *Exit* restores the
session that was signed in before the hand-off (when one existed) and otherwise returns to the
login screen.

Guard rails:

| Control | Behaviour |
| --- | --- |
| Who can impersonate | `super_admin` only, on both the console route and the admin API |
| Who can be impersonated | Active, not banned, not soft-deleted accounts — the API rejects sessions for anything else, so the tab would only show errors |
| Hand-off code | 64 random characters, only its SHA-256 hash is stored, single use, expires after `ADMIN_IMPERSONATION_LINK_TTL_SECONDS` (default 120) |
| Impersonated session | A normal Sanctum token for the user, expiring after `ADMIN_IMPERSONATION_SESSION_MINUTES` (default 60), named `admin-impersonation:<admin id>` so it is identifiable in the account's session list |
| 2FA | Not re-challenged (the administrator already passed the admin login); the session is bounded and audited instead |
| Audit | `user.impersonation_link_issued` and `user.impersonation_session_started`, both against the user with the administrator as actor, plus the link id, token id and expiry |
| Kill switch | `ADMIN_IMPERSONATION_ENABLED=false` makes both steps refuse with a clear message |

The code travels in the query string (the Flutter entry page is served at `/`), so it is also
visible to same-origin server logs until it is redeemed or expires. It is single-use, expiring and
scoped to one account; treat access logs accordingly.

## Notes and invariants

- Wallet balances are never written directly: the adjustment creates a
  `ledger_entries` row, and `LedgerEntryObserver` applies the balance delta and
  projects the transaction. `php artisan ledger:verify` remains the integrity
  check.
- Deleted accounts can be inspected (`User::withTrashed()`), but money moves and
  messages are refused until the account is restored.
- Notification preferences are displayed read-only: consent belongs to the user
  and is changed in the app.
- Push delivery depends on `FCM_*` being configured (see the API keys tab of
  `/admin/settings`). Without it, `notify` still writes the in-app notification
  and says so.
- The dashboard "Users" metric now links to `/admin/users`, and the sidebar
  Workspace group shows **Users** for super admins, admins, moderators and
  finance.

## Tests

`tests/Feature/AdminUsersAreaTest.php` covers the module removal, list
search/filter/deleted behaviour, every tab rendering, the read-only moderator
view, profile edits plus audit, wallet adjustment permissions and overdraft
protection, ban/unban token revocation, password reset/verify/delete/restore
role gates, in-app notification creation and device revocation.

`tests/Feature/AdminImpersonationTest.php` covers the impersonation hand-off:
role gates on both entry points, the single-use code, token expiry, replay and
expiry rejection, refused accounts (banned/suspended/deleted), the admin API
payload and the two audit rows.

```bash
.tools/bin/php vendor/phpunit/phpunit/phpunit tests/Feature/AdminUsersAreaTest.php
.tools/bin/php vendor/phpunit/phpunit/phpunit tests/Feature/AdminImpersonationTest.php
.tools/bin/php vendor/phpunit/phpunit/phpunit
```
