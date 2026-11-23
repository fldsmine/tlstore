<?php

namespace App\Services;

use App\Enums\CouponType;
use App\Models\AdditionalMinutePlan;
use App\Models\CloudStoragePlan;
use App\Models\CallType;
use App\Models\Coupon;
use App\Models\CouponBatch;
use App\Models\Package;
use App\Models\User;
use App\Support\RegionPricing;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Vendor token minting — shared by the web (Livewire Agent\Token) and API.
 * Vendors pay from their agent float; each minted token is a Coupon tied
 * to a batch.
 */
class CouponMintingService
{
    /** The mintable plan catalogue with this vendor's regional pricing. */
    public function planOptions(User $user): Collection
    {
        $region = Coupon::regionFor($user);

        $packages = Package::active()->ordered()->get()->map(fn (Package $p): array => [
            'key' => 'package:'.$p->id,
            'group' => 'Activation Packages',
            'label' => $p->name,
            'agent_price' => RegionPricing::agent($p, $region),
            'price' => RegionPricing::customer($p, $region),
        ]);

        $minutes = AdditionalMinutePlan::active()->ordered()->get()->map(fn (AdditionalMinutePlan $p): array => [
            'key' => 'minutes:'.$p->id,
            'group' => 'Extra Minutes',
            'label' => $p->name,
            'agent_price' => RegionPricing::agent($p, $region),
            'price' => RegionPricing::customer($p, $region),
        ]);

        $shareProducts = app(ShareSubscriptionService::class);
        $shares = CallType::query()->active()->ordered()->get()
            ->filter(fn (CallType $callType): bool => $shareProducts->callTypeIsCouponPurchasable($callType, $region))
            ->map(fn (CallType $callType): array => [
                'key' => 'shares:'.$callType->id,
                'group' => 'Share Access',
                'label' => $callType->name,
                'agent_price' => RegionPricing::agent($callType, $region),
                'price' => RegionPricing::customer($callType, $region),
            ]);

        // Cloud storage plans split by tier_level: plans tagged with a package tier are SPECIAL
        // coupons (coupon-only — not directly purchasable); plans without one are regular
        // purchasable storage plans.
        $cloudPlans = CloudStoragePlan::active()->ordered()->get();

        $cloud = $cloudPlans->filter(fn (CloudStoragePlan $p): bool => $p->tier_level === null)->map(fn (CloudStoragePlan $p): array => [
            'key' => 'cloud:'.$p->id,
            'group' => 'Cloud Storage Plans',
            'label' => $p->name,
            'agent_price' => RegionPricing::agent($p, $region),
            'price' => RegionPricing::customer($p, $region),
            'special' => false,
        ]);

        $special = $cloudPlans->filter(fn (CloudStoragePlan $p): bool => $p->tier_level !== null)->map(fn (CloudStoragePlan $p): array => [
            'key' => 'cloud:'.$p->id,
            'group' => 'Special Coupons',
            'label' => $p->name,
            'agent_price' => RegionPricing::agent($p, $region),
            'price' => RegionPricing::customer($p, $region),
            'special' => true,
            'tier_level' => (int) $p->tier_level,
        ]);

        $loanFees = collect();
        $loanService = app(LoanService::class);
        if ((bool) config('settings.loan.enabled', false) && $loanService->applicationFeeMode() === 'fixed') {
            $localFee = $region === Coupon::REGION_NIGERIA
                ? '₦'.number_format($loanService->applicationFeeNgn(), 2)
                : '$'.number_format($loanService->applicationFeeUsd(), 2);
            $loanFees = collect([[
                'key' => 'loan_fee',
                'group' => 'Loan Application Fees',
                'label' => 'Loan Application Fee Coupon — '.$localFee,
                'agent_price' => $loanService->vendorCouponPriceUsd($region),
                'price' => $loanService->customerCouponPriceUsd($region),
                'loan_fee' => true,
            ]]);
        }

        return $packages->concat($minutes)->concat($shares)->concat($cloud)->concat($special)->concat($loanFees)->values();
    }

    /**
     * Mint $quantity tokens for the given plan key, charging the vendor float.
     *
     * @return array{batch: CouponBatch, cost: float}
     */
    public function mint(User $user, string $planKey, int $quantity): array
    {
        if (! $user->is_agent) {
            throw ValidationException::withMessages(['plan_key' => 'Only vendors can generate tokens.']);
        }

        $plan = $this->planOptions($user)->firstWhere('key', $planKey);

        if (! $plan) {
            throw ValidationException::withMessages(['plan_key' => 'Select a valid plan.']);
        }

        $cost = round($plan['agent_price'] * $quantity, 2);

        if ((float) $user->agent_balance < $cost) {
            throw ValidationException::withMessages(['quantity' => 'Insufficient vendor balance for this purchase.']);
        }

        [$kind, $refId] = array_pad(explode(':', $planKey, 2), 2, null);
        $type = match (true) {
            $kind === 'minutes' => CouponType::EXTRA_MINUTES,
            $kind === 'shares' => CouponType::SHARE_ACTIVATION,
            $kind === 'loan_fee' => CouponType::LOAN_FEE,
            $kind === 'cloud' && ($plan['special'] ?? false) => CouponType::STORAGE_ACTIVATION,
            $kind === 'cloud' => CouponType::CLOUD,
            default => CouponType::ACTIVATION,
        };

        // Special coupons expire (settings.token.special_expiry_days); regular codes never do.
        $expiresAt = $type === CouponType::STORAGE_ACTIVATION
            ? now()->addDays((int) config('settings.token.special_expiry_days', 90))
            : null;

        $batch = DB::transaction(function () use ($user, $plan, $cost, $kind, $refId, $type, $quantity, $expiresAt): CouponBatch {
            $locked = User::whereKey($user->id)->lockForUpdate()->first();

            if ((float) $locked->agent_balance < $cost) {
                throw ValidationException::withMessages(['quantity' => 'Insufficient vendor balance.']);
            }

            $region = Coupon::regionFor($user);

            $batch = CouponBatch::create([
                'agent_id' => $user->id,
                'package_id' => $kind === 'package' ? $refId : null,
                'call_type_id' => $kind === 'shares' ? $refId : null,
                'additional_minute_plan_id' => $kind === 'minutes' ? $refId : null,
                'cloud_storage_plan_id' => $kind === 'cloud' ? $refId : null,
                'type' => $type,
                'region' => $region,
                'quantity' => $quantity,
            ]);

            for ($i = 0; $i < $quantity; $i++) {
                Coupon::create([
                    'type' => $type,
                    'region' => $region,
                    'agent_id' => $user->id,
                    'package_id' => $kind === 'package' ? $refId : null,
                    'call_type_id' => $kind === 'shares' ? $refId : null,
                    'additional_minute_plan_id' => $kind === 'minutes' ? $refId : null,
                    'cloud_storage_plan_id' => $kind === 'cloud' ? $refId : null,
                    'batch_id' => $batch->id,
                    'price' => $plan['price'],
                    'expires_at' => $expiresAt,
                    'code' => Coupon::generateCode($user->username, $plan['label']),
                ]);
            }

            $locked->decrement('agent_balance', $cost);

            return $batch;
        });

        return ['batch' => $batch, 'cost' => $cost];
    }
}
