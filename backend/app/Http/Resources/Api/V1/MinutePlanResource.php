<?php

namespace App\Http\Resources\Api\V1;

use App\Models\Coupon;
use App\Models\User;
use App\Support\RegionPricing;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\AdditionalMinutePlan */
class MinutePlanResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $user = $request->user();
        $region = $user instanceof User ? RegionPricing::forUser($user) : Coupon::REGION_NIGERIA;

        return [
            'id' => $this->id,
            'name' => $this->name,
            'description' => $this->description,
            'price_usd' => RegionPricing::customer($this->resource, $region),
            'daily_call_minutes' => (float) $this->daily_call_minutes,
            'share_minutes' => (float) $this->share_minutes,
            'duration_days' => (int) $this->duration_days,
            'paylater_duration_days' => (int) $this->paylater_duration_days,
            'min_earning_rate' => (float) $this->min_earning_rate,
            'call_id_bonus' => (int) $this->call_id_bonus,
            'features' => $this->features,
            'is_featured' => (bool) $this->is_featured,
        ];
    }
}
