<?php

namespace App\Models;

use App\Enums\CallType as CallTypeEnum;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Arr;

class AllocatedNumber extends Model
{
    use HasFactory, HasUuids;

    public const string NEXTEL_PREFIX = '0700';

    public const array NETWORKS = [
        'Nextel' => ['color' => '#18443E', 'logo' => 'nextel.png', 'prefixes' => ['0700']],
        'MTN' => ['color' => '#FFCB05', 'logo' => 'mtn.jpg', 'prefixes' => ['0803', '0806', '0703', '0706', '0813', '0816', '0810', '0814', '0903', '0906']],
        'Glo' => ['color' => '#1F9E4A', 'logo' => 'glo.jpg', 'prefixes' => ['0805', '0807', '0705', '0815', '0811', '0905', '0915']],
        'Airtel' => ['color' => '#E40000', 'logo' => 'airtel.jpg', 'prefixes' => ['0802', '0808', '0708', '0812', '0701', '0902', '0901', '0904']],
        '9mobile' => ['color' => '#0A8A3C', 'logo' => '9mobile.png', 'prefixes' => ['0809', '0817', '0818', '0909', '0908']],
    ];

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where(function (Builder $q): void {
            $q->whereNull('expires_at')->orWhere('expires_at', '>', now());
        });
    }

    public function getNetworkAttribute(): string
    {
        foreach (self::NETWORKS as $name => $meta) {
            if (in_array($this->prefix, $meta['prefixes'], true)) {
                return $name;
            }
        }

        return 'Nextel';
    }

    public function getColorAttribute(): string
    {
        return self::NETWORKS[$this->network]['color'] ?? '#18443E';
    }

    public function getLogoAttribute(): string
    {
        $file = self::NETWORKS[$this->network]['logo'] ?? 'nextel.png';

        return asset('images/networks/'.$file);
    }

    public static function logoFor(?string $network): string
    {
        $file = self::NETWORKS[$network]['logo'] ?? 'nextel.png';

        return asset('images/networks/'.$file);
    }

    public function getMaskedAttribute(): string
    {
        $local = ltrim($this->prefix, '0');
        $tail = substr($this->suffix, -3);

        return "+234 {$local}••••{$tail}";
    }

    public function getIsExpiredAttribute(): bool
    {
        return $this->expires_at !== null && $this->expires_at->isPast();
    }

    public function callType(): CallTypeEnum
    {
        return $this->network === 'Nextel' ? CallTypeEnum::ON_NET : CallTypeEnum::OFF_NET;
    }

    public function ratePerMinute(?Package $package): float
    {
        $key = $this->callType();

        if ($package) {
            $package->loadMissing('callTypes');
            $type = $package->callTypes->first(fn ($c) => $c->key === $key);

            if ($type) {
                return (float) ($type->pivot->rate_per_minute ?? $type->rate_per_minute);
            }
        }

        return (float) (CallType::query()->where('key', $key->value)->value('rate_per_minute') ?? 0);
    }

    public static function randomPrefix(): string
    {

        return Arr::random(array_merge(...array_map(fn ($n) => $n['prefixes'], array_values(self::NETWORKS))));
    }

    public static function randomCounterparty(): string
    {
        return self::randomPrefix().str_pad((string) random_int(0, 9999999), 7, '0', STR_PAD_LEFT);
    }

    public static function networkFor(?string $number): ?string
    {
        $prefix = substr((string) $number, 0, 4);

        foreach (self::NETWORKS as $name => $meta) {
            if (in_array($prefix, $meta['prefixes'], true)) {
                return $name;
            }
        }

        return null;
    }

    public static function randomSuffix(): string
    {
        return str_pad((string) random_int(0, 9_999_999), 7, '0', STR_PAD_LEFT);
    }
}
