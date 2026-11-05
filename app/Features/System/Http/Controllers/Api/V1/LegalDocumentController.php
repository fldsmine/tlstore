<?php

namespace App\Features\System\Http\Controllers\Api\V1;

use App\Models\LegalDocument;
use Illuminate\Http\JsonResponse;

final class LegalDocumentController
{
    public function show(string $slug): JsonResponse
    {
        abort_unless(in_array($slug, ['privacy', 'terms'], true), 404);

        $document = LegalDocument::query()
            ->where('slug', $slug)
            ->where('is_published', true)
            ->firstOrFail();

        return response()->json([
            'data' => [
                'slug' => $document->slug,
                'title' => $document->title,
                'eyebrow' => $document->eyebrow,
                'subtitle' => $document->subtitle,
                'hero_text' => $document->hero_text,
                'sections' => $document->sections ?? [],
                'version' => $document->version,
                'last_updated' => $document->last_updated?->toDateString(),
            ],
        ]);
    }
}
