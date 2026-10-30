<?php

namespace App\Services;

use App\Enums\TransactionType;
use App\Models\AdditionalMinutePlan;
use App\Models\AllocatedNumber;
use App\Models\Coupon;
use App\Models\MinuteTopUp;
use App\Models\Package;
use App\Models\Transaction;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class PackageActivationService
{
    public function __construct(private ShareSubscriptionService $shareSubscriptions) {}

    public function activate(User $user, Package $package): void
    {
        // Never re-activate an already-activated account — that would overwrite the package,
        // re-allocate Call IDs, and re-pay the referral chain. Tier upgrades go through upgrade().
        if ($user->package_id !== null) {
            throw ValidationException::withMessages(['token' => 'This account is already activated.']);
        }

        DB::transaction(function () use ($user, $package): void {
            $lockedUser = User::query()->whereKey($user->id)->lockForUpdate()->firstOrFail();

            if ($lockedUser->package_id !== null) {
                throw ValidationException::withMessages(['token' => 'This account is already activated.']);
            }

            $lockedUser->forceFill([
                'package_id' => $package->id,
                // The personalized-website validity window starts at first activation; renewals extend it.
                'website_expires_at' => $this->initialWebsiteExpiry(),
            ])->save();

            $this->allocateNumbers($lockedUser, (int) $package->call_id_limit, 'package', null, nextelFirst: true);
            $this->shareSubscriptions->startNewTerm($lockedUser, $package);
            $this->payReferralChain($lockedUser, $package);
        });

    }

    /**
     * Initial personalized-website expiry for a first activation: the later of the configured grace
     * floor (a temporary Sept-30 charging cut-over) and activation date + duration_days.
     */
    private function initialWebsiteExpiry(): CarbonInterface
    {
        $expiresAt = now()->addDays((int) config('settings.website.duration_days', 30));

        $floor = config('settings.website.expiry_floor');
        if (filled($floor)) {
            $floorAt = Carbon::parse($floor)->endOfDay();
            if ($floorAt->greaterThan($expiresAt)) {
                return $floorAt;
            }
        }

        return $expiresAt;
    }

    private function payReferralChain(User $user, Package $package, string $context = 'activation'): void
    {
        $user->loadMissing(['referrer.package', 'referrer.referrer.package']);

        $isUpgrade = $context === 'upgrade';
        $verb = $isUpgrade ? 'upgraded to' : 'activated';
        $directAmount = $isUpgrade ? (float) $package->upgrade_direct_referral_bonus : (float) $package->direct_referral_bonus;
        $indirectAmount = $isUpgrade ? (float) $package->upgrade_indirect_referral_bonus : (float) $package->indirect_referral_bonus;

        $direct = $user->referrer;
        if ($this->isEligible($direct, $package) && $directAmount > 0) {
            $this->payReferralBonus($direct, $user, $package, $directAmount, $isUpgrade ? 'upgrade_direct' : 'direct', $verb);
        }

        $indirect = $direct?->referrer;
        if ($this->isEligible($indirect, $package) && $indirectAmount > 0) {
            $this->payReferralBonus($indirect, $user, $package, $indirectAmount, $isUpgrade ? 'upgrade_indirect' : 'indirect', $verb);
        }
    }

    private function payReferralBonus(User $upline, User $referee, Package $package, float $amount, string $level, string $verb): void
    {
        $upline->creditEarnings('referral_bonus_balance', $amount);

        $referee->loadMissing('referrer');
        $isDirect = ! str_contains($level, 'indirect');
        $description = $isDirect
            ? 'Direct Connect Bonus — '.$referee->username.' '.$verb.' '.$package->name
            : 'Indirect Connect Bonus — '.$referee->username.' '.$verb.' '.$package->name.' (referred by '.($referee->referrer->username ?? 'your network').')';

        Transaction::create([
            'user_id' => $upline->id,
            'type' => TransactionType::REFERRAL_BONUS,
            'direction' => 'credit',
            'amount' => $amount,
            'wallet' => 'referral_bonus',
            'status' => 'completed',
            'reference' => 'REF-'.strtoupper(Str::random(10)),
            'description' => $description,
            'meta' => [
                'level' => $level,
                'referee_id' => $referee->id,
                'package_id' => $package->id,
            ],
        ]);

        app(TargetRewardService::class)->evaluate($upline);
    }

    private function isEligible(?User $upline, Package $activated): bool
    {
        return $upline?->package !== null
            && (int) $upline->package->tier_level >= (int) $activated->tier_level;
    }

    public function upgrade(User $user, Package $newPackage): void
    {
        DB::transaction(function () use ($user, $newPackage): void {
            $lockedUser = User::query()->whereKey($user->id)->lockForUpdate()->firstOrFail();
            $lockedUser->loadMissing('package');

            if ($lockedUser->package === null) {
                throw ValidationException::withMessages(['package_id' => 'Activate a package before upgrading.']);
            }

            if ((int) $newPackage->tier_level <= (int) $lockedUser->package->tier_level) {
                throw ValidationException::withMessages(['package_id' => 'Choose a package higher than your current plan.']);
            }

            $currentlyHeld = $lockedUser->allocatedNumbers()->where('source', 'package')->count();
            $additional = max(0, (int) $newPackage->call_id_limit - $currentlyHeld);

            $lockedUser->forceFill(['package_id' => $newPackage->id])->save();

            if ($additional > 0) {
                $this->allocateNumbers($lockedUser, $additional, 'package', null);
            }

            // Every upgrade starts a new share term. Old term top-ups remain attached to the
            // superseded subscription and cannot be carried into this allocation.
            $this->shareSubscriptions->startNewTerm($lockedUser, $newPackage);

            if (config('settings.referral.pay_on_upgrade', true)) {
                $this->payReferralChain($lockedUser, $newPackage, 'upgrade');
            }
        });
    }

    public function renewShares(User $user, Package $package): void
    {
        $this->shareSubscriptions->renew($user, $package);
    }

    public function addMinutes(
        User $user,
        AdditionalMinutePlan $plan,
        ?Coupon $coupon = null,
        bool $shareOnly = false,
        ?string $expectedShareSubscriptionId = null,
    ): MinuteTopUp {
        return DB::transaction(function () use ($user, $plan, $coupon, $shareOnly, $expectedShareSubscriptionId): MinuteTopUp {
            $lockedUser = User::query()->whereKey($user->id)->lockForUpdate()->firstOrFail();

            if (! $lockedUser->package_id && ! $shareOnly) {
                throw ValidationException::withMessages(['plan_id' => 'An eSIM package is required for eSIM minute top-ups.']);
            }

            if ($shareOnly && (float) $plan->share_minutes <= 0) {
                throw ValidationException::withMessages(['plan_id' => 'This plan does not include share minutes.']);
            }

            $shareSubscription = $this->shareSubscriptions->current($lockedUser, lock: true);
            if ((float) $plan->share_minutes > 0 && $shareSubscription === null) {
                throw ValidationException::withMessages(['plan_id' => 'An active share plan is required for this share-minute top-up.']);
            }

            if ($expectedShareSubscriptionId !== null && $shareSubscription?->id !== $expectedShareSubscriptionId) {
                throw ValidationException::withMessages(['plan_id' => 'The share term changed before this top-up was confirmed. Start a new checkout for the current term.']);
            }

            $expiresAt = now()->addDays((int) $plan->duration_days);
            $shareExpiresAt = null;
            if ((float) $plan->share_minutes > 0 && $shareSubscription !== null) {
                $shareExpiresAt = $shareSubscription->expires_at?->lessThan($expiresAt)
                    ? $shareSubscription->expires_at
                    : $expiresAt;
            }

            $topUp = MinuteTopUp::create([
                'user_id' => $lockedUser->id,
                'share_subscription_id' => $shareSubscription?->id,
                'additional_minute_plan_id' => $plan->id,
                'coupon_id' => $coupon?->id,
                // A share-only purchase cannot grant eSIM minutes or Call IDs.
                'daily_call_minutes' => $shareOnly ? 0 : (float) $plan->daily_call_minutes,
                'share_minutes' => (float) $plan->share_minutes,
                'expires_at' => $expiresAt,
                'share_expires_at' => $shareExpiresAt,
            ]);

            if (! $shareOnly && (int) $plan->call_id_bonus > 0) {
                $this->allocateNumbers($lockedUser, (int) $plan->call_id_bonus, 'additional', $expiresAt, $plan->id, minuteTopUpId: $topUp->id);
            }

            if (! $shareOnly) {
                $lockedUser->loadMissing('referrer.package');
                $direct = $lockedUser->referrer;
                if ($direct?->package !== null && (float) $plan->referral_bonus > 0) {
                    $this->payMinutePlanReferralBonus($direct, $lockedUser, $plan, (float) $plan->referral_bonus);
                }
            }

            return $topUp;
        });
    }

    private function payMinutePlanReferralBonus(User $upline, User $referee, AdditionalMinutePlan $plan, float $amount): void
    {

        $upline->creditEarnings('referral_bonus_balance', $amount);

        Transaction::create([
            'user_id' => $upline->id,
            'type' => TransactionType::REFERRAL_BONUS,
            'direction' => 'credit',
            'amount' => $amount,
            'wallet' => 'referral_bonus',
            'status' => 'completed',
            'reference' => 'REF-'.strtoupper(Str::random(10)),
            'description' => 'Shares Connect Bonus — '.$referee->username.' bought extra shares',
            'meta' => [
                'level' => 'minutes',
                'referee_id' => $referee->id,
                'minute_plan_id' => $plan->id,
            ],
        ]);
    }

    public function allocateNumbers(User $user, int $count, string $source, ?CarbonInterface $expiresAt, ?string $planId = null, bool $nextelFirst = false, ?string $minuteTopUpId = null): void
    {
        if ($count < 1) {
            return;
        }

        $now = now();
        $rows = [];

        for ($i = 0; $i < $count; $i++) {
            $rows[] = [
                'id' => (string) Str::uuid7(),
                'user_id' => $user->id,
                'prefix' => ($nextelFirst && $i === 0) ? AllocatedNumber::NEXTEL_PREFIX : AllocatedNumber::randomPrefix(),
                'suffix' => AllocatedNumber::randomSuffix(),
                'source' => $source,
                'expires_at' => $expiresAt,
                'additional_minute_plan_id' => $planId,
                'minute_top_up_id' => $minuteTopUpId,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        foreach (array_chunk($rows, 500) as $chunk) {
            AllocatedNumber::insert($chunk);
        }
    }

    public function pruneExpiredNumbers(): int
    {
        return AllocatedNumber::whereNotNull('expires_at')
            ->where('expires_at', '<=', now())
            ->delete();
    }
}
