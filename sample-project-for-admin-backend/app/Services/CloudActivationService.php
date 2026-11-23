<?php

namespace App\Services;

use App\Enums\CouponType;
use App\Enums\TransactionType;
use App\Models\CloudStoragePlan;
use App\Models\CloudStorageTopUp;
use App\Models\Coupon;
use App\Models\Transaction;
use App\Models\User;
use App\Support\CouponCountryPolicy;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CloudActivationService
{
    public function redeem(User $user, string $code, string $field = 'redeemCode', ?string $expectedPlanId = null): CloudStoragePlan
    {
        $coupon = Coupon::query()
            ->where('code', strtoupper(trim($code)))
            ->whereIn('type', [CouponType::CLOUD->value, CouponType::CLOUD_RENEWAL->value])
            ->first();

        if (! $coupon) {
            throw ValidationException::withMessages([$field => 'That token isn\'t valid.']);
        }

        if ($coupon->used_at) {
            throw ValidationException::withMessages([$field => 'That token has already been used.']);
        }

        $plan = CloudStoragePlan::query()->find($coupon->cloud_storage_plan_id);

        if (! $plan || ! $plan->is_active) {
            throw ValidationException::withMessages([$field => 'That token isn\'t valid.']);
        }

        if ($expectedPlanId !== null && $coupon->cloud_storage_plan_id !== $expectedPlanId) {
            throw ValidationException::withMessages([$field => 'That token isn\'t valid for the selected plan.']);
        }

        if (! $user->canCloudCall()) {
            throw ValidationException::withMessages([$field => 'Upgrade to the Royal eSIM before adding storage plans.']);
        }

        $isRenewal = $coupon->type === CouponType::CLOUD_RENEWAL;

        // A renewal code only extends the plan the user is currently on (their active pack, or their
        // latest pack if it has lapsed). Starting a plan they've never had — or switching back to one
        // they left — needs an activation code, not a renewal code. Renewals are also only allowed
        // near the end of the term: at expiry or within one day of it (never stacked far in advance).
        if ($isRenewal) {
            $current = $this->currentPack($user);
            if (! $current || $current->cloud_storage_plan_id !== $plan->id) {
                throw ValidationException::withMessages([$field => 'Renewal codes only renew your current plan — use an activation code to start this plan.']);
            }
            if (! $this->withinRenewalWindow($current)) {
                throw ValidationException::withMessages([$field => 'You can renew only within a day of expiry. Please try again closer to your expiry date.']);
            }
        }

        CouponCountryPolicy::assertUsable($user, $coupon, $field);

        return DB::transaction(function () use ($coupon, $plan, $user, $field, $isRenewal): CloudStoragePlan {

            if (! $coupon->claim($user)) {
                throw ValidationException::withMessages([$field => 'That token has already been used.']);
            }

            $days = (int) $plan->duration_days;

            if ($isRenewal) {
                // Extend the current pack — stack onto remaining time; if lapsed, start from now.
                $pack = $this->currentPack($user);
                $base = ($pack->expires_at && $pack->expires_at->isFuture()) ? $pack->expires_at : now();

                $pack->update([
                    'coupon_id' => $coupon->id,
                    'extra_contacts' => (int) $plan->extra_contacts,
                    'extra_sms' => (int) $plan->extra_sms,
                    'expires_at' => $days > 0 ? $base->copy()->addDays($days) : null,
                ]);
            } else {
                // Activation (incl. changing plan): one plan at a time — expire any current packs,
                // start fresh. Shared with direct (card) purchase via grantPack().
                $this->grantPack($user, $plan, $coupon);
            }

            app(CloudLineService::class)->ensureLine($user);

            return $plan;
        });
    }

    /**
     * Grant a plan as a fresh pack: any current pack is expired and a new one starts now (one plan at
     * a time). The single implementation shared by coupon redemption and direct (card) purchase, so
     * both channels create the exact same entitlement. `$coupon` is null when the plan was paid for
     * directly; a renewal is handled by redeem() (it extends the current pack instead).
     */
    public function grantPack(User $user, CloudStoragePlan $plan, ?Coupon $coupon = null): CloudStorageTopUp
    {
        return DB::transaction(function () use ($user, $plan, $coupon): CloudStorageTopUp {
            $days = (int) $plan->duration_days;

            // One plan at a time — expire any current packs, start fresh.
            $user->cloudStorageTopUps()
                ->where(fn ($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()))
                ->update(['expires_at' => now()]);

            $topUp = CloudStorageTopUp::create([
                'user_id' => $user->id,
                'cloud_storage_plan_id' => $plan->id,
                'coupon_id' => $coupon?->id,
                'extra_contacts' => (int) $plan->extra_contacts,
                'extra_sms' => (int) $plan->extra_sms,
                'expires_at' => $days > 0 ? now()->addDays($days) : null,
            ]);

            // Connect Bonus is paid when a plan is bought/activated, not on renewals.
            $this->payStorageReferralBonus($user, $plan);

            app(CloudLineService::class)->ensureLine($user);

            return $topUp;
        });
    }

    /**
     * Activate storage using a STORAGE_ACTIVATION coupon.
     * This binds the coupon to the user but does NOT mark it as used,
     * allowing the same code to be used later for withdrawal authorization.
     */
    public function activateStorage(User $user, string $code, string $field = 'code'): CloudStoragePlan
    {
        // Use HMAC hash for secure lookup
        $codeHash = Coupon::hashCode($code);

        $coupon = Coupon::query()
            ->where('code_hash', $codeHash)
            ->where('type', CouponType::STORAGE_ACTIVATION->value)
            ->first();

        if (!$coupon) {
            throw ValidationException::withMessages([$field => 'That code is not valid.']);
        }

        // Check if already bound to a user
        if ($coupon->user_id !== null) {
            throw ValidationException::withMessages([$field => 'That code has already been activated.']);
        }

        // Check if expired
        if ($coupon->expires_at && $coupon->expires_at->isPast()) {
            throw ValidationException::withMessages([$field => 'That code has expired.']);
        }

        $plan = CloudStoragePlan::query()->find($coupon->cloud_storage_plan_id);

        if (!$plan || !$plan->is_active) {
            throw ValidationException::withMessages([$field => 'The storage plan is not active, code is invalid.']);
        }

        // Validate user has a package
        if (!$user->package_id) {
            throw ValidationException::withMessages([$field => 'Activate a package first.']);
        }

        // Validate user's tier matches the plan's tier
        if ($plan->tier_level !== null && (int) $user->package->tier_level !== (int) $plan->tier_level) {
            throw ValidationException::withMessages([$field => 'This code is not valid for your package tier.']);
        }

        CouponCountryPolicy::assertUsable($user, $coupon, $field);

        return DB::transaction(function () use ($coupon, $plan, $user, $field): CloudStoragePlan {
            // Lock coupon for update to prevent race conditions
            $lockedCoupon = Coupon::whereKey($coupon->getKey())->lockForUpdate()->firstOrFail();

            // Re-check user_id after lock (explicit re-check as per implementation note)
            if ($lockedCoupon->user_id !== null) {
                throw ValidationException::withMessages([$field => 'That code has already been activated.']);
            }

            // Bind coupon to user (but do NOT mark as used)
            $lockedCoupon->update(['user_id' => $user->id]);

            $days = (int) $plan->duration_days;

            // Expire any current active packs for this user (one at a time)
            $user->cloudStorageTopUps()
                ->where(fn ($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()))
                ->update(['expires_at' => now()]);

            // Create new storage top-up
            CloudStorageTopUp::create([
                'user_id' => $user->id,
                'cloud_storage_plan_id' => $plan->id,
                'coupon_id' => $coupon->id,
                'extra_contacts' => (int) $plan->extra_contacts,
                'extra_sms' => (int) $plan->extra_sms,
                'expires_at' => $days > 0 ? now()->addDays($days) : null,
            ]);

            // Connect Bonus is paid when a plan is activated — same as a purchased pack.
            $this->payStorageReferralBonus($user, $plan);

            app(CloudLineService::class)->ensureLine($user);

            return $plan;
        });
    }

    /** The plan the user is currently on: their active pack, or their latest pack if it has lapsed. */
    public function currentPack(User $user): ?CloudStorageTopUp
    {
        return $user->cloudStorageTopUps()
            ->where(fn ($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()))
            ->latest('created_at')->first()
            ?? $user->cloudStorageTopUps()->latest('created_at')->first();
    }

    /**
     * Renewals open only at the tail of the term — when the pack has expired or is within one day of
     * expiring. Lifetime packs (no expiry) never enter the window; they are changed, not renewed.
     */
    public function withinRenewalWindow(?CloudStorageTopUp $pack): bool
    {
        return $pack !== null
            && $pack->expires_at !== null
            && $pack->expires_at->lessThanOrEqualTo(now()->addDay());
    }

    private function payStorageReferralBonus(User $buyer, CloudStoragePlan $plan): void
    {
        $amount = (float) $plan->referral_bonus;

        if ($amount <= 0) {
            return;
        }

        $buyer->loadMissing('referrer');
        $upline = $buyer->referrer;

        if ($upline === null || $upline->package_id === null) {
            return;
        }

        $upline->creditEarnings('referral_bonus_balance', $amount);

        Transaction::create([
            'user_id' => $upline->id,
            'type' => TransactionType::REFERRAL_BONUS,
            'direction' => 'credit',
            'amount' => $amount,
            'wallet' => 'referral_bonus',
            'status' => 'completed',
            'reference' => 'REF-'.strtoupper(Str::random(10)),
            'description' => 'Storage Connect Bonus from '.$buyer->username,
            'meta' => [
                'level' => 'storage',
                'referee_id' => $buyer->id,
                'cloud_storage_plan_id' => $plan->id,
            ],
        ]);
    }
}
