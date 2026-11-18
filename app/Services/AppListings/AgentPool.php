<?php

declare(strict_types=1);

namespace App\Services\AppListings;

use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

/**
 * Holds the configured AI agents and decides the order they are tried in.
 *
 * Agents are picked in weighted random order, so a run spreads its work across
 * whatever keys you have configured. When one throws — a dead key, a timeout,
 * malformed JSON, a listing that fails validation — the pool quietly moves on
 * to the next agent and keeps the error for the summary.
 */
class AgentPool
{
    public function __construct(private readonly AgentClient $client)
    {
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function candidates(?string $only = null): array
    {
        $agents = [];

        foreach ((array) config('ai-listings.agents', []) as $agent) {
            if (! is_array($agent)) {
                continue;
            }

            if (($agent['enabled'] ?? true) === false) {
                continue;
            }

            if (blank($agent['api_key'] ?? null) || blank($agent['model'] ?? null)) {
                continue;
            }

            if ($only !== null && $only !== '' && strcasecmp((string) ($agent['name'] ?? ''), $only) !== 0) {
                continue;
            }

            $agents[] = $agent;
        }

        return $this->weightedShuffle($agents);
    }

    /**
     * Run the work against the first agent that succeeds.
     *
     * @param  array<int, array<string, mixed>>  $agents
     * @param  callable(array<string, mixed>): array<int, array<string, mixed>>  $work
     * @return array{agent: array<string, mixed>, result: array<int, array<string, mixed>>, attempts: array<int, array{agent: string, error: string}>, used: array<int, string>}
     */
    public function attempt(array $agents, callable $work): array
    {
        $attempts = [];
        $used = [];

        foreach ($agents as $agent) {
            $name = (string) ($agent['name'] ?? 'unnamed');
            $model = (string) ($agent['model'] ?? 'unknown-model');
            $used[] = $name;

            try {
                $result = $work($agent);

                return [
                    'agent' => $agent,
                    'result' => $result,
                    'attempts' => $attempts,
                    'used' => $used,
                ];
            } catch (Throwable $error) {
                $message = AgentClient::describe($error);
                $attempts[] = ['agent' => $name, 'error' => $message];

                Log::warning('AI listing agent failed; trying the next one.', [
                    'agent' => $name,
                    'model' => $model,
                    'driver' => $agent['driver'] ?? null,
                    'error' => $message,
                ]);
            }
        }

        $reasons = collect($attempts)
            ->map(fn (array $attempt): string => $attempt['agent'].': '.$attempt['error'])
            ->implode(' | ');

        throw new RuntimeException(
            'No AI agent could produce valid listings. '.($attempts === [] ? 'Nothing was configured.' : $reasons)
        );
    }

    /**
     * @param  array<int, array<string, mixed>>  $agents
     * @return array<int, array<string, mixed>>
     */
    private function weightedShuffle(array $agents): array
    {
        $bag = [];

        foreach ($agents as $agent) {
            $weight = max(1, (int) ($agent['weight'] ?? 1));

            for ($i = 0; $i < $weight; $i++) {
                $bag[] = $agent;
            }
        }

        shuffle($bag);

        $seen = [];
        $ordered = [];

        foreach ($bag as $agent) {
            $name = (string) ($agent['name'] ?? '');

            if (isset($seen[$name])) {
                continue;
            }

            $seen[$name] = true;
            $ordered[] = $agent;
        }

        return $ordered;
    }
}
