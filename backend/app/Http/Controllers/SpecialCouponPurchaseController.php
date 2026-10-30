<?php

namespace App\Http\Controllers;

use App\Models\CloudStoragePlan;
use App\Services\Payments\SpecialCouponPurchaseService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Card purchase of a Revenue Authentication (special storage) code from the dashboard page.
 *
 * `start` hands the user to the gateway's hosted checkout; `callback` is the return trip. The return
 * trip proves nothing on its own — finalize() re-verifies with the gateway server-side, and only then
 * issues the code (see SpecialCouponPurchaseService and SettlesGatewayCharge).
 */
class SpecialCouponPurchaseController extends Controller
{
    public function start(Request $request, SpecialCouponPurchaseService $purchases): RedirectResponse
    {
        $data = $request->validate([
            'plan_id' => ['nullable', 'string', 'exists:cloud_storage_plans,id'],
        ]);

        $plan = filled($data['plan_id'] ?? null)
            ? CloudStoragePlan::query()->findOrFail($data['plan_id'])
            : null;

        $result = $purchases->start($request->user(), $plan);

        if (! $result['ok']) {
            return back()->with('toast', ['type' => 'error', 'message' => $result['error'] ?? 'Could not start the payment.']);
        }

        return redirect()->away($result['checkout_url']);
    }

    public function callback(Request $request, SpecialCouponPurchaseService $purchases): RedirectResponse
    {
        $reference = (string) $request->query('reference', '');

        // Never trust the callback: the gateway is re-queried server-side, the amount/currency are
        // reconciled against the charge, and the code is issued inside the settlement transaction.
        $status = $reference !== '' ? $purchases->finalize($reference) : 'failed';

        if ($status === 'completed') {
            // The page reads the freshly issued code back from its own row — only the reference
            // travels through the session, so the secret never rides along in a redirect.
            session()->flash('special_coupon_code_issued', $reference);
        }

        [$message, $type] = match ($status) {
            'completed' => ['Payment verified — your revenue code is ready below.', 'success'],
            'pending', 'unavailable' => ['Payment received — your revenue code will appear here as soon as it clears.', 'info'],
            default => ['That payment did not go through. You were not charged, or you have been refunded.', 'error'],
        };

        return redirect()->route('special-coupon')->with('toast', ['type' => $type, 'message' => $message]);
    }
}
