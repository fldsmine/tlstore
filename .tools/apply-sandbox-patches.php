<?php
/**
 * Sandbox-only patches for vendored Laravel code.
 *
 * These are NOT application fixes - `vendor/` is gitignored and CI runs the real
 * framework. They exist because PHP compiled to WebAssembly (the only PHP
 * available in this sandbox) aborts with an opaque `RuntimeError: unreachable`
 * in two places, which would otherwise make the test suite unverifiable here.
 *
 * Run: .tools/bin/php .tools/apply-sandbox-patches.php   (after composer-install.py)
 */

$patches = [];

/**
 * 1. PendingCommand::__destruct() aborts the WASM VM.
 *
 * RefreshDatabase fires `migrate:fresh` through `$this->artisan(...)` and relies
 * on PendingCommand's destructor to execute it. Under PHP-WASM that destructor
 * path kills the process, so call `run()` explicitly instead.
 */
$patches[] = [
    'file' => 'vendor/laravel/framework/src/Illuminate/Foundation/Testing/RefreshDatabase.php',
    'from' => "        \$this->artisan('migrate:fresh', \$this->migrateFreshUsing());",
    'to' => "        // Sandbox patch (PHP-WASM): run the command explicitly instead of\n"
        ."        // letting PendingCommand::__destruct() execute it.\n"
        ."        \$this->artisan('migrate:fresh', \$this->migrateFreshUsing())->run();",
];

$root = dirname(__DIR__);
$applied = 0;

foreach ($patches as $patch) {
    $path = $root.'/'.$patch['file'];

    if (! is_file($path)) {
        fwrite(STDERR, "missing: {$patch['file']} (run the installer first)\n");

        continue;
    }

    $source = file_get_contents($path);

    if (str_contains($source, $patch['to'])) {
        echo "already patched: {$patch['file']}\n";

        continue;
    }

    if (! str_contains($source, $patch['from'])) {
        fwrite(STDERR, "pattern not found: {$patch['file']} - framework version changed?\n");

        continue;
    }

    file_put_contents($path, str_replace($patch['from'], $patch['to'], $source));
    echo "patched: {$patch['file']}\n";
    $applied++;
}

echo "{$applied} patch(es) applied\n";
