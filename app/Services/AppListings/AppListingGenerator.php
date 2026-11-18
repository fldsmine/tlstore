<?php

declare(strict_types=1);

namespace App\Services\AppListings;

use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

/**
 * Turns one AI completion into validated, artwork-backed database attributes.
 *
 * Anything the model gets wrong — a currency that does not match the country,
 * a store link on the wrong domain, fewer than two screenshots, a duplicate
 * app — raises, which lets the pool move on to the next agent instead of
 * writing something half-true into the file.
 */
class AppListingGenerator
{
    public function __construct(
        private readonly AgentClient $client,
        private readonly ListingArtwork $artwork,
    ) {
    }

    /**
     * @param  array<string, mixed>  $agent
     * @param  array<int, array<string, mixed>>  $existing
     * @return array<int, array<string, mixed>>
     */
    public function generate(
        array $agent,
        int $count,
        array $existing,
        bool $downloadImages = true,
        bool $requireImages = true,
    ): array {
        $raw = $this->client->complete($agent, $this->systemPrompt(), $this->userPrompt($count, $existing));
        $items = $this->decode($raw);

        if ($items === []) {
            throw new RuntimeException('The agent did not return any listings.');
        }

        $taken = collect($existing)
            ->map(fn ($app): string => mb_strtolower(trim((string) (is_array($app) ? ($app['name'] ?? '') : ''))))
            ->filter()
            ->all();

        $accepted = [];
        $problems = [];

        foreach ($items as $item) {
            if (count($accepted) >= $count) {
                break;
            }

            if (! is_array($item)) {
                $problems[] = 'listing was not an object';

                continue;
            }

            try {
                $this->assertValid($item, $taken);
                $accepted[] = $this->build($item, $downloadImages, $requireImages);
                $taken[] = mb_strtolower(trim((string) $item['name']));
            } catch (Throwable $error) {
                $problems[] = AgentClient::describe($error);
            }
        }

        if (count($accepted) < $count) {
            throw new RuntimeException(
                'The agent only produced '.count($accepted).' of '.$count.' valid listings'
                .($problems === [] ? '.' : ': '.implode(' | ', array_slice($problems, 0, 4)))
            );
        }

        return $accepted;
    }

    /**
     * @param  array<string, mixed>  $item
     * @param  array<int, string>  $taken
     */
    private function assertValid(array $item, array $taken): void
    {
        $name = trim((string) ($item['name'] ?? ''));

        if (mb_strlen($name) < 2 || mb_strlen($name) > 120) {
            throw new RuntimeException('app name is missing or too long');
        }

        if (in_array(mb_strtolower($name), $taken, true)) {
            throw new RuntimeException('duplicate listing ['.$name.']');
        }

        $store = (string) ($item['store'] ?? '');
        $stores = (array) config('ai-listings.stores', []);

        if (! isset($stores[$store])) {
            throw new RuntimeException('unknown store ['.$store.']');
        }

        $country = strtoupper(trim((string) ($item['country'] ?? '')));
        $currency = strtoupper(trim((string) ($item['currency'] ?? '')));
        $countries = (array) config('ai-listings.countries', []);

        if (! isset($countries[$country])) {
            throw new RuntimeException('unknown country ['.$country.']');
        }

        if ($countries[$country] !== $currency) {
            throw new RuntimeException($country.' must be billed in '.$countries[$country].', got '.$currency);
        }

        $link = trim((string) ($item['store_link'] ?? ''));

        if (! preg_match('#^https://#i', $link)) {
            throw new RuntimeException('store link must be https');
        }

        $host = (string) parse_url($link, PHP_URL_HOST);

        if (! str_contains($host, (string) $stores[$store])) {
            throw new RuntimeException('store link host ['.$host.'] does not belong to '.$store);
        }

        $icon = trim((string) ($item['icon_url'] ?? ''));

        if (! preg_match('#^https?://#i', $icon)) {
            throw new RuntimeException('icon_url must be an absolute http(s) URL');
        }

        $shots = array_values(array_filter(
            (array) ($item['screenshot_urls'] ?? []),
            fn ($url): bool => is_string($url) && preg_match('#^https?://#i', $url) === 1
        ));

        $min = (int) config('ai-listings.image.min_screenshots', 2);
        $max = (int) config('ai-listings.image.max_screenshots', 4);

        if (count($shots) < $min || count($shots) > $max) {
            throw new RuntimeException('needs '.$min.' to '.$max.' screenshots, got '.count($shots));
        }

        foreach (['developer', 'category', 'description'] as $field) {
            if (trim((string) ($item[$field] ?? '')) === '') {
                throw new RuntimeException($field.' is missing');
            }
        }

        if (mb_strlen(trim((string) $item['description'])) > 300) {
            throw new RuntimeException('description is longer than 300 characters');
        }

        if (! preg_match('/^\d+(\.\d+)?\s?[KMB]\+$/i', trim((string) ($item['install_count_label'] ?? '')))) {
            throw new RuntimeException('install_count_label must look like 1M+ or 500K+');
        }

        if (! preg_match('/^#[0-9a-f]{6}$/i', trim((string) ($item['brand_color'] ?? '')))) {
            throw new RuntimeException('brand_color must be a #rrggbb hex value');
        }

        $rating = (float) ($item['rating'] ?? 0);

        if ($rating < 1 || $rating > 5) {
            throw new RuntimeException('rating must be between 1 and 5');
        }

        $reward = (float) ($item['review_reward'] ?? 0);

        if ($reward <= 0) {
            throw new RuntimeException('review_reward must be greater than zero');
        }

        $maximum = (int) ($item['maximum_review'] ?? 0);

        if ($maximum < 10 || $maximum > 2000) {
            throw new RuntimeException('maximum_review must be between 10 and 2000');
        }

        $reviews = (int) ($item['total_reviews'] ?? 0);

        if ($reviews < 10 || $reviews > 100000) {
            throw new RuntimeException('total_reviews must be between 10 and 100000');
        }
    }

