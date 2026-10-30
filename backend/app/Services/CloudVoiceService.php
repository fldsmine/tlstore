<?php

namespace App\Services;

use App\Models\CloudNote;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Laravel\Ai\Audio;
use Laravel\Ai\Enums\Lab;

class CloudVoiceService
{
    private const CACHE_KEY = 'cloud.voices.elevenlabs';

    public function isConfigured(): bool
    {
        return filled(config('services.elevenlabs.key'));
    }

    /**
     * @return array<int, array{id: string, label: string, accent: string, name: string}>
     */
    public function voices(): array
    {
        if (! $this->isConfigured()) {
            return [];
        }

        return Cache::remember(self::CACHE_KEY, 3600, function (): array {
            $response = Http::withHeaders(['xi-api-key' => (string) config('services.elevenlabs.key')])
                ->timeout(15)
                ->get($this->base().'/v1/voices');

            if ($response->failed()) {
                report(new \RuntimeException('ElevenLabs voices fetch failed: '.$response->status()));

                return [];
            }

            return collect($response->json('voices', []))
                ->map(function (array $voice): array {
                    $accent = ucfirst((string) (data_get($voice, 'labels.accent') ?: 'Other'));

                    return [
                        'id' => (string) $voice['voice_id'],
                        'name' => (string) $voice['name'],
                        'accent' => $accent,
                        'label' => $voice['name'].' · '.$accent,
                    ];
                })
                ->sortBy(fn (array $voice): array => [self::accentRank($voice['accent']), $voice['name']])
                ->values()
                ->all();
        });
    }

    /**
     * @return array<int, string>
     */
    public function voiceIds(): array
    {
        return array_column($this->voices(), 'id');
    }

    /**
     * @return array<int, array{id: string, label: string, accent: string, name: string}>
     */
    public function refreshVoices(): array
    {
        Cache::forget(self::CACHE_KEY);

        return $this->voices();
    }

    public function labelFor(string $voiceId): string
    {
        foreach ($this->voices() as $voice) {
            if ($voice['id'] === $voiceId) {
                return $voice['label'];
            }
        }

        return $voiceId;
    }

    public function synthesize(string $text, string $voiceId): ?string
    {
        if (! $this->isConfigured() || blank(trim($text)) || blank($voiceId)) {
            return null;
        }

        try {
            return Audio::of($text)
                ->voice($voiceId)
                ->generate(Lab::ElevenLabs, config('services.elevenlabs.model'))
                ->content() ?: null;
        } catch (\Throwable $e) {
            report($e);

            return null;
        }
    }

    public function generateForNote(CloudNote $note): bool
    {
        $mp3 = $this->synthesize((string) $note->message, (string) $note->voice);

        if ($mp3 === null) {
            return false;
        }

        $previous = $note->audio_path;
        $path = 'cloud-voices/'.Str::uuid7()->toString().'.mp3';

        $this->disk()->put($path, $mp3);
        $note->forceFill(['audio_path' => $path])->save();

        if ($previous) {
            $this->disk()->delete($previous);
        }

        return true;
    }

    public function previewDataUri(string $text, string $voiceId): ?string
    {
        $mp3 = $this->synthesize($text, $voiceId);

        return $mp3 ? 'data:audio/mpeg;base64,'.base64_encode($mp3) : null;
    }

    public function deleteAudio(?string $path): void
    {
        if ($path) {
            $this->disk()->delete($path);
        }
    }

    private static function accentRank(string $accent): int
    {
        $a = strtolower($accent);

        if (str_contains($a, 'niger')) {
            return 0;
        }

        if (str_contains($a, 'africa') || str_contains($a, 'ghan') || str_contains($a, 'kenya')) {
            return 1;
        }

        return 2;
    }

    private function base(): string
    {
        return rtrim((string) config('services.elevenlabs.base_url'), '/');
    }

    private function disk(): Filesystem
    {
        return Storage::disk((string) config('filesystems.default'));
    }
}
