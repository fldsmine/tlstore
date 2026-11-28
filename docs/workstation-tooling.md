# TLStore / TrustLens — Workstation & Sandbox Tooling

**Last Updated:** 2026-11-27 (Phase 1)
**Workspace Root:** `/home/user/tlstore`

This document describes **the actual toolchain available in the current sandbox**. It
replaces the earlier inventory that listed system binaries (`/usr/local/bin/php`,
FrankenPHP, Composer, PHPStan, SQL CLIs, Flutter) — none of which exist here, because the
sandbox only permits outbound traffic to `github.com`, `codeload.github.com`,
`api.github.com`, `registry.npmjs.org`, `pypi.org` and `files.pythonhosted.org`.
`packagist.org`, `getcomposer.org`, GitHub *release assets* (`objects.githubusercontent.com`)
and the Dart/Flutter mirrors are unreachable, so Composer and the Flutter SDK cannot be
installed. `.tools/` (see below) works around the first of those.

---

## 1. What is available

| Component | Version | How it is provided |
|---|---|---|
| PHP CLI + built-in web server | **8.5.12** (WASM) | `@php-wasm/cli` from npm, wrapped as `.tools/bin/php` |
| `vendor/` (115 packages, incl. dev) | per `composer.lock` | `.tools/composer-install.py` (downloads the `api.github.com` zipballs recorded in the lock file) |
| `vendor/autoload.php` + class maps | Composer-compatible | `.tools/gen-autoload.php` (uses Composer's real `ClassLoader`) |
| PHPUnit | 12.5.x | `vendor/phpunit/phpunit/phpunit` |
| Laravel Pint (code style) | phar bundled in `laravel/pint` | `vendor/laravel/pint/builds/pint` |
| Node.js / npm | 22.22.3 / 10.9.8 | system |
| Vite + TailwindCSS v4 | per `package.json` | `npm install && npm run build` |
| Python 3 | 3.11 | system (`sqlite3` module available for ad-hoc SQL) |
| git, gh, jq, curl, ripgrep | current | system |

## 2. What is **not** available (and the workaround)

| Missing | Impact | Workaround |
|---|---|---|
| Composer | `composer install/require/dump-autoload` | `.tools/composer-install.py` + `.tools/gen-autoload.php` |
| FrankenPHP / Caddy | production-like server, HTTP/2, workers | PHP's built-in server (`.tools/bin/php -S 0.0.0.0:8000 -t public`) |
| PHPStan | static analysis | PHPUnit + `php -l` + careful review; run PHPStan in CI |
| Flutter / Dart SDK | `flutter analyze`, `flutter test`, `flutter build web` | **Dart files can be edited but never compiled here** — review them by hand; build on a machine with the SDK (`public/web/` cannot be rebuilt here, see `ERR-17`) |
| sqlite3 / litecli / sqlfluff | DB inspection | `php -r '$p=new PDO("sqlite:..."); ...'` or Python's `sqlite3` |
| ESLint / Prettier / TSC / Vitest / Playwright / Stylelint | JS lint & E2E | `npx <tool>` when the package registry has it; Playwright browsers cannot be downloaded |
| semgrep / ast-grep | SAST & structural refactors | `rg` + review (note: `/usr/bin/sg` is *shadow-utils*, not ast-grep) |
| pytest / schemathesis / newman / autocannon | API fuzz & load tests | curl/HTTPie-style manual checks against the dev server; PHPUnit feature tests |

## 3. Rebuilding the toolchain (idempotent, ~1 minute)

```bash
npm install --no-save --no-audit --no-fund @php-wasm/cli
python3 .tools/composer-install.py --dev
.tools/bin/php .tools/gen-autoload.php
npm install && npm run build
```

The scripts are committed so any sandbox or CI runner with the same network policy can
reproduce the environment.

## 4. Common commands

```bash
# Application boot / introspection
.tools/bin/php artisan about
.tools/bin/php artisan route:list --path=admin

# Test suite (NEVER `artisan test`: PHP_BINARY points at the WASM CLI)
.tools/bin/php vendor/phpunit/phpunit/phpunit

# Style check (scope paths; public/web/ and vendored code are excluded)
.tools/bin/php vendor/laravel/pint/builds/pint --test app config database routes tests

# Syntax lint
.tools/bin/php -l app/Providers/AppServiceProvider.php

# Dev server / live preview
.tools/bin/php -S 0.0.0.0:8000 -t public

# Database (dev copy, keeps the committed sqlite file clean)
.tools/bin/php -r '$p=new PDO("sqlite:.tools/tmp/dev.sqlite"); foreach($p->query("select name from sqlite_master where type=\"table\"") as $r) echo $r[0],"\n";'
```

## 5. Sandbox caveats (read before trusting a result)

1. **Exit codes are meaningless.** `@php-wasm/cli` exits `0` even when the PHP script
   fails. Parse output (`grep -q "ERRORS!"`, `grep -q "PASS"`), never rely on `$?`.
2. **`flock(LOCK_SH)` is unsupported** by the WASM filesystem, so Laravel's *file* cache
   and *file* session drivers silently miss on every read. Local `.env` therefore uses
   `CACHE_STORE=database` (against `.tools/tmp/dev.sqlite`); PHPUnit uses the `array`
   driver. A file-cache "miss" here is a sandbox artefact, **not** an application bug.
3. **No network from PHP.** `curl`/`openssl` exist, but outbound calls (VTpass, Korapay,
   FCM, SMTP) cannot succeed — mock them in tests.
4. **PHP 8.5.12 vs `^8.3`.** Missing extensions: `intl`, `pcntl`, `posix`, `redis`.
   Present: `pdo_sqlite`, `dom`, `mbstring`, `openssl`, `zip`, `gd`, `bcmath`, `curl`.
5. **Don't pipe long PHP output into `head`** — the WASM CLI raises `EPIPE` and prints a
   Node crash. Redirect to a file and read the tail.
6. The dev server serves the app correctly, but its single-threaded WASM loop logs
   `Failed to accept a client` noise; concurrent-request tests (load/race testing) are not
   meaningful here.
7. **PHPUnit cannot drive `RefreshDatabase` unpatched.** `Illuminate\Testing\PendingCommand`
   runs the migration from its destructor, and executing an artisan command re-entrantly
   aborts the PHP-WASM VM (`RuntimeError: unreachable`) with no test output at all.
   `.tools/apply-sandbox-patches.php` appends the missing `->run()` to
   `vendor/.../Foundation/Testing/RefreshDatabase.php`; run it again after any dependency
   reinstall. It patches `vendor/` **only** — CI and production use the stock framework.
8. **PHPStan is not a project dependency.** PHPStan 2.3.7 can be pulled from its GitHub
   phar (`gh api repos/phpstan/phpstan/git/blobs/<sha>` with the raw media type) and runs,
   but without `larastan` a Laravel codebase produces hundreds of Eloquent magic-property
   false positives. Treat it as a smoke check, not a gate, until Phase 6 adds `larastan`
   and a baseline.
9. **`vendor/autoload.php` is generated** by `.tools/gen-autoload.php` (no Composer) and
   now guards `Composer\Autoload\ClassLoader` with `class_exists(..., false)` so phar
   tools (PHPStan, PHPUnit) can bootstrap it after declaring the class themselves.
