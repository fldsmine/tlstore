<?php

namespace App\Models;

use App\Enums\TransactionType;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Transaction extends Model
{
    use HasFactory, HasUuids;

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

    public function paymentResolutionLogs(): HasMany
    {
        return $this->hasMany(PaymentResolutionLog::class, 'transaction_id')->latest();
    }

    public function paymentReference(): HasOne
    {
        return $this->hasOne(PaymentReference::class, 'reference', 'reference');
    }

    public function getIsCreditAttribute(): bool
    {
        return $this->direction === 'credit';
    }

    public function getSignedAmountAttribute(): string
    {
        return ($this->is_credit ? '+' : '-').money($this->amount);
    }

    public function getNetAmountAttribute(): float
    {
        return (float) $this->amount - (float) $this->fee;
    }

    public function getNetworkLogoAttribute(): ?string
    {
        $network = $this->meta['network'] ?? null;

        return $network ? AllocatedNumber::logoFor($network) : null;
    }
}
