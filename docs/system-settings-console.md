# System settings console

The admin console used to edit `system_settings` through the generic module CRUD
(`/admin/modules/system-settings`), where every row is a JSON textarea. That is
workable for a developer and hostile for everyone else: a switch becomes
`true`, a price list becomes nested objects, and one stray bracket corrupts a
setting that checkout depends on.

The settings console (`/admin/settings`) is a dedicated screen for the same
table:

- **One tab per settings group** — Brand & about page, Payments, Rewards &
  wallets, Notifications, Automation, VAS, Campaigns, Advanced.
- **Typed controls** — switch, number (with unit), text/email/URL, multi-line
  list (one item per line), key/value rows, picker, toggle grid, gateway ×
  currency matrix, per-platform price grid.
- **Same storage contract** — each editor writes exactly the JSON shape the rest
  of the application reads. Saving the screen with no edits produces no
  database writes (verified against the live table: every tab round-trips with
  zero shape drift).
- **Audited** — one `audit_logs` row per changed setting (`action =
  system_setting.updated`, `entity_type = system_setting`, before/after values).
- **Super admins only** — the same rule that guards the `system-settings`
  module. Other roles get `403`.

The generic module editor is still available ("Raw records" on the console, or
`/admin/modules/system-settings/...`) for one-off repairs. Both screens write
the same rows.

## Where the code lives

| File | Purpose |
| --- | --- |
| `app/Application/Settings/SystemSettingsSchema.php` | Groups, tabs, labels, help text, editor types, sections and validation metadata for every known key. |
| `app/Application/Settings/SystemSettingsManager.php` | Resolves stored values into editor state, builds validation rules, normalises submissions back into the exact stored shapes, persists and audits. |
| `app/Features/Admin/Http/Controllers/Web/SystemSettingsController.php` | `index` / `update`, super-admin authorisation, cache invalidation. |
| `resources/views/admin/settings/index.blade.php` | Tabs, section cards, save bar, row-adding script. |
| `resources/views/admin/settings/_field.blade.php` | One control per editor type. |
| `routes/web.php` | `admin.settings.index`, `admin.settings.update`. |
| `resources/views/admin/layout.blade.php` | Sidebar link (Configuration → System settings) for super admins. |

## Stored shapes (do not change these)

These settings are read by code that expects a specific structure. The console
normalises to these shapes; keep them if you add a field.

| Key | Stored value |
| --- | --- |
| `payment.active_gateways` | `{"gateway": ["NGN", …]}` — `manual` maps to `[]`. Read by `PaymentGatewaySettings`. |
| `payment.active_payment_currency` | `{"gateway.NGN": "NGN"}` (display labels are preserved on save). Not editable on its own: the matrix derives it. |
| `account_validation.provider` | `{"provider": "paystack"}` — read by `ResolvePayoutAccount`. |
| `vas.services` | `{"airtime": true, "data": false, …}` |
| `withdrawal.wallet.referral` / `.bonus` | `{"active": true}` |
| `campaign.services` | `{"tiktok": {"followers": 300, "min": 100}, …}` — integer amounts stay integers. |
| `about.features`, `about.what_we_do_bullets` | `["one", "two"]` |
| `about.social_links` | `{"Instagram": "https://…"}` |
| `notifications.*`, `rewards.*`, `vas.enabled`, `app.*` | scalars (bool / int / string) |

`is_public` is surfaced as a per-setting "Public" chip, so a value can stay
hidden from the public API without leaving the screen.

## Adding a new setting

1. Add it to the right group in `SystemSettingsSchema::groups()` with a `label`,
   `section` and `type` (plus `min`/`max`/`options`/`required` as needed).
2. If it needs a brand-new editor type, add a `@case` in `_field.blade.php`, the
   validation rules in `SystemSettingsManager::rules()`, and the matching
   branch in `normalise()`.
3. Add a round-trip assertion to `tests/Feature/SystemSettingsAdminTest.php`.

Keys without a schema entry still appear on the **Advanced** tab as a JSON
field — that is the escape hatch, not the default. Keys containing `secret`,
`credential`, `token`, `private_key`, `password`, and everything under
`telegram.`, are never rendered: they are listed by name only under "Protected
values".

## Verification

```bash
php vendor/phpunit/phpunit/phpunit tests/Feature/SystemSettingsAdminTest.php
```

The suite covers authorisation per role, tab rendering, typed edits for every
shape above, required-field enforcement, invalid JSON rejection, read-only
fields surviving a save, the public flag, audit rows, cache invalidation and the
untouched raw module editor.
