<?php

namespace App\Application\Actions\PackageAddons;

use App\Models\PackageAddon;
use App\Models\PaymentIntent;
use App\Models\UserPackageAddon;
use App\Models\User;
use Illuminate\Support\Facades\DB;

final class ActivatePackageAddon
{
    public function handle(PaymentIntent $intent): UserPackageAddon
    {
        return DB::transaction(function () use ($intent): UserPackageAddon {
            $intent = PaymentIntent::query()->lockForUpdate()->findOrFail($intent->id);
            $existing = UserPackageAddon::query()->where('payment_intent_id', $intent->id)->first();
            if ($existing) return $existing->load('addon.plan');

            // Serialize renewals for a user so concurrent confirmed payments
            // extend the entitlement one after another instead of overlapping.
            User::query()->lockForUpdate()->findOrFail($intent->user_id);
            $addon = PackageAddon::query()->with('plan')->findOrFail($intent->source_id);
            $now = $intent->paid_at ?? now();
            $latestExpiry = UserPackageAddon::query()
                ->where('user_id', $intent->user_id)->where('package_addon_id', $addon->id)
                ->where('status', 'active')->where('ends_at', '>', $now)->max('ends_at');
            $startsAt = $now->copy();
            if ($latestExpiry) {
                $latestExpiry = \Illuminate\Support\Carbon::parse($latestExpiry);
                if ($latestExpiry->greaterThan($startsAt)) $startsAt = $latestExpiry;
            }
            $endsAt = $startsAt->copy()->addMonthsNoOverflow($addon->duration_count);

            return UserPackageAddon::query()->create([
                'user_id' => $intent->user_id, 'package_addon_id' => $addon->id,
                'payment_intent_id' => $intent->id, 'status' => 'active',
                'price_minor' => $intent->amount_minor, 'currency' => $intent->currency,
                'starts_at' => $startsAt, 'ends_at' => $endsAt,
            ])->load('addon.plan');
        });
    }
}
