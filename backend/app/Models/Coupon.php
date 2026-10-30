<?php

namespace App\Models;

use App\Enums\CouponType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class Coupon extends Model
{
    use HasFactory, HasUuids;

    public const REGION_NIGERIA = 'nigeria';

    public const REGION_FOREIGN = 'foreign';

    /** Where a code came from — see the provenance migration. */
    public const SOURCE_VENDOR = 'vendor';

    public const SOURCE_DIRECT = 'direct';

    public const SOURCE_ADMIN = 'admin';

    /**
     * Provenance defaults to the historical channel: a coupon minted without an explicit source came
     * from a vendor. Reads must never see null here — the column default only applies on the way back
     * from the database, not on the instance that just created the row.
     */
    protected $attributes = [
        'source' => self::SOURCE_VENDOR,
    ];

    protected function casts(): array
    {
        return [
            'type' => CouponType::class,
            'price' => 'decimal:6',
            'is_special' => 'boolean',
            'region' => 'string',
            'used_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }

    public static function regionFor(User $vendor): string
    {
        return $vendor->isNigerian() ? self::REGION_NIGERIA : self::REGION_FOREIGN;
    }

    public function isForeign(): bool
    {
        return $this->region === self::REGION_FOREIGN;
    }

    protected static function booted(): void
    {
        static::creating(function (Coupon $coupon): void {
            if (empty($coupon->code)) {
                $coupon->code = static::generateCode();
            }
            
            // Always set code_hash when code is set (for new and updating)
            if (!empty($coupon->code) && empty($coupon->code_hash)) {
                $coupon->code_hash = static::hashCode($coupon->code);
            }
        });

        static::updating(function (Coupon $coupon): void {
            // Re-hash if code changes
            if ($coupon->isDirty('code')) {
                $coupon->code_hash = static::hashCode($coupon->code);
            }
        });
    }

    public static function generateCode(?string $vendorUsername = null, ?string $planName = null): string
    {
        $prefix = static::codePrefix($vendorUsername, $planName);

        do {
            $code = $prefix.Str::upper(Str::random(12));
        } while (static::query()->where('code', $code)->exists());

        return $code;
    }

    public static function codePrefix(?string $vendorUsername, ?string $planName): string
    {
        $initials = function (?string $value): string {
            $alnum = preg_replace('/[^A-Za-z0-9]/', '', (string) $value) ?? '';

            return Str::upper(substr($alnum, 0, 3));
        };

        $vendor = $initials($vendorUsername);
        $plan = $initials($planName);

        return $vendor !== '' && $plan !== '' ? $vendor.$plan.'-' : '';
    }

    /**
     * Generate a secure HMAC-SHA256 hash of the coupon code for lookup.
     * Uses application key as the HMAC secret.
     */
    public static function hashCode(string $code): string
    {
        $normalized = strtoupper(trim($code));
        return hash_hmac('sha256', $normalized, config('app.key'));
    }

    /**
     * Verify if the input code matches this coupon's code (using HMAC comparison).
     */
    public function verifyCode(string $inputCode): bool
    {
        if (empty($this->code_hash)) {
            return false;
        }

        $inputHash = static::hashCode($inputCode);
        return hash_equals($this->code_hash, $inputHash);
    }

    /** The charge that bought this code (direct purchases), when there was one. */
    public function callType(): BelongsTo
    {
        return $this->belongsTo(CallType::class);
    }

    public function paymentTransaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class, 'payment_reference', 'reference');
    }

    /** The super admin who issued this code against payment proof. */
    public function issuedByAdmin(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'issued_by_admin_id');
    }

    public function getIsValidAttribute(): bool
    {
        return is_null($this->user_id);
    }

    public function claim(User $user): bool
    {
        $claimed = static::query()
            ->whereKey($this->getKey())
            ->whereNull('used_at')
            ->update(['user_id' => $user->id, 'used_at' => now()]);

        return $claimed === 1;
    }

    /**
     * Claim the coupon for storage activation only.
     * Binds to user but does NOT mark as used (used_at remains NULL).
     * This allows the same code to be used later for withdrawal authorization.
     */
    public function claimForStorage(User $user): bool
    {
        $claimed = static::query()
            ->whereKey($this->getKey())
            ->whereNull('user_id')
            ->whereNull('used_at')
            ->update(['user_id' => $user->id]);

        return $claimed === 1;
    }

    public function scopeValid(Builder $query): Builder
    {
        return $query->whereNull('user_id');
    }

    /** Available · Used (a code is redeemed or it isn't). */
    public function getStatusLabelAttribute(): string
    {
        return $this->user_id !== null ? 'Used' : 'Available';
    }

    /** Unredeemed stock. */
    public function scopeAvailable(Builder $query): Builder
    {
        return $query->whereNull('used_at');
    }

    /**
     * Scope for storage activation coupons that are available for activation.
     */
    public function scopeStorageActivation(Builder $query): Builder
    {
        return $query->where('type', CouponType::STORAGE_ACTIVATION->value)
            ->whereNull('user_id')
            ->whereNull('used_at');
    }

    /**
     * Scope for coupons bound to a user (storage activated, available for withdrawal).
     */
    public function scopeBoundToUser(Builder $query, User $user): Builder
    {
        return $query->where('user_id', $user->id)
            ->whereNull('used_at');
    }

    /**
     * Check if coupon is expired.
     */
    public function getIsExpiredAttribute(): bool
    {
        if ($this->expires_at === null) {
            return false;
        }

        return $this->expires_at->isPast();
    }

    /**
     * Check if coupon is available for storage activation (not bound, not used, not expired).
     */
    public function getIsAvailableForActivationAttribute(): bool
    {
        return $this->user_id === null
            && $this->used_at === null
            && !$this->is_expired;
    }

    /**
     * Check if coupon is available for withdrawal authorization (bound to user, not used, not expired).
     */
    public function getIsAvailableForWithdrawalAttribute(): bool
    {
        return $this->user_id !== null
            && $this->used_at === null
            && !$this->is_expired;
    }

    public function redeemer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function agent(): BelongsTo
    {
        return $this->belongsTo(User::class, 'agent_id');
    }

    public function package(): BelongsTo
    {
        return $this->belongsTo(Package::class);
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(CouponBatch::class, 'batch_id');
    }

    public function additionalMinutePlan(): BelongsTo
    {
        return $this->belongsTo(AdditionalMinutePlan::class);
    }

    public function cloudStoragePlan(): BelongsTo
    {
        return $this->belongsTo(CloudStoragePlan::class);
    }

    /** Human label for what this coupon grants — an UPGRADE coupon is suffixed to set it apart from activation. */
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
