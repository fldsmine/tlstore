<?php

/**
 * Short links used by QR codes, printed material and social bios:
 *
 *   /redirect.php?ref=CODE        -> {APP_URL}/ref/CODE
 *   /redirect.php?path=register   -> {WEB_APP_URL}/register  (`login` also allowed)
 *
 * This file is served directly by the web server (it is not routed through
 * Laravel's front controller), so it boots the framework far enough to read
 * the configured URLs. The destinations used to be hardcoded to the
 * production domains, which sent staging/preview visitors to live (ERR-26).
 */

$referralCode = strtoupper(trim((string) ($_GET['ref'] ?? '')));
$path = (string) ($_GET['path'] ?? 'register');

try {
    require __DIR__.'/../vendor/autoload.php';

    /** @var \Illuminate\Foundation\Application $app */
    $app = require __DIR__.'/../bootstrap/app.php';
    $app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

    $destination = $referralCode !== '' && preg_match('/^[A-Z0-9_-]{1,64}$/', $referralCode) === 1
        ? \App\Application\Support\ShortLinkDestination::forReferral($referralCode)
        : \App\Application\Support\ShortLinkDestination::forAppPath($path);
} catch (\Throwable $exception) {
    // Never fall back to a hardcoded domain: without configuration this short
    // link must fail closed rather than send traffic to the wrong host.
    http_response_code(503);
    header('Retry-After: 60');
    header('Content-Type: text/plain; charset=UTF-8');
    echo 'This short link is temporarily unavailable.';
    exit;
}

header('Location: '.$destination, true, 302);
header('Cache-Control: no-store, no-cache, must-revalidate');
exit;
