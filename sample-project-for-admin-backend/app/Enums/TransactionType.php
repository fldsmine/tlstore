<?php

namespace App\Enums;

use Illuminate\Support\Str;

enum TransactionType: string
{
    case CALL_EARNING = 'call_earning';
    case AD_REVENUE = 'ad_revenue';
    case REFERRAL_BONUS = 'referral_bonus';
    case REWARD = 'reward';
    case WITHDRAWAL = 'withdrawal';
    case PACKAGE_ACTIVATION = 'package_activation';
    case MINUTES_TOPUP = 'minutes_topup';
    case ADJUSTMENT = 'adjustment';
    case AGENT_TRANSFER = 'agent_transfer';
    case CLOUD_EARNING = 'cloud_earning';
    case VTU_PURCHASE = 'vtu_purchase';
    case BILLS_TOPUP = 'bills_topup';
    case WEBSITE_RENEWAL = 'website_renewal';
    case STORAGE_PURCHASE = 'storage_purchase';
    case PACKAGE_PURCHASE = 'package_purchase';
    case SHARE_ACCESS_PURCHASE = 'share_access_purchase';
    case SPECIAL_COUPON_PURCHASE = 'special_coupon_purchase';
    case LOAN_APPLICATION_FEE = 'loan_application_fee';
    case LOAN_GUARANTOR_FORM_FEE = 'loan_guarantor_form_fee';

    public function getName(): string
    {
        return match ($this) {
            self::VTU_PURCHASE => 'Bill Payment',
            self::BILLS_TOPUP => 'Wallet Top-up',
            self::WEBSITE_RENEWAL => 'Website Renewal',
            self::STORAGE_PURCHASE => 'Storage Purchase',
            self::PACKAGE_PURCHASE => 'Package Purchase',
            self::SHARE_ACCESS_PURCHASE => 'Share Access',
            self::MINUTES_TOPUP => 'Share Minutes Top-up',
            self::SPECIAL_COUPON_PURCHASE => 'Revenue Code Purchase',
            self::LOAN_APPLICATION_FEE => 'Loan Application Fee',
            self::LOAN_GUARANTOR_FORM_FEE => 'Loan Guarantor Form Fee',
            self::CALL_EARNING => 'Nextel Shares',
            self::REFERRAL_BONUS => 'Connect Bonus',
            self::REWARD => 'Target Reward',
            self::CLOUD_EARNING => 'N-Cloud Earning',
            default => Str::headline($this->value),
        };
    }

    /** Whether this transaction represents a charge verified through a payment gateway. */
    public function isGatewayCharge(): bool
    {
        return in_array($this, [
            self::BILLS_TOPUP,
            self::WEBSITE_RENEWAL,
            self::STORAGE_PURCHASE,
            self::PACKAGE_PURCHASE,
            self::SHARE_ACCESS_PURCHASE,
            self::MINUTES_TOPUP,
            self::SPECIAL_COUPON_PURCHASE,
            self::LOAN_APPLICATION_FEE,
            self::LOAN_GUARANTOR_FORM_FEE,
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
        return in_array($this, [self::CALL_EARNING, self::AD_REVENUE, self::REFERRAL_BONUS, self::REWARD, self::CLOUD_EARNING], true);
    }

    public function icon(): string
    {
        return match ($this) {
            self::CALL_EARNING => 'ri-phone-line',
            self::AD_REVENUE => 'ri-advertisement-line',
            self::REFERRAL_BONUS => 'ri-user-add-line',
            self::REWARD => 'ri-trophy-line',
            self::WITHDRAWAL => 'ri-arrow-right-up-line',
            self::PACKAGE_ACTIVATION => 'ri-shopping-bag-3-line',
            self::MINUTES_TOPUP => 'ri-add-circle-line',
            self::ADJUSTMENT => 'ri-scales-3-line',
            self::AGENT_TRANSFER => 'ri-exchange-funds-line',
            self::CLOUD_EARNING => 'ri-cloud-line',
            self::VTU_PURCHASE => 'ri-shopping-bag-line',
            self::BILLS_TOPUP => 'ri-wallet-3-line',
            self::WEBSITE_RENEWAL => 'ri-global-line',
            self::STORAGE_PURCHASE => 'ri-database-2-line',
            self::PACKAGE_PURCHASE => 'ri-vip-crown-2-line',
            self::SHARE_ACCESS_PURCHASE => 'ri-pie-chart-line',
            self::SPECIAL_COUPON_PURCHASE => 'ri-gift-2-line',
            self::LOAN_APPLICATION_FEE, self::LOAN_GUARANTOR_FORM_FEE => 'ri-bank-card-line',
            default => 'ri-exchange-line',
        };
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
