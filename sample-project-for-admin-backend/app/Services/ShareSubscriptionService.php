<?php

namespace App\Services;

use App\Models\CallType;
use App\Models\Package;
use App\Models\ShareSubscription;
use App\Models\User;
use App\Support\RegionPricing;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Manages one non-stacking share entitlement at a time. An entitlement can come from an
 * eSIM Package or from a standalone CallType share product; the latter never activates eSIM.
 */
class ShareSubscriptionService
{
    /** Convert the configured monthly quota into the term's prorated total allocation. */
    private function termAllocation(float $monthlyMinutes, int $termDays): float
    {
        return app(ShareQuotaService::class)->termAllocation($monthlyMinutes, $termDays);
    }

    public function packageIsConfigured(?Package $package): bool
    {
        return $package !== null
            && (float) $package->monthly_share_minutes > 0
            && (int) $package->share_term_days > 0
            && (float) $package->max_daily_shareholder_earning > 0;
    }

    public function callTypeIsConfigured(?CallType $callType): bool
    {
        return $callType !== null
            && (bool) $callType->is_active
            && (float) $callType->monthly_share_minutes > 0
            && (int) $callType->share_term_days > 0
            && (float) $callType->max_daily_shareholder_earning > 0;
    }

    public function callTypeIsDirectlyPurchasable(?CallType $callType, ?string $region = null): bool
    {
        if ($callType === null || ! $this->callTypeIsConfigured($callType)) {
            return false;
        }

        return RegionPricing::customer($callType, $region ?? (string) ($callType->region ?: 'nigeria')) > 0;
    }

    public function callTypeIsCouponPurchasable(?CallType $callType, ?string $region = null): bool
    {
        if ($callType === null || ! $this->callTypeIsConfigured($callType)) {
            return false;
        }

        $region ??= (string) ($callType->region ?: 'nigeria');

        return RegionPricing::customer($callType, $region) > 0
            && RegionPricing::agent($callType, $region) > 0;
    }

    /** The active entitlement is independent of the user's optional eSIM package. */
    public function current(User $user, bool $lock = false): ?ShareSubscription
    {
        $query = ShareSubscription::query()
            ->where('user_id', $user->id)
            ->current()
            ->latest('starts_at');

        if ($lock) {
            $query->lockForUpdate();
        }

        return $query->first();
    }

    /** True when a held eSIM package can start or renew its share term right now. */
    public function canRenew(User $user, Package $package): bool
    {
        return $user->package_id === $package->id
            && $this->packageIsConfigured($package)
            && $this->current($user) === null;
    }

    /** A standalone share product can be activated only when no share term is currently active. */
    public function canPurchaseCallType(User $user, CallType $callType): bool
    {
        return $this->callTypeIsConfigured($callType)
            && $this->current($user) === null;
    }

    /** Start a fresh package share term after activation or tier upgrade. */
    public function startNewTerm(User $user, Package $package): ?ShareSubscription
    {
        return DB::transaction(function () use ($user, $package): ?ShareSubscription {
            $lockedUser = User::query()->whereKey($user->id)->lockForUpdate()->first();

            if (! $lockedUser) {
                return null;
            }

            // Activating or upgrading eSIM must not revoke a separately purchased, active share term.
            $standaloneTerm = ShareSubscription::query()
                ->where('user_id', $lockedUser->id)
                ->whereNotNull('call_type_id')
                ->current()
                ->lockForUpdate()
                ->first();

            if ($standaloneTerm !== null) {
                return null;
            }

            $now = now();
            $previousTerms = ShareSubscription::query()
                ->where('user_id', $lockedUser->id)
                ->whereNotNull('package_id')
                ->where('status', 'active')
                ->lockForUpdate()
                ->get();

            foreach ($previousTerms as $previous) {
                $previous->update([
                    'status' => $previous->expires_at?->isPast() ? 'expired' : 'superseded',
                ]);
            }

            if (! $this->packageIsConfigured($package)) {
                return null;
            }

            return ShareSubscription::query()->create([
                'user_id' => $lockedUser->id,
                'package_id' => $package->id,
                'call_type_id' => null,
                'region' => RegionPricing::forUser($lockedUser),
                'allocated_minutes' => $this->termAllocation((float) $package->monthly_share_minutes, (int) $package->share_term_days),
                'daily_earning_cap' => (float) $package->max_daily_shareholder_earning,
                'starts_at' => $now,
                'expires_at' => $now->copy()->addDays((int) $package->share_term_days),
                'status' => 'active',
            ]);
        });
    }

    /** Start a share-only term for exactly one CallType, without touching eSIM state. */
    public function startForCallType(User $user, CallType $callType, ?string $region = null): ShareSubscription
    {
        return DB::transaction(function () use ($user, $callType, $region): ShareSubscription {
            $lockedUser = User::query()->whereKey($user->id)->lockForUpdate()->first();

            if (! $lockedUser) {
                throw ValidationException::withMessages([
                    'call_type_id' => 'This account could not be found.',
                ]);
            }

            $lockedCallType = CallType::query()->whereKey($callType->id)->lockForUpdate()->first();
            if ($lockedCallType === null || ! $this->callTypeIsConfigured($lockedCallType)) {
                throw ValidationException::withMessages([
                    'call_type_id' => 'This share product is not available right now.',
                ]);
            }

            if ($this->current($lockedUser, lock: true) !== null) {
                throw ValidationException::withMessages([
                    'call_type_id' => 'An active share term is already in place. Share products do not stack.',
                ]);
            }

            ShareSubscription::query()
                ->where('user_id', $lockedUser->id)
                ->where('status', 'active')
                ->where('expires_at', '<=', now())
                ->update(['status' => 'expired']);

            $now = now();

            return ShareSubscription::query()->create([
                'user_id' => $lockedUser->id,
                'package_id' => null,
                'call_type_id' => $lockedCallType->id,
                'region' => $region ?: RegionPricing::forUser($lockedUser),
                'allocated_minutes' => $this->termAllocation((float) $lockedCallType->monthly_share_minutes, (int) $lockedCallType->share_term_days),
                'daily_earning_cap' => (float) $lockedCallType->max_daily_shareholder_earning,
                'starts_at' => $now,
                'expires_at' => $now->copy()->addDays((int) $lockedCallType->share_term_days),
                'status' => 'active',
            ]);
        });
    }

    /** Start/renew only the share entitlement; do not re-activate eSIM features or pay referral bonuses. */
    public function renew(User $user, Package $package): ShareSubscription
    {
        return DB::transaction(function () use ($user, $package): ShareSubscription {
            $lockedUser = User::query()->whereKey($user->id)->lockForUpdate()->first();

            if (! $lockedUser || $lockedUser->package_id !== $package->id) {
                throw ValidationException::withMessages([
                    'package_id' => 'You can only renew the share plan attached to your current package.',
                ]);
            }

            if (! $this->packageIsConfigured($package)) {
                throw ValidationException::withMessages([
                    'package_id' => 'This package does not have a configured share quota and daily earning cap yet.',
                ]);
            }

            if ($this->current($lockedUser, lock: true) !== null) {
                throw ValidationException::withMessages([
                    'package_id' => 'Your share plan is still active. It cannot be renewed early.',
                ]);
            }

            return $this->startNewTerm($lockedUser, $package)
                ?? throw ValidationException::withMessages([
                    'package_id' => 'This package does not have a configured share entitlement.',
                ]);
        });
    }
}
