# Laravel Admin Backend

The browser admin console is served at `/admin`. It uses Laravel's session guard against the existing `admins` table, Laravel CSRF protection, and the platform's existing models and application actions. It does not use the discarded Flutter admin screens.

## Create the first administrator

```bash
php artisan admin:create
```

The command prompts for an email, full name, and a password of at least 14 characters. It does not print or email the password. The demo admin seeder skips production environments, so production access must be bootstrapped with this command.

## Console areas

- Dashboard queues and recent admin activity.
- User management, account status, soft delete/restore, password reset, wallet credit/debit, recent subscriptions, transactions, withdrawals, referrals, and review tasks.
- App listings and screenshots, review campaigns, ads listings, app reports, and user campaign orders.
- Review and ad-share moderation, manual top-up verification, and withdrawal settlement. Decisions call the same application actions as the API so ledger, transaction, referral, and notification effects remain in one place.
- Subscription groups/plans/add-ons, rank levels, withdrawal/payment methods, banks, country/region coverage, social handles, app versions, announcements, FAQs, testimonials, legal documents, and system settings.
- Questionnaire templates, draft versions, ordered question assignments, and guarded publishing; published versions stay immutable. Read-only operational views include social-follow rewards, VAS orders, exchange-rate snapshots, and audit activity.
- Admin accounts with role and active/inactive status management. Sensitive settings and password values are redacted in audit log snapshots.

## Role access

- `super_admin`: all console areas, including administrator accounts and system settings.
- `admin`: platform management except administrator accounts and system settings.
- `content_admin`: listings, packages, content, review templates/questions, and location coverage.
- `moderator`: task/report moderation and limited user account operations.
- `finance`: deposits, withdrawals, payment configuration, and wallet adjustment from a user account view.

## Notes

The console is a server-rendered Laravel Blade application so it introduces no frontend build or package dependency. Filament/Livewire was evaluated, but the package install could not complete in the Windows environment because Composer archive writes were denied and source downloads timed out. The current implementation uses existing Laravel dependencies and can be accessed immediately. The module forms are driven by `config/admin.php`; adding a platform model there makes it available to the appropriate role after its fields and authorization are defined.

Run `php artisan storage:link` if admin uploads need to be served from the public disk. For production, ensure HTTPS, secure session cookie settings, strong admin passwords, backups, and a least-privilege database user are configured.
