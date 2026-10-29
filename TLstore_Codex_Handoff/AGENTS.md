# TLstore — Codex Project Instructions

## Project goal
Build TLstore as a Flutter/Dart mobile app, a Laravel REST API backend, and a Laravel + Livewire administration panel. The backend is the source of truth for authorization, task eligibility, rewards, subscriptions, payments, wallet balances, referrals, and withdrawals.

## Before coding
1. Read `README.md` and every relevant document under `docs/`.
2. Inspect the actual repository and installed framework/package versions before making assumptions.
3. If this is an empty repository, propose and implement the repository structure described in `docs/TECHNICAL_SPECIFICATION.md`.
4. Work in small, reviewable phases. Do not attempt to generate the entire production system in one change.
5. State assumptions and unresolved business decisions in `docs/DECISIONS.md`; do not silently invent financial rules.

## Architecture
- `mobile/`: Flutter app using Dart, Riverpod, GoRouter, Dio, secure token storage, feature-first Clean Architecture.
- `backend/`: Laravel API and Livewire admin panel in the same Laravel application initially.
- API prefix: `/api/v1`.
- MySQL/InnoDB for persistent data; Redis/queues where appropriate.
- Laravel Sanctum for mobile bearer tokens; separate session-based authentication for admin.
- Use Form Requests, API Resources, Policies, domain services/actions, database transactions, jobs, events and listeners.
- Keep controllers thin and business rules in services/domain classes.
- Keep API DTOs separate from Flutter domain entities.

## Financial safety rules
- Use fixed-precision DECIMAL values, never floating-point types for money.
- Use an append-only double-entry ledger. Posted entries are not edited; corrections use explicit reversals.
- Use idempotency keys and unique source references for payment credits, rewards, commissions and payouts.
- Verify payment webhooks using the provider's official signature/authentication method and verify amounts, currency, merchant account, and transaction status.
- Never trust a client-supplied payment success, balance, reward amount, task status or permission.
- Reserve funds before payout; release/reverse reservations through auditable operations on failure.
- Use maker-checker approval for higher-risk financial actions.
- Do not seed fake financial balances in production.
- Keep secrets out of source control and redact sensitive information from logs.

## Assessment and advertising integrity
- Reward honest, evidence-based app assessments, not positive public app-store reviews or rating manipulation.
- Do not implement artificial ad clicks/impressions/shares or bypass of app-store, advertiser or regional rules.
- Only reward advertising events permitted by the provider and validated by a trusted source or approved validation process.
- Preserve questionnaire versions and submitted evidence history.
- Use explicit task state transitions and server-side eligibility checks.

## Security
- Enforce authorization on every server-side endpoint/action; hiding a UI control is not authorization.
- Prevent IDOR, CSRF, SQL injection, XSS, unsafe file uploads, duplicate webhook processing and race conditions.
- Validate uploaded files and keep sensitive evidence in private storage.
- Use rate limiting, secure sessions, token revocation, audit trails and appropriate MFA for privileged admin users.
- Do not log passwords, tokens, provider secrets or unnecessary personal data.

## Development workflow
1. Inspect the repository and report current state.
2. Identify the smallest coherent implementation step.
3. Add/update migrations, code, tests and documentation together.
4. Run relevant formatting, static analysis and tests. Report commands and results honestly.
5. Inspect `git diff` before finishing and summarize changed files, assumptions, tests and remaining risks.
6. Do not deploy, run destructive database commands, change production data, or make real payment/payout calls without explicit approval.
7. Never run `migrate:fresh` or destructive reset commands against any non-disposable database.
8. Keep dependencies compatible with the actual installed versions; do not guess package versions.

## First task
Start with repository reconnaissance. Then implement Phase 1 only: project skeleton, environment examples, database foundations, authentication, role/permission setup, API conventions, audit logging foundation, and tests. Before creating financial posting logic, document the ledger design and invariants and add tests.
