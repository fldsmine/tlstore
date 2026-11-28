<?php

namespace Tests\Unit;

use App\Application\Telegram\TelegramHtml;
use Tests\TestCase;

/**
 * Telegram rejects a message outright when its HTML cannot be parsed, so the
 * sanitiser is the guard between admin input and the Bot API.
 */
class TelegramHtmlTest extends TestCase
{
    public function test_allowed_formatting_survives_and_aliases_are_normalised(): void
    {
        $html = TelegramHtml::sanitize(
            '<strong>Bold</strong> <em>italic</em> <u>under</u> <del>gone</del> <code>x=1</code><pre>block</pre>',
        );

        $this->assertSame(
            '<b>Bold</b> <i>italic</i> <u>under</u> <s>gone</s> <code>x=1</code><pre>block</pre>',
            $html,
        );
    }

    public function test_unknown_tags_are_unwrapped_and_attributes_removed(): void
    {
        $html = TelegramHtml::sanitize('<div class="x"><span style="color:red">Hello</span> <b onclick="evil()">world</b></div>');

        $this->assertSame('Hello <b>world</b>', $html);
    }

    public function test_script_and_style_content_is_discarded(): void
    {
        $html = TelegramHtml::sanitize('<script>alert(1)</script><style>body{}</style><b>Safe</b>');

        $this->assertSame('<b>Safe</b>', $html);
        $this->assertStringNotContainsString('alert', $html);
    }

    public function test_only_safe_link_schemes_are_kept(): void
    {
        $html = TelegramHtml::sanitize(
            '<a href="javascript:alert(1)">bad</a> <a href="https://tlstore.org" target="_blank">good</a> <a href="tg://resolve?domain=tlstore">tg</a>',
        );

        $this->assertStringNotContainsString('javascript', $html);
        $this->assertStringNotContainsString('target=', $html);
        $this->assertStringContainsString('<a href="https://tlstore.org">good</a>', $html);
        $this->assertStringContainsString('<a href="tg://resolve?domain=tlstore">tg</a>', $html);
    }

    public function test_text_is_escaped_and_line_breaks_survive(): void
    {
        $html = TelegramHtml::sanitize("Fish & chips < 5\nsecond line <b>bold</b>");

        $this->assertSame("Fish &amp; chips &lt; 5\nsecond line <b>bold</b>", $html);
    }

    public function test_visible_length_ignores_markup(): void
    {
        $this->assertSame(11, TelegramHtml::visibleLength('<b>Hello</b> world'));
        $this->assertSame(0, TelegramHtml::visibleLength('<b></b>'));
    }
}
