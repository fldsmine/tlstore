<?php

namespace App\Models;

use App\Application\Money\CurrencyMinorUnits;
use App\Enums\TransactionType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Transaction extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id', 'type', 'direction', 'amount', 'fee', 'wallet', 'status',
        'reference', 'description', 'meta',
    ];

    protected function casts(): array
    {
        return [
            'type' => TransactionType::class,
            'amount' => 'decimal:6',
            'fee' => 'decimal:6',
            'meta' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function paymentIntents(): HasMany
    {
        return $this->hasMany(PaymentIntent::class, 'source_id');
    }

    public function getIsCreditAttribute(): bool
    {
        return $this->direction === 'credit';
    }

    public function getSignedAmountAttribute(): string
    {
        $currency = strtoupper((string) ($this->meta['currency'] ?? config('trustlens.default_currency', 'USD')));
        $precision = CurrencyMinorUnits::precision($currency);

        return ($this->is_credit ? '+' : '-').number_format((float) $this->amount, $precision, '.', '');
    }

    public function getNetAmountAttribute(): float
    {
        return (float) $this->amount - (float) $this->fee;
    }

}
