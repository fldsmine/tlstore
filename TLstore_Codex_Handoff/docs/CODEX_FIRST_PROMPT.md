You are taking over the TLstore implementation. First read the root `AGENTS.md`, `README.md`, and `docs/TECHNICAL_SPECIFICATION.md`.

Task:
1. Inspect the repository and report whether it is empty, partially initialized, or already contains code.
2. Check installed toolchain versions (Flutter/Dart, PHP, Composer, Laravel, Node/npm if relevant) without changing production systems.
3. Summarize the current state, risks, and missing decisions.
4. Create `docs/DECISIONS.md` listing unresolved business decisions, especially membership eligibility, reward triggers, currency settlement, referral commission base, payout verification, supported countries, and advertising-provider rules.
5. Propose a short Phase 1 implementation checklist, then implement only the approved foundation in this working repository: Laravel project structure if missing, environment example, initial migrations for identity/access and currency/ledger foundations, API conventions, and tests.
6. If the repository is empty and the mobile app is to be created in this same workspace, scaffold it only after confirming the available Flutter SDK. Keep backend and mobile clearly separated.
7. Run relevant tests and report exact commands/results. Do not claim tests passed unless they ran successfully.
8. Review the final diff and list changed files.

Constraints:
- Do not deploy.
- Do not connect to live payment providers or make real payments.
- Do not run destructive database commands or `migrate:fresh`.
- Do not invent credentials, provider secrets, domain names, or unresolved business rules.
- Follow `AGENTS.md` and the technical specification.
