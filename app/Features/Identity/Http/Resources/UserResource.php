<?php

namespace App\Features\Identity\Http\Resources;

use App\Application\Support\PaymentGatewaySettings;
use App\Application\Support\SubscriptionPriceQuote;
use App\Models\Country;
use App\Models\Region;
use App\Models\SubscriptionPlan;
use App\Models\SystemSetting;
use App\Models\UserSubscription;
use App\Models\Wallet;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $subscriptionContext = $this->subscriptionContext();
        return [
            'id' => $this->id,
            'full_name' => $this->full_name,
            'username' => $this->username,
            'email' => $this->email,
            'phone' => $this->phone,
            'country_code' => $this->country_code,
            'country_name' => $this->country?->name,
            'preferred_currency' => $this->preferred_currency,
            'avatar' => \App\Application\Support\Media::url($this->avatar),
            'cover_image' => \App\Application\Support\Media::url($this->cover_image),
            'status' => $this->status?->value,
            'email_verified' => $this->email_verified_at !== null,
            'has_password' => filled($this->password),
            'security_pin_set' => filled($this->security_pin),
            'two_factor' => (bool) $this->two_factor,
            'created_at' => $this->created_at,
            'subscription_context' => $subscriptionContext,
        ];
    }

    private function subscriptionContext(): array
    {
        $userId = $this->resource->id;
        $bonusSetting = SystemSetting::query()->where('setting_key', 'rewards.subscription_bonus_active')->value('value');
        $bonusActive = $bonusSetting === null
            || filter_var(is_array($bonusSetting) ? ($bonusSetting['enabled'] ?? true) : $bonusSetting, FILTER_VALIDATE_BOOLEAN);
        $paymentOptions = PaymentGatewaySettings::options();
        $priceQuote = app(SubscriptionPriceQuote::class);
        $countryNames = Country::query()->pluck('name', 'code')->all();
        $regionNames = Region::query()->pluck('name', 'code')->all();

        $planModels = SubscriptionPlan::query()
            ->where('is_active', true)
            ->with(['features', 'locationCoverage'])
            ->orderBy('price_minor')
            ->get();

        $subscriptions = UserSubscription::query()
            ->where('user_id', $userId)
            ->where('status', 'active')
            ->where('ends_at', '>', now())
            ->with(['plan.features', 'plan.locationCoverage', 'locationEntitlements'])
            ->orderByDesc('starts_at')
            ->get();
        $activeMarketplace = $subscriptions->first(
            fn (UserSubscription $subscription): bool => $subscription->product_type->value === 'marketplace_access',
        );
        $activeAddons = $subscriptions->filter(
            fn (UserSubscription $subscription): bool => $subscription->product_type->value === 'location_coverage',
        );

        $wallets = Wallet::query()->where('user_id', $userId)->where('status', 'active')->get();
        $sums = DB::table('ledger_entries')
            ->whereIn('wallet_id', $wallets->pluck('id'))
            ->where(function ($query): void {
                $query->where(fn ($credits) => $credits->where('direction', 'credit')->where('status', 'posted'))
                    ->orWhere(fn ($debits) => $debits->where('direction', 'debit')->whereIn('status', ['posted', 'reserved']));
            })
            ->selectRaw('wallet_id, direction, SUM(amount_minor) AS total_minor')
            ->groupBy('wallet_id', 'direction')
            ->get()
            ->groupBy('wallet_id');

        $walletSnapshot = $wallets->map(function (Wallet $wallet) use ($sums): array {
            $rows = $sums->get($wallet->id, collect());
            $credits = (int) ($rows->firstWhere('direction', 'credit')->total_minor ?? 0);
            $debits = (int) ($rows->firstWhere('direction', 'debit')->total_minor ?? 0);

            return [
                'id' => $wallet->id,
                'type' => $wallet->wallet_type->value,
                'currency' => $wallet->currency,
                'available_balance_minor' => max(0, $credits - $debits),
            ];
        })->values();

        return [
            'catalog' => [
                'marketplace_access' => $planModels
                    ->filter(fn (SubscriptionPlan $plan): bool => $plan->product_type->value === 'marketplace_access')
                    ->map(fn (SubscriptionPlan $plan): array => $this->decoratePlan($plan, $bonusActive, $priceQuote, $paymentOptions, $countryNames, $regionNames))
                    ->values(),
                'location_coverage' => $planModels
                    ->filter(fn (SubscriptionPlan $plan): bool => $plan->product_type->value === 'location_coverage')
                    ->map(fn (SubscriptionPlan $plan): array => $this->decoratePlan($plan, $bonusActive, $priceQuote, $paymentOptions, $countryNames, $regionNames))
                    ->values(),
            ],
            'active_marketplace' => $activeMarketplace
                ? $this->decorateSubscription($activeMarketplace, $bonusActive, $priceQuote, $paymentOptions, $countryNames, $regionNames)
                : null,
            'active_location_addons' => $activeAddons->map(fn (UserSubscription $subscription): array => $this->decorateSubscription($subscription, $bonusActive, $priceQuote, $paymentOptions, $countryNames, $regionNames))->values(),
            'active_subscriptions' => $subscriptions->map(fn (UserSubscription $subscription): array => $this->decorateSubscription($subscription, $bonusActive, $priceQuote, $paymentOptions, $countryNames, $regionNames))->values(),
            'wallets' => $walletSnapshot,
            'payment_options' => $paymentOptions,
        ];
    }

    private function decorateSubscription(
        UserSubscription $subscription,
        bool $bonusActive,
        SubscriptionPriceQuote $priceQuote,
        array $paymentOptions,
        array $countryNames,
        array $regionNames,
    ): array {
        $data = $subscription->toArray();
        if ($subscription->relationLoaded('plan') && $subscription->plan) {
            $data['plan'] = $this->decoratePlan($subscription->plan, $bonusActive, $priceQuote, $paymentOptions, $countryNames, $regionNames);
        }

        return $data;
    }

    private function decoratePlan(
        SubscriptionPlan $plan,
        bool $bonusActive,
        SubscriptionPriceQuote $priceQuote,
        array $paymentOptions,
        array $countryNames,
        array $regionNames,
    ): array {
        $data = $plan->toArray();
        $data['bonus_active'] = $bonusActive;
        $coverage = $data['location_coverage'] ?? [];
        if (is_array($coverage)) {
            foreach ($coverage as &$target) {
                if (! is_array($target)) continue;
                $code = strtoupper((string) ($target['target_code'] ?? ''));
                $type = strtolower((string) ($target['target_type'] ?? 'country'));
                $target['target_name'] = $type === 'region'
                    ? ($regionNames[$code] ?? $code)
                    : ($countryNames[$code] ?? $code);
            }
            unset($target);
            $data['location_coverage'] = $coverage;
        }
        $data['checkout_options'] = $priceQuote->optionsFor($plan, $paymentOptions);

        return $data;
    }
}
