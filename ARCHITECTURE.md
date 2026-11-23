# TrustLens application architecture

The backend uses feature-oriented HTTP modules and a shared application layer. API controllers validate and translate requests, invoke use cases in `Application/Actions`, and format API responses. Future Web controllers can call those same actions. Actions do not depend on HTTP request/response classes.

## Code layout

```text
app/
├── Application/Actions/         # Shared use cases across API and future Web transports
├── Features/                    # Feature-owned API controllers, requests, resources
│   ├── Identity/
│   ├── Marketplace/
│   ├── AppReview/
│   ├── Ads/
│   ├── Subscriptions/
│   ├── Payments/
│   ├── Wallets/
│   ├── Notifications/
│   ├── Admin/
│   └── System/
├── Http/Middleware/              # User/admin account guards
├── Models/                       # Eloquent persistence models
├── Enums/                        # Domain and persistence state values
└── Providers/
routes/
├── api.php                       # `/api/v1` API routes
├── web.php                       # Reserved for the future Web transport
└── console.php                   # Scheduled maintenance tasks
```

## Confirmed product rules

- Marketplace plans are configured by admins in Groups A, B, and C. Listings are public; an active package is required to claim tasks. Group A enables ad shares, Group B enables Google Play or Samsung reviews, and Group C enables Apple app reviews. A user has one active package and joining another switches packages.
- A user's profile country determines country matching; region targets match through the region's country membership. There are no location add-on packages or global listing access flag.
- Campaign `paid_amount` is its reward pool. Admins configure campaign maximums. Claiming reserves capacity; human approval records completion and updates `apps.total_reviews` or `ads.total_share`. Store-reported review totals live in `app_store_listing_snapshots`.
- App reviews and public ad-share links/screenshots wait for human admin approval. Rewards are credited only after approval.
- Only top-up funds purchase subscription packages. Top-up funds cannot be withdrawn; users may transfer them fee-free to review, share, referral, or bonus wallets. Earned wallets may be withdrawn under the active package's currency, minimum, and allowed days.
- Manual bank deposits require private proof upload and admin approval. Withdrawal `tax` is a percentage fee. All configured withdrawal methods are available to all users.
- Campaign currency is authoritative for reward payouts. Exchange rates are returned as dated display values only.
- Admins use a separate `admins` table and Sanctum tokens. Administrative changes and decisions are written to `audit_logs`.

## API contract

See [docs/api-v1.md](docs/api-v1.md) for the route inventory, authentication groups, and integration boundaries.

## Local setup

1. Copy `.env.example` to `.env` and configure MySQL.
2. Run `php artisan key:generate`.
3. Review migrations and then run `php artisan migrate --seed` in a local database.
4. Run the Laravel scheduler (`php artisan schedule:work` locally, or configure the server scheduler) so expired task reservations are released.

Demo users and the demo admin are seeded only in local, testing, or staging environments. The demo admin login is `admin@tlstore.test` / `DemoAdmin!2026`; change it before exposing any non-demo environment.
