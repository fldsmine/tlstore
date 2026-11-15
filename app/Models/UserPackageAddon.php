<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use InvalidArgumentException;

class UserPackageAddon extends Model
{
    protected $fillable = [
        'user_id',
        'package_addon_id',
        'payment_intent_id',
        'status',
        'price_minor',
        'currency',
        'features',
        'starts_at',
        'ends_at',
    ];

    protected $casts = [
        'price_minor' => 'integer',
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
        'features' => 'array',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function addon(): BelongsTo
    {
        return $this->belongsTo(PackageAddon::class, 'package_addon_id');
    }

    public function paymentIntent(): BelongsTo
    {
        return $this->belongsTo(PaymentIntent::class);
    }

    protected static function booted(): void
    {
        static::creating(function (UserPackageAddon $addon): void {

            // Get the selected package addon
            $package = PackageAddon::find($addon->package_addon_id);

            if (!$package) {
                throw new InvalidArgumentException(
                    'Selected package addon does not exist.'
                );
            }

            // Copy package features
            $features = $package->features ?? [];

            if (!is_array($features)) {
                $features = (array) $features;
            }

            // Get supported countries
            $countries = $features['country_code'] ?? [];

            if (!is_array($countries)) {
                $countries = [$countries];
            }

            // Generate IP addresses when dedicated IPs are enabled
            if (!empty($features['dedicated_ips'])) {
                $features['ip_addresses'] = static::generateIPs($countries);
            }

            // Generate phone number when phone number is enabled
            if (!empty($features['phone_no'])) {
                $features['phone_number'] = static::generatePhone($countries);
            }

            // Assign all features to the user's addon
            $addon->features = $features;
        });
    }

    /**
     * Generate synthetic IP addresses.
     *
     * These documentation addresses are NOT public,
     * routable or country-geolocated IP addresses.
     */
    public static function generateIPs(array $countries = []): array
    {
        $ips = [];

        foreach ($countries as $country) {
            $country = strtoupper($country);

            // Documentation-only IPv4 ranges.
            $ranges = [
                'NG' => ['192.0.2.0', '192.0.2.255'],
                'US' => ['198.51.100.0', '198.51.100.255'],
                'GB' => ['203.0.113.0', '203.0.113.255'],
                'UK' => ['203.0.113.0', '203.0.113.255'],
                'CA' => ['198.51.100.0', '198.51.100.255'],
                'CN' => ['192.0.2.0', '192.0.2.255'],
                'NA' => ['198.51.100.0', '198.51.100.255'],
            ];

            if (!isset($ranges[$country])) {
                continue;
            }

            $start = ip2long($ranges[$country][0]);
            $end = ip2long($ranges[$country][1]);

            $ips[$country] = long2ip(random_int($start, $end));
        }

        return $ips;
    }

    /**
     * Generate a synthetic phone number.
     */
    public static function generatePhone(array $countries = []): ?string
    {
        if (empty($countries)) {
            return null;
        }

        $country = strtoupper($countries[array_rand($countries)]);

        // Reserved/example numbers where applicable.
        $formats = [
            'US' => '+120255501',
            'CA' => '+120255501',
            'GB' => '+441632960',
            'UK' => '+441632960',
        ];

        if (!isset($formats[$country])) {
            return null;
        }

        // Append a two-digit suffix.
        return $formats[$country] . str_pad(
            (string) random_int(0, 99),
            2,
            '0',
            STR_PAD_LEFT
        );
    }
}
