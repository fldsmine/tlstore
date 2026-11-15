<?php

namespace App\Enums;

use Illuminate\Support\Str;

enum TransactionType: string
{
    case ADS_SHARE = 'ads_share';
    case APP_REVIEW = 'app_review';
    case REFERRAL_BONUS = 'referral_bonus';
    case SOCIAL_FOLLOW_REWARD = 'social_follow_reward';
    case TOP_UP_FUND = 'top_up_fund';
    case WITHDRAWAL = 'withdrawal';
    case SUBSCRIPTION_PLAN = 'subscription_plan';
    case PACKAGE_ADDON = 'package_addon';
    case LOCATION_ADDONS = 'location_addons';
    case FUND_TRANSFER = 'fund_transfer';
    case RANK_EARNING = 'rank_earning';
    case VTU_PURCHASE = 'vtu_purchase';
    case CAMPAIGN_ORDER = 'campaign_order';

    public function getName(): string
    {
        return match ($this) {
            self::VTU_PURCHASE => 'VTU Payment',
            self::CAMPAIGN_ORDER => 'Campaign order',
            self::RANK_EARNING => 'Salary Salary',
            self::FUND_TRANSFER => 'Account Topup',
            self::LOCATION_ADDONS => 'Location Proxy',
            self::SUBSCRIPTION_PLAN => 'Package Purchase',
            self::PACKAGE_ADDON => 'Package Add-on',
            self::WITHDRAWAL => 'Cash withdrawal',
            self::TOP_UP_FUND => 'Account Top-up',
            self::REFERRAL_BONUS => 'Referal Reward',
            self::SOCIAL_FOLLOW_REWARD => 'Social follow reward',
            self::APP_REVIEW => 'App Review',
            self::ADS_SHARE => 'Share Ads',
            default => Str::headline($this->value),
        };
    }

    /** Whether this transaction represents a charge verified through a payment gateway. */
    public function isGatewayCharge(): bool
    {
        return in_array($this, [
            self::VTU_PURCHASE,
            self::SUBSCRIPTION_PLAN,
            self::PACKAGE_ADDON,
            self::LOCATION_ADDONS,
            self::TOP_UP_FUND,
        ], true);
    }

    /** @return array<int, string> */
    public static function gatewayChargeValues(): array
    {
        return array_values(array_map(
            static fn (self $type): string => $type->value,
            array_filter(self::cases(), static fn (self $type): bool => $type->isGatewayCharge()),
        ));
    }

    public function isCredit(): bool
    {
        return in_array($this, [self::ADS_SHARE, self::APP_REVIEW, self::REFERRAL_BONUS, self::SOCIAL_FOLLOW_REWARD, self::RANK_EARNING], true);
    }

    public function icon(): string
    {
        return match ($this) {
            self::APP_REVIEW => 'ri-phone-line',
            self::ADS_SHARE => 'ri-advertisement-line',
            self::REFERRAL_BONUS => 'ri-user-add-line',
            self::SOCIAL_FOLLOW_REWARD => 'ri-user-follow-line',
            self::TOP_UP_FUND => 'ri-trophy-line',
            self::WITHDRAWAL => 'ri-arrow-right-up-line',
            self::SUBSCRIPTION_PLAN => 'ri-shopping-bag-3-line',
            self::PACKAGE_ADDON => 'ri-add-circle-line',
            self::LOCATION_ADDONS => 'ri-add-circle-line',
            self::FUND_TRANSFER => 'ri-scales-3-line',
            self::RANK_EARNING => 'ri-exchange-funds-line',
            self::VTU_PURCHASE => 'ri-shopping-bag-line',
            self::CAMPAIGN_ORDER => 'ri-megaphone-line',
            default => 'ri-exchange-line',
        };
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
