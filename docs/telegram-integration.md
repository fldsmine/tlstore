# Telegram integration

One bot posts to one admin group/channel:

- **Admin announcements** — composed in the console, text-only or with an image.
- **Withdrawal notifications** — automatic “NEW WITHDRAWAL REQUEST” post with the
  generated receipt attached.
- **Withdrawal status updates** — automatic post when a withdrawal is marked paid
  or rejected.
- **Test message** — proves the bot can post to the configured chat.

Nothing about the bot token reaches the browser, the mobile app or any API
response. It is encrypted with `APP_KEY` before it is written to
`system_settings` and the console only ever shows `123456789:••••••abcd`.

---

## 1. How it fits together

| Layer | Path | Responsibility |
|---|---|---|
| Settings | `app/Application/Telegram/TelegramSettings.php` | Encrypted token, chat id, master + per-feature switches, last connection check. Environment values act as defaults. |
| Client | `app/Application/Telegram/TelegramClient.php` | `sendMessage`, `sendPhoto`, `sendDocument`, `getMe`, `getChat` against `https://api.telegram.org/bot<token>/…`. Returns a `TelegramResult`; never throws. |
| HTML | `app/Application/Telegram/TelegramHtml.php` | Reduces admin input to Telegram's HTML allowlist (`b`, `i`, `u`, `s`, `a[href]`, `code`, `pre`, `br`). Drops scripts, unwraps unknown tags, escapes stray `<`. |
| Copy | `app/Application/Telegram/TelegramNotifier.php` | The withdrawal request / decision / test message layouts (all user data escaped). |
| Delivery | `app/Application/Telegram/TelegramDispatcher.php` | Sends one `telegram_messages` row, records `sent`/`failed`, cleans up receipts. |
| Receipt | `app/Application/Receipts/WithdrawalReceiptFile.php` | Renders `resources/views/pdf/withdrawal-receipt.blade.php`; attaches a PNG when Ghostscript is available, otherwise the PDF. |
| Queue | `app/Jobs/SendTelegramMessageJob.php`, `SendWithdrawalTelegramNotificationJob.php`, `SendWithdrawalStatusTelegramJob.php` | Retries with backoff, idempotent per message row. |
| Console | `app/Features/Admin/Http/Controllers/Web/TelegramAdminController.php`, `resources/views/admin/telegram/index.blade.php` | Settings, diagnostics, composer + preview, history. |
| Data | `telegram_messages` (migration `2026_11_27_120001_create_telegram_messages_table.php`) | Delivery log and queue payload. |

### Database

`telegram_messages`: `kind` (`announcement`, `test`, `withdrawal_request`,
`withdrawal_status`), `chat_id`, `body` (HTML), `attachment_type` /
`attachment_path`, `status` (`queued`, `sending`, `sent`, `failed`), unique
`dedupe_key` (prevents a retried job sending twice), `telegram_message_id`,
`error`, `sent_by_admin_id`, `withdrawal_request_id`, `meta`, `sent_at`.

No existing table changed. Configuration lives in `system_settings`
(`telegram.*`), always with `is_public = false`.

---

## 2. Environment variables

```dotenv
# Shared bot (also used by the Telegram login widget)
TELEGRAM_BOT_TOKEN=
TELEGRAM_BOT_USERNAME=tlstore_ai_bot

# Notification bot; blank reuses TELEGRAM_BOT_TOKEN
TELEGRAM_NOTIFY_BOT_TOKEN=
# -1001234567890 (private group/channel) or @publicchannel
TELEGRAM_NOTIFY_CHAT_ID=

# Receipt attachment: PNG when Ghostscript is present, PDF otherwise
TELEGRAM_RECEIPT_IMAGE=true
TELEGRAM_GHOSTSCRIPT_BINARY=
```

Precedence for the token and chat id: **admin console (encrypted DB) → environment
→ nothing**. The console also stores the four switches; when the master switch has
never been saved it follows “configured or not”, so an environment-only install
starts working without a visit to the console.

Queue: automatic notifications are queued (`SendWithdrawal*Job`). Keep a worker
running so they leave the server:

```bash
php artisan queue:work --queue=default --tries=3
```

Without a worker the withdrawals still succeed; the messages simply stay
`queued` in the history.

---

## 3. Telegram setup

