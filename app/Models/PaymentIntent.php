<?php

namespace App\Models;

use App\Enums\PaymentIntentStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class PaymentIntent extends Model
{
    use TrustLensMorphMap;

    protected $table = 'payment_intents';
    protected $fillable = [
        'user_id', 'purpose', 'source_type', 'source_id', 'amount_minor', 'currency', 'provider',
        'provider_reference', 'idempotency_key', 'status', 'provider_metadata', 'expires_at', 'paid_at',
    ];
    protected $casts = [
        'amount_minor' => 'integer',
        'status' => PaymentIntentStatus::class,
        'provider_metadata' => 'array',
        'expires_at' => 'datetime',
        'paid_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function source(): MorphTo
    {
        return $this->morphTo(__FUNCTION__, 'source_type', 'source_id');
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(PaymentTransaction::class);
    }

    public function manualDeposit(): HasOne
    {
        return $this->hasOne(ManualDepositSubmission::class);
    }

    public function userSubscriptions(): HasMany
    {
        return $this->hasMany(UserSubscription::class);
    }

    public function userPackageAddon(): HasOne
    {
        return $this->hasOne(UserPackageAddon::class);
    }

    public function ledgerEntries(): MorphMany
    {
        return $this->morphMany(LedgerEntry::class, 'source');
    }
}
