<?php

namespace App\Models;

use App\Enums\VtuStatus;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

class VtuOrder extends Model
{
    use HasFactory, HasUuids;

    public const SERVICE_AIRTIME = 'airtime';

    public const SERVICE_DATA = 'data';

    public const SERVICE_TV = 'tv';

    public const SERVICE_ELECTRICITY = 'electricity';

    public const SERVICE_EDUCATION = 'education';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'status' => VtuStatus::class,
            'amount_ngn' => 'decimal:2',
            'amount_usd' => 'decimal:6',
            'fee' => 'decimal:6',
            'refunded' => 'boolean',
            'meta' => 'array',
        ];
    }

    public static function generateRequestId(): string
    {
        $prefix = Carbon::now('Africa/Lagos')->format('YmdHi');

        do {
            $requestId = $prefix.strtolower(Str::random(12));
        } while (self::where('request_id', $requestId)->exists());

        return $requestId;
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function transaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class);
    }

    public function scopeSuccessful($query)
    {
        return $query->where('status', VtuStatus::DELIVERED);
    }

    public function scopePending($query)
    {
        return $query->where('status', VtuStatus::PENDING);
    }
}
