<?php

namespace App\Enums;

enum WalletType: string
{
    /** Legacy mixed app-review wallet; new review rewards use a group-specific wallet. */
    case Review = 'review';
    case ReviewGroupB = 'review_group_b';
    case ReviewGroupC = 'review_group_c';
    case Share = 'share';
    case Referral = 'referral';
    case Bonus = 'bonus';
    case TopupFund = 'topup_fund';

    /** @return list<self> */
    public static function accountWalletTypes(): array
    {
        return [
            self::ReviewGroupB,
            self::ReviewGroupC,
            self::Share,
            self::Referral,
            self::Bonus,
            self::TopupFund,
        ];
    }

    public function label(): string
    {
        return match ($this) {
            self::Review => 'Legacy Review wallet',
            self::ReviewGroupB => 'Group B Review wallet',
            self::ReviewGroupC => 'Group C Review wallet',
            self::Share => 'Share wallet',
            self::Referral => 'Referral wallet',
            self::Bonus => 'Bonus wallet',
            self::TopupFund => 'Top-up fund',
        };
    }

    public static function forReviewStore(?string $store): self
    {
        $store = strtolower(trim($store ?? ''));
        return str_contains($store, 'apple') || str_contains($store, 'ios')
            ? self::ReviewGroupC
            : self::ReviewGroupB;
    }
}
