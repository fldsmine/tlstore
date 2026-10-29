# TLstore — Codex Handoff

This folder transfers the TLstore project context from the planning conversation into files Codex can read.

## Project
TLstore is an app-trust and review-intelligence platform with:
- Flutter/Dart mobile application
- Laravel REST API
- Laravel + Livewire admin panel
- Structured app assessments and evidence
- Permitted advertiser-funded tasks
- Optional membership access, subject to validation of the business model
- Wallet ledger, payment integrations, withdrawals, referrals, rankings, regional eligibility, notifications and reporting

## How to use this handoff
1. Extract this ZIP into a working directory, or copy `AGENTS.md`, `README.md`, and `docs/` into the root of your actual TLstore repository.
2. Open the repository root in VS Code and start Codex, or open a terminal in the repository root and run `codex`.
3. Codex should read `AGENTS.md` automatically. If it does not, explicitly tell it to read `AGENTS.md`, `README.md`, and `docs/TECHNICAL_SPECIFICATION.md`.
4. Use the prompt in `docs/CODEX_FIRST_PROMPT.md` as the first task.
5. Review diffs and test results before accepting changes. Commit checkpoints in Git are recommended.

## Important
This is a planning/handoff package, not a finished application. Provider credentials, domain names, exact package pricing rules, payout policy, regional policy and legal/compliance decisions must be supplied/approved before production activation.
