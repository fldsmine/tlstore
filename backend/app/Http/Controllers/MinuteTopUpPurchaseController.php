<?php

namespace App\Http\Controllers;

use App\Models\AdditionalMinutePlan;
use App\Services\Payments\MinuteTopUpPurchaseService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class MinuteTopUpPurchaseController extends Controller
{
    public function start(Request $request, MinuteTopUpPurchaseService $purchases): RedirectResponse
    {
        $data = $request->validate([
            'plan_id' => ['required', 'string', 'exists:additional_minute_plans,id'],
        ]);

        $plan = AdditionalMinutePlan::query()->active()->findOrFail($data['plan_id']);
        $result = $purchases->start($request->user(), $plan);

        if (! $result['ok']) {
            return back()->with('toast', ['type' => 'error', 'message' => $result['error'] ?? 'Could not start the top-up payment.']);
        }

        return redirect()->away($result['checkout_url']);
    }

    public function callback(Request $request, MinuteTopUpPurchaseService $purchases): RedirectResponse
    {
        $reference = (string) $request->query('reference', '');
        $status = $reference !== '' ? $purchases->finalize($reference) : 'failed';

        [$message, $type] = match ($status) {
            'completed' => ['Your share-minute top-up has been applied.', 'success'],
            'pending', 'unavailable' => ['Your payment is still being verified. Minutes will be added only after confirmation.', 'info'],
            default => ['That top-up did not go through or needs review. Minutes were not added.', 'error'],
        };

        return redirect()->route('plans')->with('toast', ['type' => $type, 'message' => $message]);
    }
}
