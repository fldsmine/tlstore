<?php

namespace App\Features\Marketplace\Http\Controllers\Api\V1;

use App\Models\Country;
use App\Models\AppReviewCampaign;
use App\Models\SubscriptionPlan;
use App\Models\SystemSetting;
use App\Application\Support\PaymentGatewaySettings;
use App\Models\ExchangeRateSnapshot;
use App\Models\PaymentOption;
use App\Models\Testimonial;
use App\Models\Faq;
use App\Models\Region;
use App\Application\Money\CurrencyMinorUnits;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Services\PublicFrontService;

final class PublicCatalogController
{

    public function countries(): JsonResponse
    {
        $countries = Country::query()
            ->where('is_available', true)
            ->orderBy('name')
            ->get(['code', 'name', 'default_currency']);
        $countries->each(fn (Country $country) => $country->setAttribute(
            'currency_minor_units', CurrencyMinorUnits::precision($country->default_currency ?? 'USD'),
        ));

        return response()->json(['data' => $countries]);
    }

    public function regions(): JsonResponse
    {
        return response()->json(['data' => Region::query()->where('is_available', true)
            ->with(['countries' => fn ($query) => $query->where('countries.is_available', true)->orderBy('name')->select(['countries.code', 'countries.name'])])
            ->orderBy('name')->get(['id', 'code', 'name'])]);
    }

    public function spotlights(PublicFrontService $sports): JsonResponse
    {
		$data = $sports->getSportlight();
		
        return response()->json(['data' => $data]);
    }

    public function faqs(): JsonResponse
    {
        $faqs = Faq::query()
            ->active()
            ->ordered()
            ->get(['id', 'question', 'answer', 'category']);

        return response()->json(['data' => $faqs]);
    }

    public function plans(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'product_type' => ['sometimes', 'in:marketplace_access'],
        ]);

        $plans = SubscriptionPlan::query()
            ->where('is_active', true)
            ->when(isset($validated['product_type']), fn ($query) => $query->where('product_type', $validated['product_type']))
            ->with(['features', 'group', 'packageAddon'])
            ->orderBy('product_type')
            ->orderBy('price_minor')
            ->get();
        $bonusSetting = SystemSetting::query()->where('setting_key', 'rewards.subscription_bonus_active')->value('value');
        $bonusActive = $bonusSetting === null || filter_var(is_array($bonusSetting) ? ($bonusSetting['enabled'] ?? true) : $bonusSetting, FILTER_VALIDATE_BOOLEAN);

        $decorated = $plans->map(fn (SubscriptionPlan $plan) => array_merge($plan->toArray(), ['bonus_active' => $bonusActive]))->values();
        return response()->json([
            'data' => $decorated,
            'groups' => $plans->filter(fn (SubscriptionPlan $plan) => $plan->group?->is_active)
                ->groupBy(fn (SubscriptionPlan $plan) => $plan->group->group_key)
                ->map(function ($rows) use ($decorated): array {
                    $group = $rows->first()->group->toArray();
                    $group['plans'] = $rows->map(fn ($plan) => $decorated->firstWhere('id', $plan->id))->values()->all();
                    return $group;
                })->values(),
        ]);
    }

    public function paymentGateways(): JsonResponse
    {
        $implemented = config('trustlens.implemented_payment_gateways', []);
        $gateways = PaymentGatewaySettings::all();

        $data = collect($gateways)
            ->filter(fn (array $currencies, string $gateway) => in_array($gateway, $implemented, true))
            ->map(fn (array $currencies, string $gateway) => [
                'gateway' => $gateway,
                'supported_currencies' => $currencies,
            ])->values();

        return response()->json(['data' => $data, 'options' => PaymentGatewaySettings::options()]);
    }

    public function exchangeRates(Request $request): JsonResponse
    {
        $data = $request->validate([
            'base' => ['required', 'string', 'size:3'],
            'quote' => ['sometimes', 'string', 'size:3'],
        ]);
        $base = strtoupper($data['base']);
        $latest = ExchangeRateSnapshot::query()->selectRaw('base_currency, quote_currency, MAX(fetched_at) AS fetched_at')
            ->where('base_currency', $base);
        if (isset($data['quote'])) {
            $latest->where('quote_currency', strtoupper($data['quote']));
        }
        $latest->groupBy('base_currency', 'quote_currency');

        $rates = DB::table('exchange_rate_snapshots as rates')
            ->joinSub($latest, 'latest_rates', function ($join): void {
                $join->on('rates.base_currency', '=', 'latest_rates.base_currency')
                    ->on('rates.quote_currency', '=', 'latest_rates.quote_currency')
                    ->on('rates.fetched_at', '=', 'latest_rates.fetched_at');
            })
            ->orderBy('rates.quote_currency')
            ->get([
                'rates.base_currency', 'rates.quote_currency', 'rates.rate', 'rates.source',
                'rates.is_fallback', 'rates.fetched_at', 'rates.source_published_at',
            ]);

        return response()->json(['data' => $rates]);
    }

    public function currencies(): JsonResponse
    {
        $codes = ExchangeRateSnapshot::query()->select('base_currency')->distinct()->pluck('base_currency')
            ->merge(ExchangeRateSnapshot::query()->select('quote_currency')->distinct()->pluck('quote_currency'))
            ->unique()->sort()->values();

        return response()->json(['data' => $codes->map(fn (string $code) => [
            'code' => $code,
            'minor_units' => CurrencyMinorUnits::precision($code),
        ])]);
    }
}
