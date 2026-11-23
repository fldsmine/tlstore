<?php

namespace App\Support;

use App\Models\CallType;
use App\Models\CloudStoragePlan;
use App\Models\Coupon;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class RegionPricing
{
    private const CUSTOMER_COLUMNS = [
        CloudStoragePlan::class => ['token_price', 'foreign_token_price'],
    ];

    private const DEFAULT_CUSTOMER_COLUMNS = ['price', 'foreign_price'];

    private const AGENT_COLUMNS = ['agent_price', 'foreign_agent_price'];

    public static function customer(Model $product, string $region): float
    {
        [$base, $foreign] = self::CUSTOMER_COLUMNS[$product::class] ?? self::DEFAULT_CUSTOMER_COLUMNS;

        return $product instanceof CallType
            ? self::resolveCallType($product, $base, $foreign, $region)
            : self::resolve($product, $base, $foreign, $region);
    }

    public static function agent(Model $product, string $region): float
    {
        [$base, $foreign] = self::AGENT_COLUMNS;

        return $product instanceof CallType
            ? self::resolveCallType($product, $base, $foreign, $region)
            : self::resolve($product, $base, $foreign, $region);
    }

    /** What a customer pays to upgrade TO this package (foreign nullable → Nigerian fallback). */
    public static function upgradeCustomer(Model $product, string $region): float
    {
        return self::resolve($product, 'upgrade_price', 'foreign_upgrade_price', $region);
    }

    /** What a vendor pays to mint an upgrade coupon for this package. */
    public static function upgradeAgent(Model $product, string $region): float
    {
        return self::resolve($product, 'upgrade_agent_price', 'foreign_upgrade_agent_price', $region);
    }

    /** What a customer pays to renew this cloud storage plan (0 = not renewable). */
    public static function renewalCustomer(Model $product, string $region): float
    {
        return self::resolve($product, 'renewal_token_price', 'foreign_renewal_token_price', $region);
    }

    /** What a vendor pays to mint a renewal coupon for this cloud storage plan. */
    public static function renewalAgent(Model $product, string $region): float
    {
        return self::resolve($product, 'renewal_agent_price', 'foreign_renewal_agent_price', $region);
    }

    public static function forUser(User $user, string $fallback = Coupon::REGION_NIGERIA): string
    {
        if (blank($user->country)) {
            return $fallback;
        }

        return $user->isNigerian() ? Coupon::REGION_NIGERIA : Coupon::REGION_FOREIGN;
    }

    public static function pinResetCustomer(string $region): float
    {
        return self::setting('token.pin_reset', $region, 'price', 'foreign_price', 1);
    }

    public static function pinResetAgent(string $region): float
    {
        return self::setting('token.pin_reset', $region, 'agent_price', 'foreign_agent_price', 0.8);
    }

    /** What a customer pays a vendor for a website-renewal code (code mode). */
    public static function websiteRenewalCustomer(string $region): float
    {
        return self::setting('website.code', $region, 'price', 'foreign_price', 1);
    }

    /** What a vendor pays to mint a website-renewal code. */
    public static function websiteRenewalAgent(string $region): float
    {
        return self::setting('website.code', $region, 'agent_price', 'foreign_agent_price', 0.8);
    }

    private static function setting(string $prefix, string $region, string $base, string $foreign, float $default): float
    {
        $key = $region === Coupon::REGION_FOREIGN ? $foreign : $base;

        return (float) (config("settings.{$prefix}.{$key}") ?? config("settings.{$prefix}.{$base}", $default));
    }

    private static function resolveCallType(CallType $product, string $base, string $alternate, string $region): float
    {
        // `region` identifies which market the primary CallType price belongs to; it defaults to Nigeria.
        $baseRegion = in_array($product->region, [Coupon::REGION_NIGERIA, Coupon::REGION_FOREIGN], true)
            ? $product->region
            : Coupon::REGION_NIGERIA;

        if ($region !== $baseRegion && $product->{$alternate} !== null) {
            return (float) $product->{$alternate};
        }

        return (float) $product->{$base};
    }

    private static function resolve(Model $product, string $base, string $foreign, string $region): float
    {
        if ($region === Coupon::REGION_FOREIGN && $product->{$foreign} !== null) {
            return (float) $product->{$foreign};
        }

        return (float) $product->{$base};
    }
}
