# Mail and account recovery

## Mailer selection

Set `MAIL_MAILER` to `smtp`, `resend`, `mailgun`, or `mailjet`. Each named mailer uses an SMTP relay so the application does not need provider-specific transport packages:

- `smtp`: `MAIL_HOST`, `MAIL_PORT`, `MAIL_USERNAME`, `MAIL_PASSWORD`, `MAIL_SCHEME`
- `resend`: `RESEND_API_KEY` plus the Resend SMTP host, port, and username. The API key is used as the SMTP password.
- `mailgun`: Mailgun SMTP host, port, username, and password. Use the matching host for the account's region.
- `mailjet`: `MAILJET_API_KEY` and `MAILJET_SECRET_KEY` with the Mailjet SMTP relay.

Set `MAIL_FROM_ADDRESS`, `MAIL_FROM_NAME`, and `SUPPORT_EMAIL` for outgoing account mail and support links. After changing environment configuration on a cached deployment, run `php artisan config:cache`.

## Password recovery

`POST /api/v1/auth/forgot-password` accepts an email and sends a one-time reset token when it belongs to an active account. The response does not disclose whether the account exists. Tokens expire after 60 minutes and requests are throttled. The app's reset form accepts the email, token, and new password at `POST /api/v1/auth/reset-password`.

## Account deletion

Deleting an account sets `deleted_at`, revokes API tokens and push devices, and keeps its account data for a 30-day recovery/support window. Login attempts with the correct credentials show an account status page with support contact details. Deleted users are purged by running:

```sh
php artisan users:purge-deleted
```

Use `--days=30` (the default) to set a different retention period. Schedule this command daily in the deployment scheduler to automatically purge accounts when they pass the retention window.
