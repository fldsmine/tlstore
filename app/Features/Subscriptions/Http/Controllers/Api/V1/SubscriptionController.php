<?php

namespace App\Features\Subscriptions\Http\Controllers\Api\V1;

use App\Application\Actions\Payments\InitializeGatewayTopup;
use App\Application\Actions\Payments\VerifyGatewayTopup;
use App\Application\Support\PaymentGatewaySettings;
use App\Application\Support\SubscriptionPriceQuote;
use App\Models\PaymentIntent;
use App\Application\Actions\Subscriptions\PurchaseSubscription;
use App\Models\SubscriptionPlan;
use App\Models\SubscriptionGroup;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class SubscriptionController
{
    public function purchase(Request $request, PurchaseSubscription $purchase): JsonResponse
    {
        $data = $request->validate([
            'subscription_plan_id' => ['required', 'integer', 'exists:subscription_plans,id'],
            'idempotency_key' => ['required', 'string', 'max:80'],
        ]);
        $plan = SubscriptionPlan::query()->with('group')->where('is_active', true)->findOrFail($data['subscription_plan_id']);
        $subscription = $purchase->handle($request->user(), $plan, $data['idempotency_key']);

        return response()->json(['data' => $subscription, 'message' => 'Package activated using top-up funds.'], 201);
    }

    public function submitQuestionnaire(Request $request): JsonResponse
    {
        $data = $request->validate([
            'group_key' => ['required', 'string', 'in:group_b,group_c'],
            'answers' => ['required', 'array:device,weekly_income,daily_time,tested_before,ready_today,motivation'],
            'answers.device' => ['required', 'in:iphone,android,other'],
            'answers.weekly_income' => ['required', 'in:100_200,200_500,500_plus'],
            'answers.daily_time' => ['required', 'in:5_10,10_30,30_plus'],
            'answers.tested_before' => ['required', 'in:yes,no'],
            'answers.ready_today' => ['required', 'in:yes,maybe_later'],
            'answers.motivation' => ['required', 'in:extra_income,pay_bills,side_hustle'],
        ]);
        abort_unless(SubscriptionGroup::query()->where('group_key', $data['group_key'])->where('is_active', true)->exists(), 404);

        $user = $request->user();
        $activeSubscription = $user->subscriptions()
            ->where('product_type', 'marketplace_access')
            ->where('status', 'active')
            ->where('starts_at', '<=', now())
            ->where('ends_at', '>', now())
            ->whereHas('plan.group', fn ($query) => $query->where('group_key', $data['group_key']))
            ->with('plan.group')
            ->latest('starts_at')
            ->first();
        if ($activeSubscription?->plan?->group?->group_key !== $data['group_key']) {
            return response()->json(['message' => 'Join an active package in this group before answering its qualification questions.'], 422);
        }
        $completedGroups = $user->subscription_questionnaire_completed_groups ?? [];
        $answersByGroup = $user->subscription_questionnaire_answers ?? [];
        $answersByGroup[$data['group_key']] = $data['answers'];
        $completedGroups = array_values(array_unique([...$completedGroups, $data['group_key']]));
        $user->forceFill([
            'subscription_questionnaire_answers' => $answersByGroup,
            'subscription_questionnaire_completed_groups' => $completedGroups,
            'subscription_questionnaire_completed_at' => now(),
        ])->save();

        return response()->json([
            'message' => 'Your app tester qualification answers were saved.',
            'data' => ['completed_groups' => $completedGroups, 'completed_at' => $user->subscription_questionnaire_completed_at],
        ]);
    }

    public function checkout(Request $request, InitializeGatewayTopup $initialize, SubscriptionPriceQuote $priceQuote): JsonResponse
    {
        $data = $request->validate([
            'subscription_plan_id' => ['required', 'integer', 'exists:subscription_plans,id'],
            'gateway' => ['required', 'in:korapay,paystack,flutterwave,bachs'],
            'currency' => ['required', 'string', 'size:3'],
            'idempotency_key' => ['required', 'string', 'max:80'],
            'customer_email' => ['nullable', 'string', 'max:191', 'email:rfc'],
        ]);
        $plan = SubscriptionPlan::query()->where('is_active', true)->findOrFail($data['subscription_plan_id']);
        if ($plan->product_type->value !== 'marketplace_access') {
            return response()->json(['message' => 'Location coverage packages are no longer available.'], 422);
        }
        $currency = strtoupper($data['currency']);
        if (! PaymentGatewaySettings::supports($data['gateway'], $currency)) {
            return response()->json(['message' => 'This payment option is no longer available. Refresh your account and select another currency.'], 422);
        }
        $amountMinor = $priceQuote->amountMinor($plan, $currency);
        $samePlanActive = $request->user()->subscriptions()
            ->where('subscription_plan_id', $plan->id)
            ->where('status', 'active')
            ->where('ends_at', '>', now())
            ->exists();
        if ($samePlanActive) {
            return response()->json(['message' => 'This package is already active on your account.'], 422);
        }
        $pendingIntent = $request->user()->paymentIntents()
            ->where('purpose', 'subscription')
            ->where('source_type', 'subscription_plan')
            ->where('source_id', $plan->id)
            ->where('provider', $data['gateway'])
            ->where('currency', $currency)
            ->where('amount_minor', $amountMinor)
            ->where('status', 'pending')
            ->where('expires_at', '>', now())
            ->latest('id')
            ->first();
        if ($pendingIntent) {
            $checkoutUrl = $pendingIntent->provider_metadata['checkout_url'] ?? null;
            if (is_string($checkoutUrl) && $checkoutUrl !== '') {
                return response()->json(['data' => [
                    'intent_id' => $pendingIntent->id,
                    'reference' => $pendingIntent->provider_reference,
                    'provider' => $pendingIntent->provider,
                    'checkout_url' => $checkoutUrl,
                    'status' => 'pending',
                ]]);
            }
        }
        return response()->json(['data' => $initialize->handle(
            $request->user(),
            $amountMinor,
            $currency,
            $data['gateway'],
            $data['idempotency_key'],
            'subscription',
            $plan,
            $data['customer_email'] ?? null,
        )], 201);
    }

    public function check(Request $request, PaymentIntent $paymentIntent, VerifyGatewayTopup $verify): JsonResponse
    {
        abort_unless($paymentIntent->user_id === $request->user()->id && $paymentIntent->purpose === 'subscription', 404);
        $paymentIntent = $verify->handle($paymentIntent);

        return response()->json([
            'data' => [
                'id' => $paymentIntent->id,
                'status' => $paymentIntent->status->value,
                'amount_minor' => $paymentIntent->amount_minor,
                'currency' => $paymentIntent->currency,
                'provider' => $paymentIntent->provider,
                'paid_at' => $paymentIntent->paid_at,
                'subscription' => $paymentIntent->userSubscriptions()->with(['plan.group', 'plan.features'])->first(),
            ],
        ]);
    }

    public function mine(Request $request): JsonResponse
    {
        return response()->json(['data' => $request->user()->subscriptions()->with(['plan.group', 'plan.features'])
            ->latest('starts_at')->paginate(30)]);
    }
}
