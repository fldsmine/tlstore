<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Daily generation defaults
    |--------------------------------------------------------------------------
    |
    | Listing generation creates app, screenshot, and review campaign records.
    |
    */

    /*
    |--------------------------------------------------------------------------
    | Generation budget
    |--------------------------------------------------------------------------
    |
    | Three listings per run: enough to fill a review queue without asking an
    | agent for a wall of JSON it will almost certainly truncate.
    |
    */

    'count' => 3,
    'max_count' => 3,

    /*
    |--------------------------------------------------------------------------
    | Artwork
    |--------------------------------------------------------------------------
    |
    | Icons and screenshots are downloaded to a public storage disk and their
    | disk-relative paths are stored in the app listing tables.
    |
    */

    'disk' => env('AI_LISTINGS_DISK', 'public'),

    'require_images' => filter_var(env('AI_LISTINGS_REQUIRE_IMAGES', true), FILTER_VALIDATE_BOOLEAN),

    'image' => [
        'min_screenshots' => 2,
        'max_screenshots' => 4,
        'max_bytes' => 12 * 1024 * 1024,
        'timeout' => (int) env('AI_LISTINGS_IMAGE_TIMEOUT', 30),
    ],

    'timeout' => (int) env('AI_LISTINGS_TIMEOUT', 180),

    /*
    |--------------------------------------------------------------------------
    | Listing vocabulary
    |--------------------------------------------------------------------------
    |
    | Every generated listing is validated against these maps, so an agent can
    | never hand back a currency that does not belong to the country, or a
    | store that this platform does not review.
    |
    */

    'countries' => [
        'NG' => 'NGN',
        'US' => 'USD',
        'UK' => 'GBP',
        'GH' => 'GHS',
        'CA' => 'CAD',
    ],

    'stores' => [
        'Google Play' => 'play.google.com',
        'Apple App Store' => 'apps.apple.com',
        'Samsung Galaxy Store' => 'galaxystore.samsung.com',
    ],

    /*
    |--------------------------------------------------------------------------
    | AI agents
    |--------------------------------------------------------------------------
    |
    | Each entry is one "agent": a provider, a model and a persona weight. The
    | command picks them in weighted random order and falls through to the next
    | one whenever an agent errors, returns invalid JSON or fails validation.
    |
    | driver: openai (also covers any OpenAI-compatible endpoint), anthropic
    | or gemini. Add your own entry to plug in another provider.
    |
    */

    'agents' => [

        [
            'name' => 'openai-mini',
            'driver' => 'openai',
            'base_url' => env('OPENAI_BASE_URL', 'https://api.openai.com/v1'),
            'model' => env('OPENAI_MODEL', 'gpt-4o-mini'),
            'api_key' => env('OPENAI_API_KEY'),
            'temperature' => 0.9,
            'weight' => 3,
            'json_mode' => true,
        ],

        [
            'name' => 'anthropic-sonnet',
            'driver' => 'anthropic',
            'base_url' => env('ANTHROPIC_BASE_URL', 'https://api.anthropic.com'),
            'model' => env('ANTHROPIC_MODEL', 'claude-3-5-sonnet-latest'),
            'api_key' => env('ANTHROPIC_API_KEY'),
            'temperature' => 0.9,
            'weight' => 3,
            'max_tokens' => 4096,
        ],

        [
            'name' => 'gemini-flash',
            'driver' => 'gemini',
            'base_url' => env('GEMINI_BASE_URL', 'https://generativelanguage.googleapis.com'),
            'model' => env('GEMINI_MODEL', 'gemini-2.0-flash'),
            'api_key' => env('GEMINI_API_KEY'),
            'temperature' => 0.9,
            'weight' => 2,
        ],

        [
            'name' => 'groq-llama',
            'driver' => 'openai',
            'base_url' => env('GROQ_BASE_URL', 'https://api.groq.com/openai/v1'),
            'model' => env('GROQ_MODEL', 'llama-3.3-70b-versatile'),
            'api_key' => env('GROQ_API_KEY'),
            'temperature' => 0.8,
            'weight' => 1,
            'json_mode' => true,
        ],

        [
            'name' => 'openrouter',
            'driver' => 'openai',
            'base_url' => env('OPENROUTER_BASE_URL', 'https://openrouter.ai/api/v1'),
            'model' => env('OPENROUTER_MODEL', 'openai/gpt-4o-mini'),
            'api_key' => env('OPENROUTER_API_KEY'),
            'temperature' => 0.9,
            'weight' => 1,
            'json_mode' => true,
            'headers' => [
                'HTTP-Referer' => env('APP_URL', 'https://trustlens.app'),
                'X-Title' => 'TrustLens listings',
            ],
        ],

        [
            'name' => 'deepseek-chat',
            'driver' => 'openai',
            'base_url' => env('DEEPSEEK_BASE_URL', 'https://api.deepseek.com/v1'),
            'model' => env('DEEPSEEK_MODEL', 'deepseek-chat'),
            'api_key' => env('DEEPSEEK_API_KEY'),
            'temperature' => 0.9,
            'weight' => 1,
            'json_mode' => true,
        ],

        [
            'name' => 'mistral-large',
            'driver' => 'openai',
            'base_url' => env('MISTRAL_BASE_URL', 'https://api.mistral.ai/v1'),
            'model' => env('MISTRAL_MODEL', 'mistral-large-latest'),
            'api_key' => env('MISTRAL_API_KEY'),
            'temperature' => 0.9,
            'weight' => 1,
            'json_mode' => true,
        ],

    ],

];
