# Sandbox toolchain (local only — not tracked by git, see .git/info/exclude)

This sandbox has **no system PHP, no Composer, no FrankenPHP**, and outbound network is
limited to `github.com`, `codeload.github.com`, `api.github.com`, `registry.npmjs.org`,
`pypi.org`, `files.pythonhosted.org`. `packagist.org` and `getcomposer.org` are blocked,
so `composer install` is impossible. The stack below reproduces a working PHP + vendor/
so `artisan`, `phpunit` and the dev server run.

## Pieces

| Piece | How it is provided |
|---|---|
| PHP 8.5.12 (CLI + built-in server) | `@php-wasm/cli` (npm, WASM build, host FS mounted) wrapped as `.tools/bin/php` |
| `vendor/` (115 packages) | `.tools/composer-install.py` downloads the `api.github.com` zipballs recorded in `composer.lock` |
| `vendor/autoload.php` | `.tools/gen-autoload.php` (Composer-compatible ClassLoader + generated psr-4/classmap/files maps) |
| `vendor/composer/installed.json` | written by `.tools/composer-install.py` so `PackageManifest` discovery works |
| `node_modules/`, `public/build/` | `npm install && npm run build` (works normally) |
| `.env`, sqlite db | `.env` generated from `.env.example` (secrets blanked, `APP_KEY` set, `CACHE_STORE=file`) |

## Rebuild from scratch (idempotent, ~60s)

```bash
npm install --no-save --no-audit --no-fund @php-wasm/cli
python3 .tools/composer-install.py --dev
.tools/bin/php .tools/gen-autoload.php
npm install && npm run build
```

## Usage

```bash
.tools/bin/php artisan about
.tools/bin/php vendor/phpunit/phpunit/phpunit      # NOT `artisan test` (ERR-38)
.tools/bin/php -S 0.0.0.0:8000 -t public           # dev server / live preview
.tools/bin/php -l app/Foo.php                      # lint
```

## Caveats

1. **`@php-wasm/cli` always exits 0** even when the PHP script fails — never trust `$?`,
   parse output (`grep -q "ERRORS!"` etc.).
2. Do not pipe long php output into `head`; the CLI throws `EPIPE` and prints a Node crash.
   Redirect to a temp file and read the tail instead.
3. PHP is 8.5.12 (project targets ^8.3). `intl`, `pcntl`, `posix`, `redis` extensions are
   absent; `pdo_sqlite`, `dom`, `mbstring`, `openssl`, `zip`, `gd`, `bcmath`, `curl` are present.
   No real network from PHP (curl exists but cannot reach the internet).
4. Missing vs. the old audit workstation: FrankenPHP, real Composer/PHPStan/Pint binaries,
   sqlite3/litecli/sqlfluff CLIs, ESLint/Prettier/TS/Playwright/Vitest, semgrep/ast-grep
   (`sg` here is shadow-utils, not ast-grep), pytest/schemathesis/newman/autocannon,
   and the Flutter/Dart SDK (so `review/` can be edited but not compiled →
   `public/web/` cannot be rebuilt here, ERR-17).
5. `vendor/` and `node_modules/` live in the repo but are gitignored; they may not survive
   between sandbox sessions — rerun the rebuild block above.
