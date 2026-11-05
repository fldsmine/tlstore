<?php

namespace App\Enums;

enum WalletType: string
{
    case Review = 'review';
    case Share = 'share';
    case Referral = 'referral';
    case Bonus = 'bonus';
    case TopupFund = 'topup_fund';
}
