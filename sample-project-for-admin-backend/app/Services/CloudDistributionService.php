<?php

namespace App\Services;

use App\Enums\TransactionType;
use App\Events\CloudDistributionFired;
use App\Models\CloudBonusClaim;
use App\Models\CloudDistribution;
use App\Models\CloudNumber;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CloudDistributionService
{
    public function distribute(CloudNumber $number, string $type, float $amount, ?string $note = null, ?string $adminId = null): CloudDistribution
    {
        $type = $type === CloudDistribution::TYPE_SHAREHOLDER ? CloudDistribution::TYPE_SHAREHOLDER : CloudDistribution::TYPE_PROMOTER;

        $distribution = CloudDistribution::create([
            'cloud_number_id' => $number->id,
            'type' => $type,
            'amount' => $amount,
            'sender_name' => $number->brand ?: $number->name,
            'avatar' => $number->avatar,
            'note' => $note ?: null,
            'created_by' => $adminId,
            'recipients_count' => $number->users()->count(),
        ]);

        try {
            CloudDistributionFired::dispatch();
        } catch (\Throwable $e) {
            report($e);
        }

        return $distribution;
    }

    public function claimableFor(User $user): Collection
    {
        return $this->claimableQuery($user)->latest()->get();
    }

    public function claimableCount(User $user): int
    {
        return $this->claimableQuery($user)->count();
    }

    public function claimableTotal(User $user): float
    {
        return (float) $this->claimableFor($user)->sum('amount');
    }

    protected function claimableQuery(User $user): Builder
    {
        return CloudDistribution::query()
            ->whereExists(fn ($q) => $q->from('cloud_user_number as cun')
                ->whereColumn('cun.cloud_number_id', 'cloud_distributions.cloud_number_id')
                ->where('cun.user_id', $user->getKey())
                ->whereColumn('cun.created_at', '<=', 'cloud_distributions.created_at'))
            ->whereNotExists(fn ($q) => $q->from('cloud_bonus_claims as cbc')
                ->whereColumn('cbc.cloud_distribution_id', 'cloud_distributions.id')
                ->where('cbc.user_id', $user->getKey()));
    }

    public function claim(User $user, CloudDistribution $distribution): float
    {
        return (float) DB::transaction(function () use ($user, $distribution): float {

            $saved = DB::table('cloud_user_number')
                ->where('cloud_number_id', $distribution->cloud_number_id)
                ->where('user_id', $user->id)
                ->where('created_at', '<=', $distribution->created_at)
                ->exists();

            if (! $saved) {
                return 0.0;
            }

            $already = CloudBonusClaim::query()
                ->where('cloud_distribution_id', $distribution->id)
                ->where('user_id', $user->id)
                ->lockForUpdate()
                ->exists();

            if ($already) {
                return 0.0;
            }

            $amount = (float) $distribution->amount;

            $lockedUser = User::query()->whereKey($user->id)->lockForUpdate()->first();

            if ($distribution->is_shareholder) {
                $lockedUser->creditEarnings('shareholder_balance', $amount);
                $txnType = TransactionType::CALL_EARNING;
                $wallet = 'shareholder';
            } else {
                $lockedUser->creditEarnings('cloud_call_balance', $amount);
                $txnType = TransactionType::CLOUD_EARNING;
                $wallet = 'cloud_call';
            }

            CloudBonusClaim::create([
                'user_id' => $lockedUser->id,
                'cloud_distribution_id' => $distribution->id,
                'cloud_number_id' => $distribution->cloud_number_id,
                'type' => $distribution->type,
                'amount' => $amount,
                'sender_name' => $distribution->sender_name,
                'avatar' => $distribution->avatar,
                'claimed_at' => now(),
            ]);

            Transaction::create([
                'user_id' => $lockedUser->id,
                'type' => $txnType,
                'direction' => 'credit',
                'amount' => $amount,
                'wallet' => $wallet,
                'status' => 'completed',
                'reference' => 'CBNS-'.strtoupper(Str::random(10)),
                'description' => ucfirst($distribution->type).' bonus · '.$distribution->sender_name,
                'meta' => [
                    'cloud_distribution_id' => $distribution->id,
                    'cloud_number_id' => $distribution->cloud_number_id,
                    'type' => $distribution->type,
                ],
            ]);

            return $amount;
        });
    }
}
