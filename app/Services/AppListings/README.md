# AI-generated app listings

`php artisan listings:generate` asks an AI agent for new app listings, validates them, downloads
their artwork, and creates `apps`, `app_screenshots`, and active `app_review_campaigns` records.
This makes generated listings visible and claimable through the existing marketplace endpoints.

Instead of trusting one provider, it treats every configured provider as an *agent*, picks
them in weighted random order, and falls through to the next agent whenever one errors, times
out, returns unparseable JSON, or hands back a listing that fails validation. Nothing is
written until three listings pass.

## Files

| Path | What it does |
| --- | --- |
| `app/Console/Commands/GenerateAppListingsCommand.php` | Artisan command: orchestration, fallback report, table output, persistence. |
| `app/Services/AppListings/AgentPool.php` | Weighted random ordering of agents and the try-next-on-failure loop. |
| `app/Services/AppListings/AgentClient.php` | One HTTP client, three drivers: `openai` (also covers OpenAI-compatible gateways), `anthropic`, `gemini`. |
| `app/Services/AppListings/AppListingGenerator.php` | Prompt, JSON extraction, validation, entry building. |
| `app/Services/AppListings/ListingArtwork.php` | Downloads the icon and 2-4 screenshots to the configured public disk. |
| `app/Services/AppListings/ListingRepository.php` | Creates app, screenshot, and review-campaign database records in one transaction. |
| `config/ai-listings.php` | Agents, weights, countries/currencies, stores, storage disk, limits. |

## Setup

1. Copy the files into your app (`app/...`, `config/ai-listings.php`). Laravel 11 discovers
   commands in `app/Console/Commands` automatically; on Laravel 10 make sure your
   `app/Console/Kernel.php` still calls `$this->load(__DIR__.'/Commands')`.

2. Set at least one API key. Agents without a key are simply skipped:

```dotenv
OPENAI_API_KEY=
ANTHROPIC_API_KEY=
GEMINI_API_KEY=
GROQ_API_KEY=
OPENROUTER_API_KEY=
DEEPSEEK_API_KEY=
MISTRAL_API_KEY=
```

Optional overrides: `AI_LISTINGS_DISK`, `AI_LISTINGS_TIMEOUT`,
`AI_LISTINGS_IMAGE_TIMEOUT`, `AI_LISTINGS_REQUIRE_IMAGES`, plus per-provider
`*_BASE_URL` / `*_MODEL` so you can point an agent at a newer model without touching code.

3. The project `public` disk is the default and already serves stored app artwork from `/storage`:

```php
// config/filesystems.php (already configured in this project)
'public' => [...],
```

Set `AI_LISTINGS_DISK` only if you intentionally want to use another configured disk.

## Running it

```bash
# generate three listings with whichever agent wins the draw
php artisan listings:generate

# see what an agent produces without creating database records
php artisan listings:generate --dry-run

# pin one agent, or keep remote image URLs instead of downloading them
php artisan listings:generate --agent=anthropic-sonnet
php artisan listings:generate --no-images

```

The scheduler runs this command daily at 00:30 in the application timezone. It requires at least
one configured AI key and a published review questionnaire version with questions. Use
`php artisan schedule:run` from the server scheduler as usual.

Output looks like this:

```
Generating 3 listing(s); 7 agent(s) available, tried in weighted random order.
  → asking gemini-flash (gemini-2.0-flash)…
  skipped gemini-flash: The agent only produced 0 of 3 valid listings: needs 2 to 4 screenshots, got 1
  → asking openai-mini (gpt-4o-mini)…
Generated 3 listing(s) with openai-mini (gpt-4o-mini).
+----+---------------------+---------------------+---------+----------+--------+------+-------+
| ID | Name                | Store               | Country | Currency | Reward | Max  | Shots |
...
Appended to database/data/app-listings.json (now 13 listing(s) in total).
```

## What is validated

A listing is rejected (and the next agent tried) unless all of this holds:

- `store` is one of Google Play / Apple App Store / Samsung Galaxy Store, and `store_link` is
  https on that store's domain;
- `country` is NG, US, UK, GH or CA and `currency` is exactly the country's currency;
- `icon_url` plus 2-4 absolute https `screenshot_urls` that actually download as images;
- `install_count_label` shaped like `1M+`, `brand_color` a `#rrggbb`, rating 1-5,
  `total_reviews` 10-100000, `maximum_review` 10-2000, positive `review_reward`;
- the app name does not already exist in the database or twice in the same batch.

`paid_amount` is never taken from the model: it is computed as `review_reward × maximum_review`.

## Adding another provider

Append an entry to `config/ai-listings.php`. Anything that speaks the OpenAI chat-completions
API only needs three lines:

```php
[
    'name' => 'xai-grok',
    'driver' => 'openai',
    'base_url' => env('XAI_BASE_URL', 'https://api.x.ai/v1'),
    'model' => env('XAI_MODEL', 'grok-2-latest'),
    'api_key' => env('XAI_API_KEY'),
    'weight' => 1,
    'json_mode' => true,
],
```

`weight` controls how often it is picked first; every enabled agent is still a fallback for the
others.

## Notes and limits

- Artwork downloads happen before validation is complete, so a rejected agent can leave a few
  files behind under `storage/app/public/app-icons` and `app-screenshots`. Prune them with a
  scheduled command if that matters to you.
- Models are not reliable at image URLs. Expect some runs where every agent fails the artwork
  rule; that is the validator doing its job. Lowering `require_images` to `false` keeps the
  remote URLs instead of the run failing.
- The command never edits existing entries. New campaigns are activated with a reward pool of
  `review_reward × maximum_review`, tied to the latest published questionnaire version.
