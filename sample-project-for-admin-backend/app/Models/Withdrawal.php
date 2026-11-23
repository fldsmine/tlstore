<?php

namespace App\Models;

use App\Enums\WithdrawalStatus;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class Withdrawal extends Model
{
    use HasFactory, HasUuids;

    protected function casts(): array
    {
        return [
            'status' => WithdrawalStatus::class,
            'amount' => 'decimal:6',
            'fee' => 'decimal:6',
            'amount_paid' => 'decimal:6',
            'local_amount' => 'decimal:2',
            'local_amount_paid' => 'decimal:2',
            'meta' => 'array',
            'processed_at' => 'datetime',
            'exported_at' => 'datetime',
        ];
    }

    public function exchangeRate(): float
    {
        return (float) ($this->meta['exchange_rate'] ?? 0);
    }

    public function toLocal(float $usd): float
    {
        $rate = $this->exchangeRate();

        return round($rate > 0 ? $usd * $rate : $usd, 2);
    }

    public function getLocalSymbolAttribute(): string
    {
        return $this->meta['local_symbol'] ?? ($this->local_currency ?: '$');
    }

    public static function generateReference(): string
    {
        do {
            $reference = 'WD-'.strtoupper(Str::random(10));
        } while (self::where('reference', $reference)->exists());

        return $reference;
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function transaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class);
    }

    public function getWalletLabelAttribute(): string
    {
        return User::WALLET_LABELS[$this->wallet] ?? 'Wallet';
    }

    public function getBankNameAttribute(): ?string
    {
        return $this->meta['bank_name'] ?? null;
    }

    public function getAccountNumberAttribute(): ?string
    {
        return $this->meta['account_number'] ?? null;
    }

    public function getAccountNameAttribute(): ?string
    {
        return $this->meta['account_name'] ?? null;
    }
}
