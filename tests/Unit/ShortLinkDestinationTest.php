<?php

namespace Tests\Unit;

use App\Application\Support\ShortLinkDestination;
use Tests\TestCase;

/**
 * ERR-26: the public short link hardcoded the production domains. The
 * destinations now follow `app.url` / `app.web_url`.
 */
class ShortLinkDestinationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config()->set('app.url', 'https://staging.tlstore.test');
        config()->set('app.web_url', 'https://web.staging.tlstore.test');
    }

    public function test_referral_links_follow_the_configured_app_url(): void
    {
        $this->assertSame(
            'https://staging.tlstore.test/ref/QA123TEST',
            ShortLinkDestination::forReferral('QA123TEST'),
        );
    }

    public function test_referral_codes_are_url_encoded(): void
    {
        $this->assertSame(
            'https://staging.tlstore.test/ref/A%2FB',
            ShortLinkDestination::forReferral('A/B'),
        );
    }

    public function test_app_paths_follow_the_configured_web_app_url(): void
    {
        $this->assertSame(
            'https://web.staging.tlstore.test/login',
            ShortLinkDestination::forAppPath('login'),
        );
    }

    public function test_unknown_paths_fall_back_to_registration(): void
    {
        $this->assertSame(
            'https://web.staging.tlstore.test/register',
            ShortLinkDestination::forAppPath('https://evil.example'),
        );
    }

    public function test_an_unconfigured_url_fails_instead_of_using_a_hardcoded_domain(): void
    {
        config()->set('app.web_url', '');

        $this->expectException(\RuntimeException::class);

        ShortLinkDestination::forAppPath('register');
    }
}
