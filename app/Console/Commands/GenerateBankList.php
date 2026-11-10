<?php

namespace App\Console\Commands;

use App\Models\Bank;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class GenerateBankList extends Command
{
    protected $signature = 'banks:generate-list';

    protected $description = 'Generate bank-list.json from the banks table';

    public function handle(): int
    {
        $banks = Bank::query()
            ->where('is_active', 1)
            ->select([
                'id',
                'name',
                'code',
                'paystack_code',
                'flutterwave_code',
                'institution_code',
                'tag',
                'type',
                'country',
                'is_active',
            ])
            ->orderBy('name')
            ->get();

        $data = $banks->toArray();

        $filePath = public_path('bank-list.json');

        File::put(
            $filePath,
            json_encode(
                $data,
                JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
            )
        );

        $this->info("Bank list generated successfully!");
        $this->info("Total banks: {$banks->count()}");
        $this->info("File location: {$filePath}");

        return self::SUCCESS;
    }
}
