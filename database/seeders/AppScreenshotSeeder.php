<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class AppScreenshotSeeder extends Seeder
{
    public function run(): void
    {
        $path = database_path('data/app-listings.json');
        $contents = file_get_contents($path);

        if ($contents === false) {
            throw new RuntimeException("Unable to read app listings at {$path}");
        }

        $model = json_decode($contents, true, 512, JSON_THROW_ON_ERROR);
        $now = now();
        $rows = [];

        foreach ($model['apps'] as $listing) {
            $appId = DB::table('apps')
                ->where('prototype_id', $listing['prototype_id'])
                ->value('id');

            if ($appId === null) {
                throw new RuntimeException(
                    "App with prototype ID {$listing['prototype_id']} must be seeded before its screenshots."
                );
            }

            foreach ($listing['screenshots'] ?? [] as $screenshot) {
                $rows[] = [
                    'app_id' => $appId,
                    'position' => $screenshot['position'],
                    'source_type' => $screenshot['source_type'],
                    'storage_disk' => $screenshot['storage_disk'] ?? null,
                    'storage_path' => $screenshot['storage_path'] ?? null,
                    'illustration_index' => $screenshot['illustration_index'] ?? null,
                    'alt_text' => $screenshot['alt_text'],
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
        }

        if ($rows !== []) {
            DB::table('app_screenshots')->upsert(
                $rows,
                ['app_id', 'position'],
                [
                    'source_type',
                    'storage_disk',
                    'storage_path',
                    'illustration_index',
                    'alt_text',
                    'updated_at',
                ]
            );
        }
    }
}
