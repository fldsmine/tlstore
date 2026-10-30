<?php

namespace App\Http\Resources\Api\V1;

use App\Models\User;
use App\Support\RegionPricing;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Package catalogue entry. Pricing is resolved server-side for the
 * REQUESTING user's region (Nigerian vs foreign) — anonymous requests see
 * the Nigerian price.
 *
 * @mixin \App\Models\Package
 */
class PackageResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $user = $request->user();
        $region = $user instanceof User ? RegionPricing::forUser($user) : \App\Models\Coupon::REGION_NIGERIA;
        $hasPackage = $user instanceof User && $user->package_id !== null;

        return [
            'id' => $this->id,
            'name' => $this->name,
            'description' => $this->description,
            'tier_level' => (int) $this->tier_level,
            'price_usd' => RegionPricing::customer($this->resource, $region),
            'upgrade_price_usd' => $hasPackage ? RegionPricing::upgradeCustomer($this->resource, $region) : null,
            'daily_call_minutes' => (float) $this->daily_call_minutes,
            'monthly_share_minutes' => (float) $this->monthly_share_minutes,
            'share_term_days' => (int) $this->share_term_days,
            'share_earning_enabled' => (float) $this->monthly_share_minutes > 0
                && (int) $this->share_term_days > 0
                && (float) $this->max_daily_shareholder_earning > 0,
            'call_id_limit' => (int) $this->call_id_limit,
            'direct_referral_bonus_usd' => (float) $this->direct_referral_bonus,
            'indirect_referral_bonus_usd' => (float) $this->indirect_referral_bonus,
            'max_daily_shareholder_earning_usd' => (float) $this->max_daily_shareholder_earning,
            'features' => $this->features,
            'enable_cloud' => (bool) $this->enable_cloud,
            'cloud_calls' => (bool) $this->cloud_calls,
            'cloud_sms' => (bool) $this->cloud_sms,
            'cloud_contact_limit' => (int) $this->cloud_contact_limit,
            'is_featured' => (bool) $this->is_featured,
        ];
    }
}
