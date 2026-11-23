<?php

namespace App\Models;

use App\Enums\BankAccountStatus;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BankAccount extends Model
{
    use HasFactory, HasUuids;

    protected function casts(): array
    {
        return [
            'status' => BankAccountStatus::class,
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function bank(): BelongsTo
    {
        return $this->belongsTo(Bank::class);
    }

    public function getMaskedNumberAttribute(): string
    {
        $number = (string) $this->account_number;

        return strlen($number) > 6
            ? substr($number, 0, 3).'••••'.substr($number, -3)
            : $number;
    }
}
