<?php

namespace App\Enums;

enum ChargePurpose: string
{
    case RENEWAL = 'renewal';
    case BILL = 'bill';
    case STORAGE = 'storage';
    case PACKAGE = 'package';
    case SPECIAL_COUPON = 'special_coupon';
    case LOAN_APPLICATION_FEE = 'loan_application_fee';

    /** The settings.payment.* key holding the configured provider for this purpose. */
    public function settingKey(): string
    {
        return match ($this) {
            self::RENEWAL => 'renewal_provider',
            self::BILL => 'bill_provider',
            self::STORAGE => 'storage_provider',
            self::PACKAGE => 'package_provider',
            self::SPECIAL_COUPON => 'special_coupon_provider',
            self::LOAN_APPLICATION_FEE => 'loan_application_fee_provider',
        };
    }
}
