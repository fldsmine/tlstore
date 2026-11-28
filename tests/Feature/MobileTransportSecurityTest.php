<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * ERR-26 / ERR-27 guards for artefacts PHPUnit can see but that Flutter
 * analysis does not cover: the release manifests must stay HTTPS-only and the
 * public short link must not hardcode a production domain again.
 *
 * The Flutter toolchain is not available in CI here, so these file checks are
 * the only automatic protection against the settings drifting back.
 */
class MobileTransportSecurityTest extends TestCase
{
    public function test_release_android_manifest_is_https_only(): void
    {
        $manifest = (string) file_get_contents(base_path('review/android/app/src/main/AndroidManifest.xml'));

        $this->assertStringContainsString('android:usesCleartextTraffic="false"', $manifest);
        $this->assertStringNotContainsString('gptminpro.online', $manifest);
        $this->assertStringNotContainsString('10.145.107.216', $manifest);
        $this->assertStringNotContainsString('android:scheme="http"', $manifest);
        $this->assertStringContainsString('android:host="tlstore.org"', $manifest);
        $this->assertStringContainsString('android:host="web.tlstore.org"', $manifest);
    }

    public function test_debug_builds_can_still_reach_a_local_http_server(): void
    {
        $manifest = (string) file_get_contents(base_path('review/android/app/src/debug/AndroidManifest.xml'));

        $this->assertStringContainsString('android:usesCleartextTraffic="true"', $manifest);
        $this->assertStringContainsString('tools:replace="android:usesCleartextTraffic"', $manifest);
    }

    public function test_ios_transport_security_no_longer_allows_arbitrary_loads(): void
    {
        $plist = (string) file_get_contents(base_path('review/ios/Runner/Info.plist'));

        $this->assertStringNotContainsString('NSAllowsArbitraryLoads', $plist);
        $this->assertStringContainsString('NSAllowsLocalNetworking', $plist);
    }

    public function test_public_short_link_does_not_hardcode_a_production_domain(): void
    {
        $script = (string) file_get_contents(public_path('redirect.php'));

        $this->assertStringNotContainsString('tlstore.org', $script);
        $this->assertStringContainsString('ShortLinkDestination', $script);
    }
}
