<?php

namespace Database\Seeders;

use App\Models\Bank;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use RuntimeException;

class BankSeeder extends Seeder
{
    public function run(): void
    {
        $filePath = database_path('data/bank-list.json');

        if (!File::exists($filePath)) {
            throw new RuntimeException(
                'Bank list file not found: ' . $filePath
            );
        }

        $banks = json_decode(
            File::get($filePath),
            true,
            512,
            JSON_THROW_ON_ERROR
        );

        foreach ($banks as $bank) {
            Bank::updateOrCreate(
                ['id' => $bank['id'] ?? (string) Str::uuid()],
                [
                    'name'             => $bank['name'],
                    'code'             => $bank['code'],
                    'paystack_code'    => $bank['paystack_code'] ?? null,
                    'flutterwave_code' => $bank['flutterwave_code'] ?? null,
                    'institution_code' => $bank['institution_code'] ?? null,
                    'tag'              => $bank['tag'] ?? null,
                    'type'             => $bank['type'] ?? null,
                    'country'          => $bank['country'] ?? 'NG',
                    'is_active'        => $bank['is_active'] ?? 1,
                ]
            );
        }

    }
}
