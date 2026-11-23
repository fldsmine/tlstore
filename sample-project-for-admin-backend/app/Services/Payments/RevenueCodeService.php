<?php

namespace App\Services\Payments;

use App\Enums\CouponType;
use App\Models\Admin;
use App\Models\CloudStoragePlan;
use App\Models\Coupon;
use App\Models\User;
use App\Services\CloudActivationService;
use App\Support\RegionPricing;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Revenue Authentication codes — the special storage codes (coupons of type STORAGE_ACTIVATION) that
 * apply a package tier's storage bonus AND authorise one N-Cloud withdrawal (call/SMS balance).
 *
 * They reach users three ways; this service owns the two the platform issues itself and leaves the
 * vendor channel (Coupon minting by agents) untouched:
 *
 *   direct — bought by the user on the Revenue Authentication page with a card. The charge settles
 *            through SpecialCouponPurchaseService, which calls issueFor() below.
 *   admin  — issued by a super admin against payment proof the user sent to support, for the cases
 *            the automatic path cannot cover (charge flagged for manual review, payment not showing).
 *
 * A vendor-minted code stays UNBOUND until the user activates it; that flow
 * (CloudActivationService::activateStorage) is deliberately unchanged. Binding the code WITHOUT
 * marking `used_at` is what later lets WithdrawalService consume the very same code.
 */
class RevenueCodeService
{
    public function __construct(private CloudActivationService $activation) {}

    /**
     * The special plan for a user: the active cloud-storage plan tagged with their package tier.
     * Null when the user has no package/tier or nothing is published for it — a revenue code cannot
     * be issued or sold without one, because the plan is what the code grants.
     */
    public function planFor(User $user): ?CloudStoragePlan
    {
        $tier = $user->package?->tier_level;

        if ($tier === null) {
            return null;
        }

        return CloudStoragePlan::query()->active()->forTier((int) $tier)->ordered()->first();
    }

    /** What a code for this plan costs the user of this region (same price a vendor charges). */
    public function priceUsd(User $user, CloudStoragePlan $plan): float
    {
        return RegionPricing::customer($plan, RegionPricing::forUser($user));
    }

    /**
     * A code the PLATFORM issued to this user (bought with a card or issued by support) that is still
     * usable: unspent, unexpired. Newest first — the dashboard shows it as "your revenue code".
     *
     * Vendor-minted codes are excluded on purpose: the user typed those in themselves, and the page
     * promises never to display a code entered there.
     */
    public function issuedCodeFor(User $user): ?Coupon
    {
        return Coupon::query()
            ->where('user_id', $user->id)
            ->where('type', CouponType::STORAGE_ACTIVATION->value)
            ->whereIn('source', [Coupon::SOURCE_DIRECT, Coupon::SOURCE_ADMIN])
            ->whereNull('used_at')
            ->where(fn ($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()))
            ->orderByDesc('created_at')
            ->first();
    }

    /** The code bought by a specific charge — exact match so the success panel shows the right one. */
    public function codeForReference(User $user, string $reference): ?Coupon
    {
        return Coupon::query()
            ->where('user_id', $user->id)
            ->where('payment_reference', $reference)
            ->where('type', CouponType::STORAGE_ACTIVATION->value)
            ->first();
    }

    /**
     * Issue a code to a user and apply its storage bonus in ONE transaction.
     *
     * Returns the raw code alongside the coupon: the caller has to show it to its owner, and it is
     * stored on the row anyway. It is never written to a log.
     *
     * @param  string  $source  Coupon::SOURCE_DIRECT (bought with a card) or Coupon::SOURCE_ADMIN (support)
     * @param  ?string  $reason  why support issued it (payment proof, reference on the user's message)
     * @return array{coupon: Coupon, code: string}
     */
    public function issueFor(
        User $user,
        CloudStoragePlan $plan,
        string $source,
        ?string $paymentReference = null,
        ?Admin $admin = null,
        ?string $reason = null,
    ): array {
        if (! $plan->is_active) {
            throw new RuntimeException('The storage plan for this tier is no longer available.');
        }

        // The code is only good for the tier it was published for, and it is bound to the buyer from
        // the start, so the tier has to match before anything is written.
        if ($plan->tier_level === null || (int) $plan->tier_level !== (int) $user->package?->tier_level) {
            throw new RuntimeException('That storage plan does not match the user\'s package tier.');
        }

        return DB::transaction(function () use ($user, $plan, $source, $paymentReference, $admin, $reason): array {
            $code = Coupon::generateCode($user->username, $plan->name);

            $coupon = Coupon::create([
                'code' => $code,
                'type' => CouponType::STORAGE_ACTIVATION,
                // Region is derived from the owner, so the code passes CouponCountryPolicy for them
                // by construction (a direct purchase is never cross-region).
                'region' => Coupon::regionFor($user),
                'user_id' => $user->id,   // bound: the withdrawal authorisation accepts it as-is
                'cloud_storage_plan_id' => $plan->id,
                'price' => $this->priceUsd($user, $plan),
                'is_special' => true,
                'source' => $source,
                'payment_reference' => $paymentReference,
                'issued_by_admin_id' => $admin?->id,
                'issued_reason' => $reason,
                // Same lifetime a vendor-minted special code gets, so both channels behave alike.
                'expires_at' => now()->addDays((int) config('settings.token.special_expiry_days', 90)),
            ]);

            // Storage is granted NOW, not on a second activation: the buyer (or support) paid for it,
            // and the code must be immediately usable to authorise a withdrawal. grantPack() is the
            // same implementation a redeemed vendor token goes through — one pack at a time.
            $this->activation->grantPack($user, $plan, $coupon);

            return ['coupon' => $coupon, 'code' => $code];
        });
    }

    /** "…+5 contacts · +10 SMS/day" — what the code grants, for the page and support replies. */
    public function describe(CloudStoragePlan $plan): string
    {
        $parts = ['+'.$plan->extra_contacts.' '.str('contact')->plural((int) $plan->extra_contacts)];

        if ((int) $plan->extra_sms > 0) {
            $parts[] = '+'.$plan->extra_sms.' SMS/day';
        }

        return implode(' · ', $parts);
    }
}
