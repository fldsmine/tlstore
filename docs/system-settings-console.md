# System settings console

The admin console used to edit `system_settings` through the generic module CRUD
(`/admin/modules/system-settings`), where every row is a JSON textarea. That is
workable for a developer and hostile for everyone else: a switch becomes
`true`, a price list becomes nested objects, and one stray bracket corrupts a
setting that checkout depends on.

The settings console (`/admin/settings`) is a dedicated screen for the same
table:

- **One tab per settings group** — Brand & about page, Payments, Rewards &
  wallets, Notifications, Automation, VAS, Campaigns, API keys & secrets,
  Advanced.
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

## API keys & secrets tab (`.env`)

Third-party credentials are not rows in `system_settings` — they come from
`config/services.php`, `config/ai-listings.php`, `config/mail.php` and
`config/trustlens.php`, which all resolve `env()` values. This tab edits the
deployment's `.env` file directly, so an admin can switch a provider on without
a developer or a redeploy.

What is editable is curated in `App\Application\Settings\EnvironmentSettingsSchema`:

| Section | Editable |
| --- | --- |
| Payment gateways | Korapay, Paystack, Flutterwave, Bachs and Fincra secret/public keys, webhook secrets, API base URLs, Fincra fee bearer |
| Value-added services | VTpass base URL, API/public/secret/webhook keys, request timeout |
| Mail transport | SMTP host/port/encryption/username/password; Resend, Mailgun and Mailjet credentials |
| Push notifications | `FCM_ENABLED`, `FCM_PROJECT_ID` |
| AI listing generator | API key, base URL and model for OpenAI, Anthropic, Gemini, Groq, OpenRouter, DeepSeek and Mistral, plus the artwork requirement and agent timeout |
| Social sign-in | Google client ID, Facebook app ID/secret/client token, Telegram bot token and username |
| Platform | support email, payment return URL, webhook base URL, Android package, signing fingerprints, iOS app ID |

**Deliberately not editable** (listed on the page under "Kept on the server"):
`FCM_SERVICE_ACCOUNT_PATH` (needs the JSON file uploaded to the server),
`APP_KEY`, `APP_URL`, `DB_*`, `AWS_*`, `REDIS_*`/`SESSION_*`/`CACHE_*`/`QUEUE_*`,
`MAIL_SENDMAIL_PATH`, `TELEGRAM_NOTIFY_*` (managed encrypted on the Telegram
page), `TELEGRAM_GHOSTSCRIPT_BINARY` and `SYSTEM_ACCOUNT_PASSWORD`.

How a save behaves:

- **Secrets are write-only.** A stored secret is never rendered — the input is
  empty with a "Set" badge, and leaving it blank keeps the value. A "Remove the
  stored value" checkbox deletes the line.
- **Empty text fields remove the variable**, so the app falls back to the
  built-in default shown next to the field.
- **Only submitted fields are touched**; comments, ordering and every other
  variable are preserved, values are quoted for dotenv, and the previous file is
  kept as `.env.bak`.
- **A no-op save writes nothing.** Submitting every field unchanged leaves the
  file byte-identical (verified against the live `.env`: 70 fields, 0 writes).
- **Validation** covers URLs, emails, integers, select options and list entries;
  keys outside the schema (e.g. `DATABASE_PASSWORD`) are ignored.
- **After a write** the config cache is cleared when it is cached and queue
  workers are restarted, so new values are live on the next request.
- Every changed variable gets an `audit_logs` row (`action =
  environment.updated`, `entity_type = environment`, secret values stored as
  `[redacted]`).
- If `.env` is missing (for example a container that only receives real
  environment variables) or not writable, the tab says so and refuses to save
  instead of creating a partial file.

`GET /admin/settings?tab=environment` renders it; `PATCH /admin/settings/api-keys`
(`admin.settings.environment.update`, throttled) writes it. Super admins only.

## Where the code lives

| File | Purpose |
| --- | --- |
| `app/Application/Settings/SystemSettingsSchema.php` | Groups, tabs, labels, help text, editor types, sections and validation metadata for every known key. |
| `app/Application/Settings/SystemSettingsManager.php` | Resolves stored values into editor state, builds validation rules, normalises submissions back into the exact stored shapes, persists and audits. |
| `app/Features/Admin/Http/Controllers/Web/SystemSettingsController.php` | `index` / `update`, super-admin authorisation, cache invalidation. |
| `resources/views/admin/settings/index.blade.php` | Tabs, section cards, save bar, row-adding script. |
| `resources/views/admin/settings/_field.blade.php` | One control per editor type. |
| `app/Application/Settings/EnvironmentSettingsSchema.php` | Curated `.env` keys: sections, providers, labels, types, defaults and the "kept on the server" list. |
| `app/Application/Settings/EnvironmentFile.php` | Format-preserving `.env` reader/writer (quoting, backup, atomic replace, permissions). |
| `app/Application/Settings/EnvironmentSettingsManager.php` | Resolves current values and provider status, builds validation rules, normalises values, detects no-ops, writes audit rows. |
| `app/Features/Admin/Http/Controllers/Web/EnvironmentSettingsController.php` | Credential save, super-admin guard, config cache clear + queue restart. |
| `resources/views/admin/settings/environment.blade.php` | The tab: provider cards, status badges, write-only secret inputs. |
| `routes/web.php` | `admin.settings.index`, `admin.settings.update`, `admin.settings.environment.update`. |
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
php vendor/phpunit/phpunit/phpunit tests/Feature/EnvironmentSettingsAdminTest.php
php vendor/phpunit/phpunit/phpunit tests/Unit/EnvironmentFileTest.php
```

The suite covers authorisation per role, tab rendering, typed edits for every
shape above, required-field enforcement, invalid JSON rejection, read-only
fields surviving a save, the public flag, audit rows, cache invalidation and the
untouched raw module editor. The environment tests cover secret redaction,
in-place writes, quoting and round-tripping, removals, no-op saves, defaults,
validation, the ignored-key allowlist, a missing or read-only `.env`, and the
audit trail.
