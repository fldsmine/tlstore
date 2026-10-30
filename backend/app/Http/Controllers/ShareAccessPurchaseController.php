<?php

namespace App\Http\Controllers;

use App\Models\CallType;
use App\Services\Payments\ShareAccessPurchaseService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ShareAccessPurchaseController extends Controller
{
    public function start(Request $request, ShareAccessPurchaseService $purchases): RedirectResponse
    {
        $data = $request->validate([
            'call_type_id' => ['required', 'string', 'exists:call_types,id'],
        ]);

        $callType = CallType::query()->active()->findOrFail($data['call_type_id']);
        $result = $purchases->start($request->user(), $callType);

        if (! $result['ok']) {
            return back()->with('toast', ['type' => 'error', 'message' => $result['error'] ?? 'Could not start the share payment.']);
        }

        return redirect()->away($result['checkout_url']);
    }

    public function callback(Request $request, ShareAccessPurchaseService $purchases): RedirectResponse
    {
        $reference = (string) $request->query('reference', '');
        $status = $reference !== '' ? $purchases->finalize($reference) : 'failed';

        [$message, $type] = match ($status) {
            'completed' => ['Your share access is active. Open the Shareholder Portal to start claiming.', 'success'],
            'pending', 'unavailable' => ['Your payment is still being verified. Share access will be activated only after confirmation.', 'info'],
            default => ['That share payment did not go through or needs review. No share access was granted.', 'error'],
        };

        return redirect()->route('shareholder')->with('toast', ['type' => $type, 'message' => $message]);
    }
}
