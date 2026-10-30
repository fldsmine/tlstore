<?php

namespace App\Services;

use App\Enums\CouponType;
use App\Models\AdditionalMinutePlan;
use App\Models\Coupon;
use App\Models\CallType;
use App\Models\MinuteCredit;
use App\Models\MinuteTopUp;
use App\Models\Package;
use App\Models\User;
use App\Support\CouponCountryPolicy;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Vendor-token (coupon) redemption — the single source of truth shared by the
 * web (Livewire) and API surfaces. Extracted from the ActivateAccount,
 * Activate, Plans, ResetPin and Paylater components.
 */
class TokenRedemptionService
{
    public function __construct(
        private PackageActivationService $activation,
        private PayLaterService $payLater,
        private ShareSubscriptionService $shareSubscriptions,
    ) {}

    /** First-time account activation via an ACTIVATION token. */
    public function activate(User $user, string $packageId, string $token, string $packageField = 'package_id', string $tokenField = 'token'): Package
    {
        if ($user->package_id) {
            throw ValidationException::withMessages([$tokenField => 'This account is already activated.']);
        }

        $package = Package::query()->active()->find($packageId);

        if (! $package) {
            throw ValidationException::withMessages([$packageField => 'That package is no longer available. Please choose another.']);
        }

        $coupon = $this->findCoupon($token, CouponType::ACTIVATION, $tokenField, 'That activation token is not valid.', 'That activation token has already been used.');

        if ($coupon->package_id !== $package->id) {
            throw ValidationException::withMessages([$tokenField => 'This token activates the '.($coupon->package?->name ?? 'a different package').', not the '.$package->name.'.']);
        }

        CouponCountryPolicy::assertUsable($user, $coupon, $tokenField);

        DB::transaction(function () use ($coupon, $package, $user, $tokenField): void {
            if (! $coupon->claim($user)) {
                throw ValidationException::withMessages([$tokenField => 'That activation token has already been used.']);
            }

            $this->activation->activate($user, $package);
        });

        return $package;
    }

    /** Activate a standalone, one-CallType share term with its own coupon type. */
    public function redeemShareAccess(User $user, string $callTypeId, string $token, string $callTypeField = 'callTypeId', string $tokenField = 'token'): \App\Models\ShareSubscription
    {
        $callType = CallType::query()->active()->find($callTypeId);

        if (! $callType || ! $this->shareSubscriptions->callTypeIsConfigured($callType)) {
            throw ValidationException::withMessages([$callTypeField => 'That share product is not available right now.']);
        }

        $coupon = $this->findCoupon($token, CouponType::SHARE_ACTIVATION, $tokenField, 'That share activation token is not valid.', 'That share activation token has already been used.');

        if ($coupon->call_type_id !== $callType->id) {
            throw ValidationException::withMessages([$tokenField => 'This token activates '.($coupon->callType?->name ?? 'a different share product').', not '.$callType->name.'.']);
        }

        CouponCountryPolicy::assertUsable($user, $coupon, $tokenField);

        return DB::transaction(function () use ($coupon, $callType, $user, $callTypeField, $tokenField): \App\Models\ShareSubscription {
            if (! $coupon->claim($user)) {
                throw ValidationException::withMessages([$tokenField => 'That share activation token has already been used.']);
            }

            try {
                return $this->shareSubscriptions->startForCallType($user, $callType, $coupon->region);
            } catch (ValidationException $exception) {
                $messages = $exception->errors();
                if (isset($messages['call_type_id'])) {
                    throw ValidationException::withMessages([$callTypeField => $messages['call_type_id']]);
                }

                throw $exception;
            }
        });
    }

    /** Tier upgrade via an UPGRADE token. */
    public function upgrade(User $user, string $packageId, string $token, string $packageField = 'package_id', string $tokenField = 'token'): Package
    {
        $user->loadMissing('package');

        if (! $user->package) {
            throw ValidationException::withMessages([$packageField => 'Activate a package before upgrading.']);
        }

        $package = Package::query()->active()->find($packageId);

        if (! $package) {
            throw ValidationException::withMessages([$packageField => 'That package is no longer available. Please choose another.']);
        }

        if ((int) $package->tier_level <= (int) $user->package->tier_level) {
            throw ValidationException::withMessages([$packageField => 'Pick a package higher than your current '.$user->package->name.' plan.']);
        }

        $coupon = $this->findCoupon($token, CouponType::UPGRADE, $tokenField, 'That upgrade token is not valid.', 'That upgrade token has already been used.');


        if ($coupon->package_id !== $package->id) {
            throw ValidationException::withMessages([$tokenField => 'This token is for the '.($coupon->package?->name ?? 'a different package').', not the '.$package->name.'.']);
        }

        CouponCountryPolicy::assertUsable($user, $coupon, $tokenField);

        DB::transaction(function () use ($coupon, $package, $user, $tokenField): void {
            if (! $coupon->claim($user)) {
                throw ValidationException::withMessages([$tokenField => 'That upgrade token has already been used.']);
            }

            $this->activation->upgrade($user, $package);
        });

        return $package;
    }