    /**
     * @param  array<string, mixed>  $item
     * @return array<string, mixed>
     */
    private function build(array $item, bool $downloadImages, bool $requireImages): array
    {
        $name = trim((string) $item['name']);
        $slug = Str::slug($name);

        if ($slug === '') {
            $slug = 'app-'.Str::lower(Str::random(8));
        }
        $reward = round((float) $item['review_reward'], 2);
        $maximum = (int) $item['maximum_review'];

        $iconUrl = trim((string) $item['icon_url']);
        $shotUrls = array_values(array_filter(
            (array) $item['screenshot_urls'],
            fn ($url): bool => is_string($url) && preg_match('#^https?://#i', $url) === 1
        ));

        $disk = 'remote';
        $icon = $iconUrl;
        $screenshots = $shotUrls;

        if (! $downloadImages) {
            if (mb_strlen($iconUrl) > 255 || collect($shotUrls)->contains(fn (string $url): bool => mb_strlen($url) > 255)) {
                throw new RuntimeException('Remote artwork URLs must fit the app and screenshot storage-path columns (255 characters).');
            }
        }

        if ($downloadImages) {
            try {
                $artwork = $this->artwork->store($slug, $iconUrl, $shotUrls);
                $disk = $this->artwork->diskName();
                $icon = $artwork['icon'];
                $screenshots = $artwork['screenshots'];
            } catch (Throwable $error) {
                if ($requireImages) {
                    throw $error;
                }

                $disk = 'remote';
                $icon = $iconUrl;
                $screenshots = $shotUrls;
            }
        }

        $entries = [];

        foreach (array_values($screenshots) as $index => $path) {
            $entries[] = [
                'position' => $index + 1,
                'source_type' => 'image',
                'illustration_index' => $index,
                'alt_text' => $name.' screenshot '.($index + 1),
                'storage_disk' => $disk,
                'storage_path' => $path,
            ];
        }

        return [
            'name' => $name,
            'short_code' => $this->shortCode($name),
            'store' => (string) $item['store'],
            'brand_color' => strtoupper(trim((string) $item['brand_color'])),
            'developer' => trim((string) $item['developer']),
            'category' => trim((string) $item['category']),
            'install_count_label' => trim((string) $item['install_count_label']),
            'description' => trim((string) $item['description']),
            'store_link' => trim((string) $item['store_link']),
            'paid_amount' => number_format($reward * $maximum, 2, '.', ''),
            'location' => strtoupper(trim((string) $item['country'])),
            'currency' => strtoupper(trim((string) $item['currency'])),
            'rating' => round((float) $item['rating'], 1),
            'total_reviews' => (int) $item['total_reviews'],
            'review_reward' => number_format($reward, 2, '.', ''),
            'app_icon' => $icon,
            'app_icon_storage_disk' => $disk,
            'maximum_review' => $maximum,
            'screenshots' => $entries,
        ];
    }

