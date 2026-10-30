<?php

namespace App\Models;

use App\Enums\CouponType;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CouponBatch extends Model
{
    use HasUuids;

    protected function casts(): array
    {
        return [
            'type' => CouponType::class,
            'region' => 'string',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (CouponBatch $batch): void {
            if (empty($batch->reference)) {
                $batch->reference = static::generateReference();
            }
        });
    }

    public static function generateReference(): string
    {
        do {
            $reference = 'BTH'.now()->format('ymdHis').str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        } while (static::query()->where('reference', $reference)->exists());

        return $reference;
    }

    public function agent(): BelongsTo
    {
        return $this->belongsTo(User::class, 'agent_id');
    }

    public function package(): BelongsTo
    {
        return $this->belongsTo(Package::class);
    }

    public function callType(): BelongsTo
    {
        return $this->belongsTo(CallType::class);
    }

    public function additionalMinutePlan(): BelongsTo
    {
        return $this->belongsTo(AdditionalMinutePlan::class);
    }

    public function cloudStoragePlan(): BelongsTo
    {
        return $this->belongsTo(CloudStoragePlan::class);
    }

    public function coupons(): HasMany
    {
        return $this->hasMany(Coupon::class, 'batch_id');
    }

    /** Human label for what this batch mints — an UPGRADE batch is suffixed to set it apart from activation. */
    public function displayName(): string
    {
        $name = $this->package?->name
            ?? $this->callType?->name
            ?? $this->additionalMinutePlan?->name
            ?? $this->cloudStoragePlan?->name
            ?? $this->type->getName();

        return match ($this->type) {
            CouponType::UPGRADE => $name.' Upgrade',
            CouponType::CLOUD_RENEWAL => $name.' Renewal',
            default => $name,
        };
    }
}