    /** Extra-minutes ("shares") redemption via an EXTRA_MINUTES token. */
    public function redeemMinutes(User $user, string $planId, string $token, string $planField = 'plan_id', string $tokenField = 'token'): MinuteTopUp
    {
        if (! (bool) config('settings.minutes.enabled', false)) {
            throw ValidationException::withMessages([$planField => 'Share purchases are currently unavailable.']);
        }

        $plan = AdditionalMinutePlan::query()->find($planId);

        if (! $plan) {
            throw ValidationException::withMessages([$planField => 'Choose a valid minutes plan.']);
        }

        $user->loadMissing('package');
        $shareOnly = $user->package_id === null;
        if ($shareOnly && ((float) $plan->share_minutes <= 0
            || (float) $plan->daily_call_minutes > 0
            || (int) $plan->call_id_bonus > 0)) {
            throw ValidationException::withMessages([$planField => 'Without an eSIM package, only a share-only top-up can be redeemed.']);
        }

        $coupon = $this->findCoupon($token, CouponType::EXTRA_MINUTES, $tokenField, 'That minutes token is not valid.', 'That minutes token has already been used.');

        if ($coupon->additional_minute_plan_id !== $plan->id) {
            throw ValidationException::withMessages([$tokenField => 'This token is for the '.($coupon->additionalMinutePlan?->name ?? 'a different plan').', not the '.$plan->name.'.']);
        }

        CouponCountryPolicy::assertUsable($user, $coupon, $tokenField);

        return DB::transaction(function () use ($user, $plan, $coupon, $tokenField, $shareOnly): MinuteTopUp {
            if (! $coupon->claim($user)) {
                throw ValidationException::withMessages([$tokenField => 'That minutes token has already been used.']);
            }

            return $this->activation->addMinutes($user, $plan, $coupon, shareOnly: $shareOnly);
        });
    }

    /** Reset the transaction PIN using a vendor PIN_RESET token. */
    public function resetPin(User $user, string $token, string $newPin, string $tokenField = 'token'): void
    {
        $coupon = $this->findCoupon($token, CouponType::PIN_RESET, $tokenField, 'That token is not a valid PIN-change token.', 'That token has already been used.');

        CouponCountryPolicy::assertUsable($user, $coupon, $tokenField);

        DB::transaction(function () use ($coupon, $user, $newPin, $tokenField): void {
            if (! $coupon->claim($user)) {
                throw ValidationException::withMessages([$tokenField => 'That token has already been used.']);
            }

            $user->update(['transaction_pin' => $newPin]); // hashed via cast
        });
    }

    /** Repay an outstanding Pay Later credit with an EXTRA_MINUTES token. */
    public function repayPayLater(User $user, MinuteCredit $credit, string $token, string $tokenField = 'token'): void
    {
        $coupon = Coupon::query()
            ->where('code', strtoupper(trim($token)))
            ->where('type', CouponType::EXTRA_MINUTES->value)
            ->first();

        if (! $coupon || $coupon->used_at) {
            throw ValidationException::withMessages([$tokenField => 'That minutes token is not valid or already used.']);
        }

        CouponCountryPolicy::assertUsable($user, $coupon, $tokenField);

        DB::transaction(function () use ($credit, $coupon, $user, $tokenField): void {
            if (! $coupon->claim($user)) {
                throw ValidationException::withMessages([$tokenField => 'That minutes token has already been used.']);
            }

            $this->payLater->repay($credit);
        });
    }

    private function findCoupon(string $token, CouponType $type, string $field, string $invalidMessage, string $usedMessage): Coupon
    {
        $coupon = Coupon::query()
            ->where('code', strtoupper(trim($token)))
            ->where('type', $type->value)
            ->first();

        if (! $coupon) {
            throw ValidationException::withMessages([$field => $invalidMessage]);
        }

        if ($coupon->used_at) {
            throw ValidationException::withMessages([$field => $usedMessage]);
        }

        return $coupon;
    }
}
