<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class WithdrawalOptionSeeder extends Seeder
{
    public function run(): void
    {
        $path = database_path('data/withdrawal-options.json');
        $contents = file_get_contents($path);

        if ($contents === false) {
            throw new RuntimeException("Unable to read withdrawal options at {$path}");
        }

        $options = json_decode($contents, true, 512, JSON_THROW_ON_ERROR);
        $rows = array_map(static function (array $option): array {
            return [
                'id' => $option['id'],
                'option_id' => $option['option_id'],
                'name' => $option['name'],
                'icon' => $option['icon'],
                'description' => $option['description'],
                'fields' => json_encode($option['fields'], JSON_THROW_ON_ERROR),
                'tax' => $option['tax'],
                'min_withdrawal' => $option['min_withdrawal'],
                'is_available' => $option['is_available'],
                'order' => $option['order'],
                'created_at' => $option['created_at'],
                'updated_at' => $option['updated_at'],
            ];
        }, $options);

        DB::table('withdrawal_options')->upsert(
            $rows,
            ['option_id'],
            [
                'name',
                'icon',
                'description',
                'fields',
                'tax',
                'min_withdrawal',
                'is_available',
                'order',
                'updated_at',
            ]
        );
    }
}
