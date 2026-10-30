<?php

namespace App\Services;

use App\Enums\TransactionType;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Support\Str;

class CloudReferralService
{
    public function creditUpline(User $earner, float $earned, array $meta = []): void
    {
        if ($earned <= 0) {
            return;
        }

        $earner->loadMissing('referrer');
        $upline = $earner->referrer;

        if ($upline === null || $upline->package_id === null) {
            return;
        }

        $bonus = self::bonusAmount();
        if ($bonus <= 0) {
            return;
        }

        $alreadyPaid = Transaction::query()
            ->where('user_id', $upline->id)
            ->where('type', TransactionType::REFERRAL_BONUS->value)
            ->where('meta->kind', 'cloud_first_call')
            ->where('meta->referee_id', $earner->id)
            ->exists();

        if ($alreadyPaid) {
            return;
        }

        $lockedUpline = User::query()->whereKey($upline->id)->lockForUpdate()->first();
        $lockedUpline->creditEarnings('cloud_call_balance', $bonus);

        Transaction::create([
            'user_id' => $lockedUpline->id,
            'type' => TransactionType::REFERRAL_BONUS,
            'direction' => 'credit',
            'amount' => $bonus,
            'wallet' => 'cloud_call',
            'status' => 'completed',
            'reference' => 'CREF-'.strtoupper(Str::random(10)),
            'description' => 'Connect 1st Call Bonus — '.$earner->username.'’s first sponsored call',
            'meta' => array_merge([
                'kind' => 'cloud_first_call',
                'level' => 'cloud',
                'referee_id' => $earner->id,
            ], $meta),
        ]);
    }

    public static function bonusAmount(): float
    {
        return round((float) config('settings.cloud.referral_bonus', 0), 6);
    }
}
