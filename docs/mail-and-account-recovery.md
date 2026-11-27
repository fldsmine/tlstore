# Mail and account recovery

## Mailer selection

Set `MAIL_MAILER` to `smtp`, `resend`, `mailgun`, or `mailjet`. Each named mailer uses an SMTP relay so the application does not need provider-specific transport packages:

- `smtp`: `MAIL_HOST`, `MAIL_PORT`, `MAIL_USERNAME`, `MAIL_PASSWORD`, `MAIL_SCHEME`
- `resend`: `RESEND_API_KEY` plus the Resend SMTP host, port, and username. The API key is used as the SMTP password.
- `mailgun`: Mailgun SMTP host, port, username, and password. Use the matching host for the account's region.
- `mailjet`: `MAILJET_API_KEY` and `MAILJET_SECRET_KEY` with the Mailjet SMTP relay.

Set `MAIL_FROM_ADDRESS`, `MAIL_FROM_NAME`, and `SUPPORT_EMAIL` for outgoing account mail and support links. After changing environment configuration on a cached deployment, run `php artisan config:cache`.

## Notification templates and delivery

The admin console at `/admin/notifications` manages the global email and push switches, sender identity, selected mailer, and event templates. Mailer credentials remain in the server environment; the system setting selects which configured Laravel mailer is used. Delivery can also be checked from this page with a test email.

`notification_templates` is seeded with current app event templates. Each template has independent email, push, and SMS fields. Email or push is delivered only when the global channel and matching template are enabled. Optional email category preferences are respected for event notifications; password recovery uses the global email switch and its own template. SMS fields and switches are stored for later but are not connected to an SMS provider or delivery path.

Templates support `{{shortcode}}` replacements shown on their edit page. Email bodies are plain text wrapped in the TLStore layout; push bodies are sent through Firebase. The current event templates cover submitted and moderated app reviews, submitted and moderated ad shares, subscription activation, withdrawal requests and decisions, manual top-up submission and decisions, successful gateway top-ups, support replies, and password reset codes. Email delivery results for notification events are recorded in `notification_deliveries`.

## Password recovery

`POST /api/v1/auth/forgot-password` accepts an email and sends a one-time reset token when it belongs to an active account. The response does not disclose whether the account exists. Tokens expire after 60 minutes and requests are throttled. The app's reset form accepts the email, token, and new password at `POST /api/v1/auth/reset-password`.

## Account deletion

Deleting an account sets `deleted_at`, revokes API tokens and push devices, and keeps its account data for a 30-day recovery/support window. Login attempts with the correct credentials show an account status page with support contact details. Deleted users are purged by running:

```sh
php artisan users:purge-deleted
```

Use `--days=30` (the default) to set a different retention period. Schedule this command daily in the deployment scheduler to automatically purge accounts when they pass the retention window.
