<?php

declare(strict_types=1);

namespace App\Services\AppListings;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

/**
 * Thin client around the chat-style APIs the supported AI providers expose.
 *
 * Three drivers cover everything in config/ai-listings.php:
 *  - openai    : OpenAI itself and any OpenAI-compatible gateway (Groq,
 *                OpenRouter, DeepSeek, Mistral, xAI, a local Ollama, ...)
 *  - anthropic : Claude models
 *  - gemini    : Google generative language models
 */
class AgentClient
{
    /**
     * Ask one agent for a completion and return the raw text it produced.
     *
     * @param  array<string, mixed>  $agent
     */
    public function complete(array $agent, string $system, string $user): string
    {
        return match ($agent['driver'] ?? 'openai') {
            'anthropic' => $this->anthropic($agent, $system, $user),
            'gemini' => $this->gemini($agent, $system, $user),
            'openai', 'openai-compatible' => $this->openAi($agent, $system, $user),
            default => throw new RuntimeException(
                'Unsupported AI driver ['.($agent['driver'] ?? 'null').'] for agent ['.($agent['name'] ?? 'unnamed').'].'
            ),
        };
    }

    /**
     * @param  array<string, mixed>  $agent
     */
    private function openAi(array $agent, string $system, string $user): string
    {
        $payload = [
            'model' => $agent['model'],
            'temperature' => (float) ($agent['temperature'] ?? 0.9),
            'messages' => [
                ['role' => 'system', 'content' => $system],
                ['role' => 'user', 'content' => $user],
            ],
        ];

        if (($agent['json_mode'] ?? false) === true) {
            $payload['response_format'] = ['type' => 'json_object'];
        }

        $response = $this->request($agent)
            ->withToken((string) $agent['api_key'])
            ->post($this->url($agent['base_url'], '/chat/completions'), $payload)
            ->throw();

        return $this->read($agent, $response->json(), 'choices.0.message.content');
    }

    /**
     * @param  array<string, mixed>  $agent
     */
    private function anthropic(array $agent, string $system, string $user): string
    {
        $response = $this->request($agent)
            ->withHeaders([
                'x-api-key' => (string) $agent['api_key'],
                'anthropic-version' => (string) ($agent['anthropic_version'] ?? '2023-06-01'),
            ])
            ->post($this->url($agent['base_url'], '/v1/messages'), [
                'model' => $agent['model'],
                'max_tokens' => (int) ($agent['max_tokens'] ?? 4096),
                'temperature' => (float) ($agent['temperature'] ?? 0.9),
                'system' => $system,
                'messages' => [
                    ['role' => 'user', 'content' => $user],
                ],
            ])->throw();

        return $this->read($agent, $response->json(), 'content.0.text');
    }

    /**
     * @param  array<string, mixed>  $agent
     */
    private function gemini(array $agent, string $system, string $user): string
    {
        $url = $this->url($agent['base_url'], '/v1beta/models/'.$agent['model'].':generateContent')
            .'?key='.urlencode((string) $agent['api_key']);

        $response = $this->request($agent)->post($url, [
            'systemInstruction' => ['parts' => [['text' => $system]]],
            'contents' => [
                ['role' => 'user', 'parts' => [['text' => $user]]],
            ],
            'generationConfig' => [
                'temperature' => (float) ($agent['temperature'] ?? 0.9),
                'responseMimeType' => 'application/json',
            ],
        ])->throw();

        return $this->read($agent, $response->json(), 'candidates.0.content.parts.0.text');
    }

    /**
     * @param  array<string, mixed>  $agent
     */
    private function request(array $agent): PendingRequest
    {
        $timeout = (int) ($agent['timeout'] ?? config('ai-listings.timeout', 180));

        return Http::acceptJson()
            ->asJson()
            ->timeout($timeout)
            ->withHeaders((array) ($agent['headers'] ?? []))
            ->withUserAgent('TrustLens-ListingBot/1.0');
    }

    /**
     * @param  array<string, mixed>  $agent
     * @param  array<mixed>|null  $json
     */
    private function read(array $agent, ?array $json, string $path): string
    {
        $this->assertNoProviderError($json ?? [], (string) ($agent['name'] ?? 'unnamed'));

        $text = data_get($json, $path);

        if (! is_string($text) || trim($text) === '') {
            throw new RuntimeException(
                'Agent ['.($agent['name'] ?? 'unnamed').'] returned an empty or unusable response.'
            );
        }

        return $text;
    }

    private function url(string $base, string $suffix): string
    {
        return rtrim($base, '/').$suffix;
    }

    /**
     * Guard against a Model Context Protocol style 200 response that still
     * carries an error payload, which some gateways do.
     *
     * @param  array<string, mixed>  $json
     */
    public function assertNoProviderError(array $json, string $agentName): void
    {
        $error = data_get($json, 'error.message') ?? data_get($json, 'error');

        if (is_string($error) && $error !== '') {
            throw new RuntimeException('Agent ['.$agentName.'] reported an error: '.Str::limit($error, 200));
        }
    }

    /**
     * Turns any transport failure into a short line the command can print.
     */
    public static function describe(Throwable $error): string
    {
        return Str::limit(preg_replace('/\s+/', ' ', $error->getMessage()) ?? 'unknown error', 220);
    }
}
