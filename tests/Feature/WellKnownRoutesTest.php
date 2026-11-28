<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * ERR-28: the app-link verification files Android and iOS fetch before opening
 * a link in the app did not exist, so `autoVerify` silently fell back to the
 * browser. They are served from configuration and answer 404 until configured.
 */
class WellKnownRoutesTest extends TestCase
{
    private const PACKAGE = 'pynith.tlstore.review';

    public function test_assetlinks_is_not_published_until_configured(): void
    {
        config()->set('trustlens.deep_links', [
            'android_package' => self::PACKAGE,
            'android_sha256_fingerprints' => [],
            'ios_app_id' => null,
            'ios_paths' => ['/register'],
        ]);

        $this->get('/.well-known/assetlinks.json')->assertNotFound();
    }

    public function test_assetlinks_serves_the_android_app_link_relationship(): void
    {
        config()->set('trustlens.deep_links', [
            'android_package' => self::PACKAGE,
            'android_sha256_fingerprints' => ['ab:cd:ef', '12:34:56'],
            'ios_app_id' => null,
            'ios_paths' => ['/register'],
        ]);

        $response = $this->get('/.well-known/assetlinks.json')
            ->assertOk()
            ->assertJsonPath('0.relation.0', 'delegate_permission/common.handle_all_urls')
            ->assertJsonPath('0.target.namespace', 'android_app')
            ->assertJsonPath('0.target.package_name', self::PACKAGE)
            // Fingerprints are normalised to upper-case in config.
            ->assertJsonPath('0.target.sha256_cert_fingerprints.0', 'AB:CD:EF');

        $this->assertStringContainsString('application/json', (string) $response->headers->get('Content-Type'));
    }

    public function test_apple_app_site_association_is_not_published_until_configured(): void
    {
        config()->set('trustlens.deep_links.ios_app_id', null);

        $this->get('/.well-known/apple-app-site-association')->assertNotFound();
    }

    public function test_apple_app_site_association_serves_the_universal_link_details(): void
    {
        config()->set('trustlens.deep_links.ios_app_id', 'A1B2C3D4E5.pynith.tlstore.review');
        config()->set('trustlens.deep_links.ios_paths', ['/register', '/ref/*']);

        $response = $this->get('/.well-known/apple-app-site-association')
            ->assertOk()
            ->assertJsonPath('applinks.details.0.appID', 'A1B2C3D4E5.pynith.tlstore.review')
            ->assertJsonPath('applinks.details.0.paths.0', '/register')
            ->assertJsonPath('applinks.details.0.paths.1', '/ref/*');

        $this->assertStringContainsString('application/json', (string) $response->headers->get('Content-Type'));
    }
}
