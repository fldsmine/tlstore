<?php

namespace App\Features\Identity\Http\Resources;

use App\Application\Support\PaymentGatewaySettings;
use App\Application\Support\SubscriptionPriceQuote;
use App\Models\SubscriptionPlan;
use App\Models\SystemSetting;
use App\Models\UserSubscription;
use App\Models\Wallet;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $subscriptionContext = $this->subscriptionContext();
        $payoutProfile = $this->resource->payoutProfile()->first();
        return [
            'id' => $this->id,
            'full_name' => $this->full_name,
            'username' => $this->username,
            'email' => $this->email,
            'phone' => $this->phone,
            'country_code' => $this->country_code,
            'country_name' => $this->country?->name,
            'preferred_currency' => $this->preferred_currency,
            'wallet_currency' => $this->wallet_currency ?: $this->preferred_currency,
            'avatar' => \App\Application\Support\Media::url($this->avatar),
            'cover_image' => \App\Application\Support\Media::url($this->cover_image),
            'status' => $this->status?->value,
            'email_verified' => $this->email_verified_at !== null,
            'has_password' => filled($this->password),
            'security_pin_set' => filled($this->security_pin),
            'has_addon' => $this->has_addon,
            'referral_code' => $this->referral_code,
            'is_member' => $this->is_member,
            'two_factor' => (bool) $this->two_factor,
            'created_at' => $this->created_at,
            'subscription_context' => $subscriptionContext,
            'payout_profile' => $payoutProfile ? [
                'withdrawal_option_id' => $payoutProfile->withdrawal_option_id,
                'details' => $payoutProfile->details,
            ] : null,
            'subscription_questionnaire' => [
                'completed_groups' => $this->subscription_questionnaire_completed_groups ?? [],
                'completed_at' => $this->subscription_questionnaire_completed_at,
            ],
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
        $planModels = SubscriptionPlan::query()
            ->where('is_active', true)
            ->with(['features', 'group'])
            ->orderBy('price_minor')
            ->get();

        $subscriptions = UserSubscription::query()
            ->where('user_id', $userId)
            ->where('product_type', 'marketplace_access')
            ->where('status', 'active')
            ->where('ends_at', '>', now())
            ->with(['plan.features', 'plan.group'])
            ->orderByDesc('starts_at')
            ->get();
        $activeMarketplace = $subscriptions->first(
            fn (UserSubscription $subscription): bool => $subscription->product_type->value === 'marketplace_access',
        );
        $activeMarketplaces = $subscriptions->map(fn (UserSubscription $subscription): array => [
            'id' => $subscription->id,
            'subscription_plan_id' => $subscription->subscription_plan_id,
            'group_key' => $subscription->plan?->group?->group_key,
            'plan_name' => $subscription->plan?->name,
            'starts_at' => $subscription->starts_at,
            'ends_at' => $subscription->ends_at,
            'status' => $subscription->status->value,
        ])->values();
        $wallets = Wallet::query()->where('user_id', $userId)->where('status', 'active')->get();
        $walletSnapshot = $wallets->map(function (Wallet $wallet): array {
            return [
                'id' => $wallet->id,
                'type' => $wallet->wallet_type->value,
                'currency' => $wallet->currency,
                'balance_minor' => (int) $wallet->balance_minor,
                'available_balance_minor' => (int) $wallet->balance_minor,
            ];
        })->values();

        return [
            'catalog' => [
                'subscription_groups' => $planModels
                    ->filter(fn (SubscriptionPlan $plan): bool => $plan->product_type->value === 'marketplace_access' && $plan->group?->is_active)
                    ->groupBy(fn (SubscriptionPlan $plan): string => $plan->group->group_key)
                    ->map(function ($plans) use ($bonusActive, $priceQuote, $paymentOptions): array {
                        $group = $plans->first()->group->toArray();
                        $group['plans'] = $plans->map(function (SubscriptionPlan $plan) use ($bonusActive, $priceQuote, $paymentOptions, $group): array {
                            $data = $this->decoratePlan($plan, $bonusActive, $priceQuote, $paymentOptions);
                            $data['group_key'] = $group['group_key'];
                            return $data;
                        })->values()->all();
                        return $group;
                    })->values(),
            ],
            'active_marketplace' => $activeMarketplace
                ? [
                    'id' => $activeMarketplace->id,
                    'subscription_plan_id' => $activeMarketplace->subscription_plan_id,
                    'starts_at' => $activeMarketplace->starts_at,
                    'ends_at' => $activeMarketplace->ends_at,
                    'status' => $activeMarketplace->status->value,
                ]
                : null,
            'active_marketplaces' => $activeMarketplaces,
            'wallets' => $walletSnapshot,
        ];
    }

    private function decoratePlan(
        SubscriptionPlan $plan,
        bool $bonusActive,
        SubscriptionPriceQuote $priceQuote,
        array $paymentOptions,
    ): array {
        $data = $plan->toArray();
        $data['group_key'] = $plan->group?->group_key;
        $data['bonus_active'] = $bonusActive;
        $data['checkout_options'] = $priceQuote->optionsFor($plan, $paymentOptions);

        return $data;
    }
}
