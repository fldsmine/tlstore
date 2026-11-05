<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class ExchangeRateSnapshotSeeder extends Seeder
{
    public function run(): void
    {
        $path = database_path('data/demo-exchange-rates.json');
        $contents = file_get_contents($path);

        if ($contents === false) {
            throw new RuntimeException("Unable to read demo exchange rates at {$path}");
        }

        $snapshot = json_decode($contents, true, 512, JSON_THROW_ON_ERROR);
        $fetchedAt = $snapshot['fetched_at'];
        $now = now();
        $rows = [];

        foreach ($snapshot['rates'] as $currency => $rate) {
            $rows[] = [
                'base_currency' => $snapshot['base_currency'],
                'quote_currency' => $currency,
                'rate' => $rate,
                'source' => $snapshot['source'],
                'is_fallback' => $snapshot['is_fallback'],
                'fetched_at' => $fetchedAt,
                'source_published_at' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        DB::table('exchange_rate_snapshots')->upsert(
            $rows,
            ['base_currency', 'quote_currency', 'fetched_at'],
            ['rate', 'source', 'is_fallback', 'updated_at']
        );
    }
}
