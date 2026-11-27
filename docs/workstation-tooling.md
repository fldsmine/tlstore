# TLStore / TrustLens — Workstation Development & Testing Tooling
**Last Updated:** 2026-11-27  
**Workspace Root:** `/home/user/tlstore`
To support full-stack implementation, static analysis, security scanning, database inspection, API fuzzing, load testing, and frontend verification, a complete development toolchain has been installed and configured on this workstation.
---
## 1. Installed Tooling Inventory
### 1.1 PHP & Laravel Backend Toolchain
| Tool | Binary Path | Version | Purpose |
|---|---|---|---|
| **FrankenPHP** | `/usr/local/bin/frankenphp` | `v1.13.1` (PHP `8.5.12` + 78 extensions, Caddy `v2.11.7`) | Primary application server & full-featured PHP CLI (`frankenphp php-cli`) including `pdo_sqlite`, `pdo_mysql`, `xmlwriter`, `dom`, `gd`, `intl`, `mbstring`, `curl`, `openssl`, `zip`, `bcmath`. |
| **PHP CLI** | `/usr/local/bin/php` | `8.4.21` (NTS musl) | Fast standalone PHP CLI for syntax linting & Composer scripts. |
| **Composer** | `/usr/local/bin/composer` | `2.9.8` | PHP dependency & autoloader management (`vendor/` installed with all 115 packages). |
| **PHPUnit** | `/usr/local/bin/phpunit` | `12.5.45` | Wrapper executing `frankenphp php-cli ./vendor/bin/phpunit "$@"` with full XML/DOM/SQLite extension support. |
| **PHPStan** | `/usr/local/bin/phpstan` | `2.3.x-dev` | Static type & bug analyzer (catches missing imports, wrong argument counts, undefined variables, relation return mismatches). |
| **Laravel Pint** | `/usr/local/bin/pint` | `1.32.3` | PHP code style fixer & checker (`pint app config database routes tests --test`). |
### 1.2 Database & SQL Inspection Tools
| Tool | Binary Path | Version | Purpose |
|---|---|---|---|
| **SQLite CLI** | `/usr/local/bin/sqlite3` | `3.40.1` | Command-line SQL runner supporting `.tables`, `.schema`, and direct SQL queries against `database/database.sqlite`. |
| **sqlite-utils** | `/usr/local/bin/sqlite-utils` | `4.2.1` | Schema inspection, JSON/CSV table dumps, index analysis, and quick fixture manipulation. |
| **LiteCLI** | `/usr/local/bin/litecli` | `1.17.1` | Interactive auto-completing SQLite client. |
| **SQLFluff** | `/usr/local/bin/sqlfluff` | `4.5.0` | SQL linter and formatter (`sqlite`, `mysql`, `postgres` dialects). |
### 1.3 Frontend, JavaScript/TypeScript & Asset Toolchain
| Tool | Binary Path | Version | Purpose |
|---|---|---|---|
| **Node.js / npm** | `/usr/local/bin/node`, `npm` | `v22.22.3` / `10.9.8` | JS runtime & package manager (`node_modules/` installed). |
| **Vite** | `npx vite` | `7.3.8` | Builds Laravel TailwindCSS v4 + JS assets into `public/build/manifest.json`. |
| **TypeScript** | `/usr/local/bin/tsc` | `7.0.2` | JS/TS type-checking (`tsc --allowJs --checkJs --noEmit`). |
| **ESLint** | `/usr/local/bin/eslint` | `10.15.0` | JavaScript linter. |
| **Prettier** | `/usr/local/bin/prettier` | `3.10.1` | Code formatter for JS, CSS, JSON, YAML, Blade/HTML, and Markdown. |
| **Stylelint** | `/usr/local/bin/stylelint` | `18.1.0` | CSS / Tailwind stylesheet linter. |
| **HTMLHint** | `/usr/local/bin/htmlhint` | `1.9.2` | HTML markup validator for rendered views and static pages. |
| **Vitest** | `/usr/local/bin/vitest` | `5.1.2` (+ `jsdom` / `happy-dom`) | Fast unit & DOM testing for frontend scripts (`resources/js/`). |
| **Playwright CLI** | `/usr/local/bin/playwright` | `1.65.1` | E2E test runner & request/API testing harness. |
### 1.4 API Testing, Fuzzing, Load Testing & Security Analysis
| Tool | Binary Path | Version | Purpose |
|---|---|---|---|
| **HTTPie** | `/usr/local/bin/http` | `3.2.4` | Ergonomic CLI HTTP client for testing `/api/v1/*` JSON endpoints and webhooks. |
| **Pytest + HTTPX + Faker** | `/usr/local/bin/pytest` | `9.2.0` (`httpx 0.29.0`, `faker 40.63.0`) | End-to-end black-box API & webhook integration test suite runner. |
| **Schemathesis** | `/usr/local/bin/schemathesis` (`st`) | `4.34.3` | Property-based OpenAPI/JSON-Schema API fuzzer. |
| **Redocly CLI** | `/usr/local/bin/redocly` | `2.84.0` | OpenAPI specification linting & bundling. |
| **Newman** | `/usr/local/bin/newman` | `6.2.3` | Postman collection CLI runner for automated API regression suites. |
| **Autocannon** | `/usr/local/bin/autocannon` | `9.0.0` | High-concurrency HTTP/1.1 benchmarking & race-condition stress tester (ideal for wallet/idempotency lock testing). |
| **Semgrep** | `/usr/local/bin/semgrep` | `1.187.0` | Multi-language SAST & custom rule scanner across PHP, Dart, JS, and Blade (`semgrep --offline ...`). |
| **ast-grep (`sg`)** | `/usr/local/bin/ast-grep` (`/usr/local/bin/sg`) | `0.51.3` | Structural AST search & refactoring across PHP, Dart, JS, HTML, and CSS. |
| **CSpell** | `/usr/local/bin/cspell` | `10.5.2` | Code & UI copy spellchecker (catches enum/UI typos like `Referal`). |
| **markdownlint-cli2** | `/usr/local/bin/markdownlint-cli2` | `0.24.2` | Documentation linter for `docs/*.md`. |
| **Ruff / Mypy / Bandit** | `/usr/local/bin/{ruff,mypy,bandit}` | `0.17.6` / `2.4.1` / `1.9.4` | Linting, type-checking, and security scanning for Python test harnesses/scripts. |
| **ripgrep / jq / gh** | `/usr/bin/{rg,jq,gh}` | `13.0.0` / `1.6` / `2.23.0` | Codebase search, JSON manipulation, and GitHub PR/Issue CLI. |
---
## 2. Environment Notes & Sandbox Constraints
1. **Running Laravel Artisan & PHPUnit:**
   - Use **`frankenphp php-cli artisan <command>`** whenever an Artisan command needs SQLite (`pdo_sqlite`), DOM, GD, or XMLWriter.
   - Run tests via **`phpunit`** (do **not** use `php artisan test`, because `artisan test` invokes `PHP_BINARY` without FrankenPHP's `php-cli` subcommand; see `ERR-38` in [`project-errors.md`](./project-errors.md)).
2. **Laravel `.env` & Vite Build:**
   - `.env` is initialized in `/home/user/tlstore/.env` with a generated `APP_KEY` and SQLite configured at `database/database.sqlite`.
   - Frontend assets are built at `public/build/manifest.json` (`npm run build`).
3. **Serving the Application for Live Preview / Integration Tests:**
   - Bind to `0.0.0.0` using FrankenPHP:
     ```bash
     frankenphp php-server --listen 0.0.0.0:8000 --root public
     ```
4. **Flutter / Dart SDK Note:**
   - Official Google Dart/Flutter binary mirrors (`storage.googleapis.com`, `dl.google.com`) and GitHub Release binary S3 redirects (`objects.githubusercontent.com`) are unreachable under this sandbox's outbound network allowlist (`github.com`, `codeload.github.com`, `api.github.com`, `registry.npmjs.org`, `pypi.org`, `files.pythonhosted.org`).
   - Dart files in `review/lib/` can be structurally analyzed and refactored locally using **`ast-grep`**, **`semgrep --offline`**, and **`cspell`**, while CI/workstations with `storage.googleapis.com` access should run `flutter pub get && flutter analyze && flutter test`.
---
## 3. Common Verification Commands
```bash
# 1. PHP syntax check across all backend files
find app config database routes tests -name '*.php' -print0 | xargs -0 -n1 -P4 frankenphp php-cli -l
# 2. Static analysis (PHPStan Level 5)
phpstan analyse --level=5 --no-progress app config database routes tests
# 3. Code style check (Laravel Pint — always scope paths to skip public/web/)
pint app config database routes tests --test
# 4. Run PHPUnit test suite
phpunit
# 5. Inspect SQLite database tables & row counts
sqlite-utils tables --counts database/database.sqlite
# 6. Rebuild Laravel Vite assets
npm run build
# 7. Benchmark / race-test an API endpoint (e.g., idempotency verification)
autocannon -c 10 -d 5 http://127.0.0.1:8000/api/v1/health
```