    private function shortCode(string $name): string
    {
        $words = preg_split('/\s+/', trim($name)) ?: [];
        $letters = '';

        foreach ($words as $word) {
            $clean = preg_replace('/[^A-Za-z0-9]/', '', $word) ?? '';

            if ($clean !== '') {
                $letters .= strtoupper($clean[0]);
            }

            if (strlen($letters) === 3) {
                break;
            }
        }

        return $letters !== '' ? $letters : strtoupper(substr($name, 0, 2));
    }

    /**
     * @return array<int, mixed>
     */
    private function decode(string $raw): array
    {
        $text = trim($raw);

        if (preg_match('/```(?:json)?\s*(.+?)```/is', $text, $matches) === 1) {
            $text = trim($matches[1]);
        }

        $data = json_decode($text, true);

        if (! is_array($data)) {
            $data = $this->decodeLoose($text);
        }

        if (! is_array($data)) {
            throw new RuntimeException('The agent did not return parseable JSON.');
        }

        if (array_key_exists('apps', $data) && is_array($data['apps'])) {
            return array_values($data['apps']);
        }

        if (array_key_exists('listings', $data) && is_array($data['listings'])) {
            return array_values($data['listings']);
        }

        if (array_key_exists('data', $data) && is_array($data['data'])) {
            return array_values($data['data']);
        }

        if (array_key_exists('name', $data)) {
            return [$data];
        }

        return array_values($data);
    }

    /**
     * @return array<mixed>|null
     */
    private function decodeLoose(string $text): ?array
    {
        foreach ([['{', '}'], ['[', ']']] as [$open, $close]) {
            $start = strpos($text, $open);
            $end = strrpos($text, $close);

            if ($start === false || $end === false || $end <= $start) {
                continue;
            }

            $candidate = substr($text, $start, $end - $start + 1);
            $decoded = json_decode($candidate, true);

            if (is_array($decoded)) {
                return $decoded;
            }
        }

        return null;
    }

    private function systemPrompt(): string
    {
        return <<<'PROMPT'
You are the listing curator for TrustLens, a platform that pays people to review real mobile apps.
You output only strict JSON. You never invent apps, developers or store links: every app you return must
exist right now on the store you name, with the developer, category and store URL that store shows.
PROMPT;
    }

    /**
     * @param  array<int, array<string, mixed>>  $existing
     */
    private function userPrompt(int $count, array $existing): string
    {
        $already = collect($existing)
            ->map(fn ($app): string => trim((string) (is_array($app) ? ($app['name'] ?? '') : '')))
            ->filter()
            ->take(120)
            ->implode('; ');

        $template = <<<'PROMPT'
Return exactly {{COUNT}} app listings as strict JSON, in this exact shape:

{
  "apps": [
    {
      "name": "Exact app name as shown in the store",
      "store": "Google Play | Apple App Store | Samsung Galaxy Store",
      "store_link": "canonical https link to the app page on that store",
      "developer": "publisher shown on the store page",
      "category": "store category, e.g. Shopping",
      "install_count_label": "1M+ (digit plus K, M or B and a plus sign)",
      "description": "one or two sentences in your own words, max 160 characters",
      "brand_color": "#RRGGBB taken from the app icon",
      "country": "NG | US | UK | GH | CA",
      "currency": "NGN | USD | GBP | GHS | CAD",
      "rating": 4.4,
      "total_reviews": 380,
      "review_reward": "what we pay one reviewer, in the country currency",
      "maximum_review": 400,
      "icon_url": "direct https URL to the app icon image",
      "screenshot_urls": ["direct https URL", "direct https URL", "direct https URL"]
    }
  ]
}

Rules, all of them mandatory:
1. Real apps only, currently listed on the store you name, with real developers. No invented or renamed products.
2. Use at least two different stores across the three listings, and at least two different countries.
3. currency MUST match country: NG=NGN, US=USD, UK=GBP, GH=GHS, CA=CAD.
4. store_link domain MUST match the store: play.google.com, apps.apple.com or galaxystore.samsung.com.
5. Give 2 to 4 screenshot URLs and one icon URL, all absolute https image URLs that resolve to that app
   (store CDN images are ideal). Never invent image URLs.
6. rating is one decimal between 1 and 5; total_reviews between 10 and 100000; maximum_review between 10 and 2000.
7. review_reward must be sensible for the currency (roughly 0.25-2 USD equivalent).
8. The apps must not repeat anything in this list: {{EXISTING}}
9. Reply with JSON only. No markdown fences, no commentary.
PROMPT;

        return str_replace(
            ['{{COUNT}}', '{{EXISTING}}'],
            [(string) $count, $already === '' ? '(nothing yet)' : $already],
            $template
        );
    }
}
