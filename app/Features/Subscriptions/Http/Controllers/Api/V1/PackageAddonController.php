<?php

namespace App\Features\Subscriptions\Http\Controllers\Api\V1;

use App\Application\Actions\Marketplace\ListingEligibility;
use App\Application\Actions\PackageAddons\PurchasePackageAddon;
use App\Application\Actions\Payments\InitializeGatewayTopup;
use App\Application\Actions\Payments\VerifyGatewayTopup;
use App\Application\Support\PackageAddonPriceQuote;
use App\Application\Support\PaymentGatewaySettings;
use App\Application\Money\CurrencyMinorUnits;
use App\Models\PackageAddon;
use App\Models\PaymentIntent;
use App\Models\ReviewTask;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class PackageAddonController
{
    public function index(Request $request, PackageAddonPriceQuote $quote): JsonResponse
    {
        $plans = $request->user()->subscriptions()->with('plan.packageAddon')
            ->where('product_type', 'marketplace_access')->where('status', 'active')
            ->where('starts_at', '<=', now())->where('ends_at', '>', now())->get()
            ->filter(fn ($subscription) => (bool) $subscription->plan?->addons)
            ->map(function ($subscription) use ($request, $quote) {
                $addon = $subscription->plan->packageAddon;
                if (! $addon) return null;
                $active = $request->user()->packageAddons()->where('package_addon_id', $addon->id)
                    ->where('status', 'active')->where('starts_at', '<=', now())->where('ends_at', '>', now())->latest('ends_at')->first();
                $latest = $request->user()->packageAddons()->where('package_addon_id', $addon->id)->latest('starts_at')->first();
                return ['addon' => $addon, 'plan' => $subscription->plan, 'active_entitlement' => $active,
                    'latest_entitlement' => $latest, 'listed_price_minor' => $addon->price_minor,
                    'payable_price_minor' => $quote->amountMinor($addon, $addon->currency),
                    'currency_minor_units' => CurrencyMinorUnits::precision($addon->currency),
                    'checkout_options' => $addon->is_active ? $quote->optionsFor($addon) : []];
            })->filter()->values();
        return response()->json(['data' => $plans]);
    }

    public function reviewEligibility(Request $request, ReviewTask $task, ListingEligibility $eligibility, PackageAddonPriceQuote $quote): JsonResponse
    {
        abort_unless($task->reviewer_id === $request->user()->id && $task->status->value === 'reserved', 404);
        $task->loadMissing('campaign.app');
        $subscription = $eligibility->subscriptionForTask(
            $request->user(), 'review', $task->campaign->app?->store, $task->campaign->app?->category,
        );
        $plan = $subscription?->plan;
        if (! $plan?->addons) return response()->json(['data' => ['required' => false, 'active' => true]]);
        $addon = $plan->packageAddon()->first();
        if (! $addon) return response()->json(['data' => ['required' => true, 'active' => false, 'offer_available' => false]]);
        $active = $request->user()->packageAddons()->where('package_addon_id', $addon->id)->where('status', 'active')
            ->where('starts_at', '<=', now())->where('ends_at', '>', now())->latest('ends_at')->first();

        return response()->json(['data' => [
            'required' => true, 'active' => $active !== null,
            'offer_available' => (bool) $addon->is_active, 'addon' => $addon, 'plan_name' => $plan->name,
            'listed_price_minor' => $addon->price_minor,
            'payable_price_minor' => $quote->amountMinor($addon, $addon->currency),
            'currency_minor_units' => CurrencyMinorUnits::precision($addon->currency),
            'entitlement' => $active, 'checkout_options' => $quote->optionsFor($addon),
        ]]);
    }

    public function purchase(Request $request, PackageAddon $addon, PurchasePackageAddon $purchase): JsonResponse
    {
        $data = $request->validate(['idempotency_key' => ['required', 'string', 'max:80']]);
        $entitlement = $purchase->withFunds($request->user(), $addon, $data['idempotency_key']);
        return response()->json(['data' => $entitlement->load('addon.plan'), 'message' => 'Add-on activated using Funds wallet.'], 201);
    }

    public function checkout(Request $request, InitializeGatewayTopup $initialize, PackageAddonPriceQuote $quote): JsonResponse
    {
        $data = $request->validate([
            'package_addon_id' => ['required', 'integer', 'exists:package_addons,id'],
            'gateway' => ['required', 'in:korapay,paystack,flutterwave,bachs,fincra'],
            'currency' => ['required', 'string', 'size:3'],
            'idempotency_key' => ['required', 'string', 'max:80'],
            'customer_email' => ['nullable', 'string', 'max:191', 'email:rfc'],
        ]);
        $addon = PackageAddon::query()->with('plan')->where('is_active', true)->findOrFail($data['package_addon_id']);
        app(PurchasePackageAddon::class)->assertEligible($request->user(), $addon);
        $currency = strtoupper($data['currency']);
        if (! PaymentGatewaySettings::supports($data['gateway'], $currency)) {
            return response()->json(['message' => 'This payment option is no longer available. Refresh and select another currency.'], 422);
        }
        $amount = $quote->amountMinor($addon, $currency);
        $pending = $request->user()->paymentIntents()->where('purpose', 'package_addon')->where('source_id', $addon->id)
            ->where('provider', $data['gateway'])->where('currency', $currency)->where('amount_minor', $amount)
            ->where('status', 'pending')->where('expires_at', '>', now())->latest('id')->first();
        if ($pending && is_string($pending->provider_metadata['checkout_url'] ?? null)) {
            return response()->json(['data' => ['intent_id' => $pending->id, 'reference' => $pending->provider_reference,
                'provider' => $pending->provider, 'checkout_url' => $pending->provider_metadata['checkout_url'], 'status' => 'pending']]);
        }
        return response()->json(['data' => $initialize->handle(
            $request->user(), $amount, $currency, $data['gateway'], $data['idempotency_key'],
            'package_addon', null, $data['customer_email'] ?? null, $addon,
        )], 201);
    }

    public function check(Request $request, PaymentIntent $paymentIntent, VerifyGatewayTopup $verify): JsonResponse
    {
        abort_unless($paymentIntent->user_id === $request->user()->id && $paymentIntent->purpose === 'package_addon', 404);
        $paymentIntent = $verify->handle($paymentIntent);
        return response()->json(['data' => [
            'id' => $paymentIntent->id, 'status' => $paymentIntent->status->value,
            'amount_minor' => $paymentIntent->amount_minor, 'currency' => $paymentIntent->currency,
            'provider' => $paymentIntent->provider,
            'entitlement' => $paymentIntent->userPackageAddon()->with('addon.plan')->first(),
        ]]);
    }

    public function mine(Request $request): JsonResponse
    {
        return response()->json(['data' => $request->user()->packageAddons()->with('addon.plan')->latest('starts_at')->paginate(30)]);
    }
}
