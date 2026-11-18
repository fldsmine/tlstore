<?php

declare(strict_types=1);

namespace App\Services\AppListings;

use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;
use RuntimeException;

/**
 * Downloads the icon and screenshots an agent pointed at and files them on the
 * configured public disk, so the app and screenshot tables store a disk plus
 * a disk-relative path, matching the project's existing media contract.
 */
class ListingArtwork
{
    /**
     * @param  array<int, string>  $screenshotUrls
     * @return array{icon: string, screenshots: array<int, string>}
     */
    public function store(string $slug, string $iconUrl, array $screenshotUrls): array
    {
        $disk = $this->disk();

        $icon = $this->fetch($iconUrl);
        $iconPath = 'app-icons/'.$slug.'.'.$icon['extension'];
        $this->write($disk, $iconPath, $icon['body']);

        $screenshots = [];

        foreach (array_values($screenshotUrls) as $index => $url) {
            $shot = $this->fetch($url);
            $path = 'app-screenshots/'.$slug.'/'.($index + 1).'.'.$shot['extension'];
            $this->write($disk, $path, $shot['body']);
            $screenshots[] = $path;
        }

        return ['icon' => $iconPath, 'screenshots' => $screenshots];
    }

    public function diskName(): string
    {
        return (string) config('ai-listings.disk', 'web');
    }

    private function disk(): Filesystem
    {
        try {
            return Storage::disk($this->diskName());
        } catch (InvalidArgumentException $error) {
            throw new RuntimeException(
                'The ['.$this->diskName().'] disk is not configured. Add it to config/filesystems.php '
                .'or set AI_LISTINGS_DISK to a disk you already have. Original error: '.$error->getMessage()
            );
        }
    }

    /**
     * @return array{body: string, extension: string}
     */
    private function fetch(string $url): array
    {
        $limit = (int) config('ai-listings.image.max_bytes', 12 * 1024 * 1024);

        $response = Http::withHeaders(['User-Agent' => 'TrustLens-ListingBot/1.0'])
            ->timeout((int) config('ai-listings.image.timeout', 30))
            ->get($url);

        if ($response->failed()) {
            throw new RuntimeException('Could not download artwork (HTTP '.$response->status().'): '.$url);
        }

        $body = $response->body();

        if ($body === '' || strlen($body) > $limit) {
            throw new RuntimeException('Artwork was empty or too large: '.$url);
        }

        return ['body' => $body, 'extension' => $this->extension((string) $response->header('Content-Type'), $url)];
    }

    private function extension(string $contentType, string $url): string
    {
        $type = strtolower(trim(explode(';', $contentType)[0]));

        $map = [
            'image/png' => 'png',
            'image/jpeg' => 'jpg',
            'image/jpg' => 'jpg',
            'image/webp' => 'webp',
            'image/gif' => 'gif',
        ];

        if (isset($map[$type])) {
            return $map[$type];
        }

        $extension = strtolower((string) pathinfo((string) parse_url($url, PHP_URL_PATH), PATHINFO_EXTENSION));

        if (in_array($extension, ['png', 'jpg', 'jpeg', 'webp', 'gif'], true)) {
            return $extension === 'jpeg' ? 'jpg' : $extension;
        }

        throw new RuntimeException('Artwork was not a usable image ('.$contentType.'): '.$url);
    }

    private function write(Filesystem $disk, string $path, string $body): void
    {
        if (! $disk->put($path, $body)) {
            throw new RuntimeException('Could not write artwork to ['.$this->diskName().']: '.$path);
        }
    }
}
