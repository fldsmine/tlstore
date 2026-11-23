<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** Embedded package snapshot (no pricing). */
class PackageSummaryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'tier_level' => (int) $this->tier_level,
            'daily_call_minutes' => (float) $this->daily_call_minutes,
            'monthly_share_minutes' => (float) $this->monthly_share_minutes,
            'share_term_days' => (int) $this->share_term_days,
            'max_daily_shareholder_earning_usd' => (float) $this->max_daily_shareholder_earning,
            'call_id_limit' => (int) $this->call_id_limit,
            'enable_cloud' => (bool) $this->enable_cloud,
            'cloud_calls' => (bool) $this->cloud_calls,
            'cloud_sms' => (bool) $this->cloud_sms,
        ];
    }
}
