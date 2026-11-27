<?php

namespace Tests\Unit;

use App\Application\Telegram\TelegramClient;
use App\Application\Telegram\TelegramSettings;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * The client must be boring on failure: a result object, no token in the
 * error text, and Telegram's retry hint preserved for the queue.
 */
class TelegramClientTest extends TestCase
{
    private const TOKEN = '555000111:AAHclient-unit-test-token-123456';

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('telegram.token', self::TOKEN);
        config()->set('telegram.chat_id', '-100555000111');
        config()->set('telegram.api_base', 'https://api.telegram.org');
    }

    public function test_a_missing_token_fails_without_any_network_call(): void
    {
        config()->set('telegram.token', null);
        Http::preventStrayRequests();

        $result = app(TelegramClient::class)->sendMessage('-100555000111', 'hi');

        $this->assertTrue($result->failed());
        $this->assertStringContainsString('not configured', (string) $result->error);
    }

    public function test_a_successful_send_returns_the_message_id(): void
    {
        Http::fake(['api.telegram.org/*' => Http::response(['ok' => true, 'result' => ['message_id' => 314]])]);

        $result = app(TelegramClient::class)->sendMessage('-100555000111', '<b>hi</b>');

        $this->assertTrue($result->ok);
        $this->assertSame(314, $result->messageId);
        $this->assertNull($result->error);

        Http::assertSent(function ($request) {
            return str_contains($request->url(), '/bot'.self::TOKEN.'/sendMessage')
                && $request['parse_mode'] === 'HTML'
                && $request['disable_web_page_preview'] === true;
        });
    }

    public function test_transport_failures_never_leak_the_bot_token(): void
    {
        Http::fake(function () {
            throw new ConnectionException('cURL error 28 for https://api.telegram.org/bot'.self::TOKEN.'/sendMessage');
        });

        $result = app(TelegramClient::class)->sendMessage('-100555000111', 'hi');

        $this->assertTrue($result->failed());
        $this->assertStringNotContainsString(self::TOKEN, (string) $result->error);
        $this->assertStringContainsString('[redacted]', (string) $result->error);
    }

    public function test_rate_limits_keep_telegrams_retry_after_hint(): void
    {
        Http::fake(['api.telegram.org/*' => Http::response([
            'ok' => false,
            'error_code' => 429,
            'description' => 'Too Many Requests: retry after 17',
            'parameters' => ['retry_after' => 17],
        ], 429)]);

        $result = app(TelegramClient::class)->sendMessage('-100555000111', 'hi');

        $this->assertTrue($result->failed());
        $this->assertTrue($result->isRateLimited());
        $this->assertSame(17, $result->retryAfter);
        $this->assertStringContainsString('Too Many Requests', (string) $result->error);
    }

    public function test_get_chat_exposes_the_chat_object(): void
    {
        Http::fake(['api.telegram.org/*' => Http::response(['ok' => true, 'result' => [
            'id' => -100555000111, 'title' => 'Ops', 'type' => 'supergroup',
        ]])]);

        $result = app(TelegramClient::class)->getChat('-100555000111');

        $this->assertTrue($result->ok);
        $this->assertSame('supergroup', $result->result()['type']);
    }

    public function test_the_masked_token_only_reveals_a_short_tail(): void
    {
        $masked = app(TelegramSettings::class)->maskedToken();

        $this->assertSame('555000111:••••••3456', $masked);
        $this->assertStringNotContainsString('client-unit-test-token', (string) $masked);
    }
}