1. **Create the bot** — open [@BotFather](https://t.me/BotFather), send
   `/newbot`, choose a name and username. Copy the token (`123456789:AAH…`).
2. **Create the destination** — a group or channel for platform operations.
3. **Add the bot** — invite it to the group (a normal member is enough) or to the
   channel as an **administrator** with *Post messages*.
4. **Find the chat id**
   - Public channel: use `@channelusername`.
   - Private group/channel: open Telegram Web, select the chat and read the id in
     the URL (`#-1001234567890`); or add [@RawDataBot](https://t.me/RawDataBot),
     read `chat.id`, then remove it. Private ids always start with `-100`.
   - Groups upgraded to supergroups keep the `-100…` id; re-read it if posts start
     failing with “chat not found”.
5. **Configure** — admin console → *Telegram messaging*: paste the token, paste the
   chat id, save, then **Test connection** → **Send test message**.
6. **Enable** the automatic switches you want. Withdrawals post immediately as
   users submit requests; settlements post when finance marks them paid/rejected.

Sending to a **channel** requires the bot to be an administrator. Sending to a
**group** requires the bot to be a member and not muted/restricted.

---

## 4. Admin console

*Platform content → Telegram messaging* (super admins and admins only; finance,
moderator and content roles get 403).

- **Bot configuration** — token (write-only, blank keeps the current one), chat
  id, master switch, admin announcements, withdrawal notifications, withdrawal
  status updates.
- **Diagnostics** — *Test connection* (`getMe` + `getChat`, shows the chat title and
  bot username and stores the result) and *Send test message*.
- **Composer** — message textarea with formatting buttons (bold, italic,
  underline, strike, code, link, line break), optional JPG/PNG/WebP up to 5 MB,
  live Telegram-style preview, character counter, send button, success/failure
  feedback. Text-only announcements allow 4096 characters; with an image the
  caption limit is 1024.
- **History** — newest first, with date, type, excerpt, attachment thumbnail,
  status (`sent` / `failed` + error) and who sent it. Failed messages can be
  re-sent.

Formatting uses Telegram's HTML mode, e.g.

```html
🎉 <b>Payout window open</b>
Withdrawals are processed within <i>24 hours</i>.
Read more: <a href="https://tlstore.org">tlstore.org</a>
```

Anything outside the allowlist is removed before sending; line breaks are kept.

---

## 5. Automatic notifications

**Withdrawal request** (after the request transaction commits):

```
🚨 NEW WITHDRAWAL REQUEST
━━━━━━━━━━━━━━━━━━━━
👤 User: Jane Doe (@janedoe)
💵 Amount: NGN 50,000.00
🏦 Method: Bank transfer · GTBank
🔢 Account: ••••••6789
👜 Wallet: Share wallet
🔖 Reference: WD-000123
🕒 Requested: 27 Nov 2026, 7:15 PM
```

…with the receipt from `resources/views/pdf/withdrawal-receipt.blade.php` attached.
Account numbers are masked in the chat; the payout desk uses the console.

**Withdrawal decision** — `WITHDRAWAL PAID` (payout reference) or
`WITHDRAWAL REJECTED` (reason), amount, reference, timestamp.

Both are queued, de-duplicated per withdrawal (and per final status), retried
3× with backoff (15 s → 2 min → 10 min) and recorded in the history.

### Failure handling

- A Telegram error never fails a withdrawal: dispatch is wrapped, the job records
  `failed` and the API/user request is unaffected.
- Transport errors are scrubbed of the bot token before they are logged or shown
  (`https://api.telegram.org/bot<token>/…` → `[redacted]`).
- Rate limits keep Telegram's `retry_after` in the result; the queue backoff takes
  over from there.
- A missing receipt (render failure) still sends the text notification.

### Receipt as image vs document

Telegram shows photos inline. The receipt is a PDF, so the pipeline rasterises the
first page with Imagick and sends it with `sendPhoto`. That needs Ghostscript on
the server (`gs`), which is auto-detected (`/usr/bin/gs`, `/usr/local/bin/gs`,
`/opt/homebrew/bin/gs`) or set with `TELEGRAM_GHOSTSCRIPT_BINARY`. Without it the
PDF is sent with `sendDocument` — same information, no extra dependency. Set
`TELEGRAM_RECEIPT_IMAGE=false` to always send the PDF.

---

## 6. Security notes

- Token encrypted with `APP_KEY` in `system_settings`; only a masked preview is
  rendered. The field is `type="password"`, `autocomplete="new-password"`, blank by
  default, and never populated with the stored value.
- Token never appears in audit logs (only `token_updated: true`), API responses
  (`/api/v1/admin/settings` excludes `telegram.*`), error messages or HTTP-failure
  logs.
- Uploaded announcement images live on the private `local` disk and are served
  through an admin-only route (`/admin/telegram/messages/{id}/image`); they are not
  in `public/`.
- Generated receipts are deleted after successful delivery and on final failure.
- Console access: admin web session (`EnsureAdminWebAccount`) + `super_admin` /
  `admin` role; test/send actions are rate-limited.

---

## 7. Tests

```bash
php vendor/phpunit/phpunit/phpunit tests/Feature/TelegramAdminTest.php
php vendor/phpunit/phpunit/phpunit tests/Feature/TelegramWithdrawalNotificationTest.php
php vendor/phpunit/phpunit/phpunit tests/Unit/TelegramClientTest.php
php vendor/phpunit/phpunit/phpunit tests/Unit/TelegramHtmlTest.php
```

Covered: permissions, encrypted token + no credential in HTML/API/audit,
blank-token keep, switches, connection check success/failure, test message,
text announcement, image announcement (`sendPhoto` multipart), sanitisation,
caption limit, failed message + re-send, disabled switches, queued dispatch on
withdrawal, idempotent replay, receipt attachment, outages not breaking the
withdrawal, status updates, retry/`failed()` handling, token redaction and
HTML allowlisting.

All Telegram HTTP is faked (`Http::preventStrayRequests()`), so the suite never
talks to Telegram.

---

## 8. Troubleshooting

| Symptom | Fix |
|---|---|
| `401 Unauthorized` on Test connection | Wrong/revoked token — copy it again from @BotFather. |
| `400 Bad Request: chat not found` | Wrong id, or the bot was removed from the chat. Re-add it and re-read the id. |
| `403 Forbidden: bot is not a member of the channel chat` | Promote the bot to channel administrator. |
| `400 Bad Request: can't parse entities` | Should not happen (input is sanitised); check the row's `body` in the history for manual edits. |
| Messages stay `queued` | No queue worker is running (`php artisan queue:work`). |
| No receipt attached | Receipt rendering failed — see the application log (`telegram.receipt_render_failed`); the text still went out. |
| Receipt arrives as PDF, not an image | Ghostscript is not installed (this is the supported fallback). |
