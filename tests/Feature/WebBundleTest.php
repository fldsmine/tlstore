<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * ERR-17: the committed Flutter web bundle was a stale build whose API origin
 * was the legacy `gosmm.com`. The Flutter SDK is not part of this
 * environment, so the bundle cannot be rebuilt in PHPUnit — these assertions
 * at least prevent the legacy origin (and the retired deep-link hosts) from
 * being published again, and keep the POSIX build script next to the bundle.
 *
 * When the bundle is rebuilt with a different production origin, update the
 * expectation here deliberately (see review/tool/build_web.sh, API_BASE_URL).
 */
class WebBundleTest extends TestCase
{
    private const PRODUCTION_API_ORIGIN = 'https://tlstore.org/api/v1/';

    public function test_the_committed_web_bundle_targets_the_production_api(): void
    {
        $bundle = (string) file_get_contents(public_path('web/main.dart.js'));

        $this->assertNotSame('', $bundle, 'public/web/main.dart.js is missing or empty.');
        $this->assertStringNotContainsString('gosmm', $bundle, 'The web bundle still points at the retired gosmm.com origin.');
        $this->assertStringContainsString(self::PRODUCTION_API_ORIGIN, $bundle);
    }

    public function test_the_web_bundle_carries_no_retired_hosts(): void
    {
        $bundle = (string) file_get_contents(public_path('web/main.dart.js'));

        $this->assertStringNotContainsString('gptminpro.online', $bundle);
        $this->assertStringNotContainsString('10.145.107.216', $bundle);
    }

    public function test_the_web_entrypoint_loads_the_bundle(): void
    {
        $index = (string) file_get_contents(public_path('web/index.html'));

        $this->assertFileExists(public_path('web/flutter_bootstrap.js'));
        $this->assertStringContainsString('flutter_bootstrap.js', $index);
    }

    public function test_a_posix_web_build_script_exists(): void
    {
        $script = (string) file_get_contents(base_path('review/tool/build_web.sh'));

        $this->assertStringStartsWith('#!/usr/bin/env bash', $script);
        $this->assertStringContainsString('flutter build web', $script);
        $this->assertStringContainsString('--dart-define=API_BASE_URL', $script);
        $this->assertStringContainsString('--base-href=/web/', $script);
    }
}
