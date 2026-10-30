<?php

namespace App\Http\Controllers;

use App\Models\CloudStoragePlan;
use App\Services\Payments\StoragePurchaseService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class StoragePurchaseController extends Controller
{
    public function start(Request $request, StoragePurchaseService $purchases): RedirectResponse
    {
        $data = $request->validate([
            'plan_id' => ['required', 'string', 'exists:cloud_storage_plans,id'],
            'mode' => ['required', 'string', Rule::in([
                StoragePurchaseService::MODE_ACTIVATE,
                StoragePurchaseService::MODE_SWITCH,
                StoragePurchaseService::MODE_RENEW,
            ])],
        ]);

        $plan = CloudStoragePlan::query()->findOrFail($data['plan_id']);

        $result = $purchases->start($request->user(), $plan, (string) $data['mode']);

        if (! $result['ok']) {
            return back()->with('toast', ['type' => 'error', 'message' => $result['error'] ?? 'Could not start the payment.']);
        }

        return redirect()->away($result['checkout_url']);
    }

    public function callback(Request $request, StoragePurchaseService $purchases): RedirectResponse
    {
        $reference = (string) $request->query('reference', '');

        // Never trust the callback: finalize() re-verifies server-side and reconciles amount/currency.
        $status = $reference !== '' ? $purchases->finalize($reference) : 'failed';

        [$message, $type] = match ($status) {
            'completed' => ['Storage activated — your extra slots are ready.', 'success'],
            'pending', 'unavailable' => ['Payment received — your storage plan will be activated shortly.', 'info'],
            default => ['That storage payment did not go through. You were not charged.', 'error'],
        };

        return redirect()->route('cloud')->with('toast', ['type' => $type, 'message' => $message]);
    }
}
