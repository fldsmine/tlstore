<?php

namespace App\Features\Campaigns\Http\Controllers\Api\V1;

use App\Application\Actions\Campaigns\CreateCampaignOrder;
use App\Application\Money\CurrencyMinorUnits;
use App\Models\SystemSetting;
use App\Models\UserCampaignOrder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class CampaignOrderController
{
    public function config(): JsonResponse
    {
        $services = SystemSetting::query()->where('setting_key', 'campaign.services')->first()?->value ?? [];
        $fee = SystemSetting::query()->where('setting_key', 'campaign.service_fee_percent')->first()?->value ?? 5;
        $currency = strtoupper((string) (SystemSetting::query()->where('setting_key', 'campaign.currency')->first()?->value ?? 'NGN'));

        return response()->json(['data' => [
            'services' => $services,
            'service_fee_percent' => (float) $fee,
            'currency' => $currency,
            'currency_minor_units' => CurrencyMinorUnits::precision($currency),
        ]]);
    }

    public function store(Request $request, CreateCampaignOrder $create): JsonResponse
    {
        $services = SystemSetting::query()->where('setting_key', 'campaign.services')->first()?->value ?? [];
        $data = $request->validate([
            'campaign_name' => ['required', 'string', 'max:120'],
            'platform' => ['required', 'string', 'in:'.implode(',', array_keys($services))],
            'action' => ['required', 'string', 'max:32'],
            'quantity' => ['required', 'integer', 'min:1', 'max:10000000'],
            'target_url' => ['required', 'url', 'max:2048'],
            'instructions' => ['nullable', 'string', 'max:3000'],
            'idempotency_key' => ['required', 'string', 'max:80'],
        ]);
        abort_unless(isset($services[$data['platform']][$data['action']]) && $data['action'] !== 'min', 422, 'This campaign action is not configured.');

        $order = $create->handle($request->user(), $data);

        return response()->json(['data' => $order, 'message' => 'Campaign submitted for admin approval.'], 201);
    }

    public function mine(Request $request): JsonResponse
    {
        return response()->json(['data' => UserCampaignOrder::query()->with('ad:id,user_campaign_order_id,status,title')
            ->where('user_id', $request->user()->id)->latest()->paginate(20)]);
    }
}
