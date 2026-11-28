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

```bash
.tools/bin/php vendor/phpunit/phpunit/phpunit tests/Feature/AdminUsersAreaTest.php
.tools/bin/php vendor/phpunit/phpunit/phpunit
```
