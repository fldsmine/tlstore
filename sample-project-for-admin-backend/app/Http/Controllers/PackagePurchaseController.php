<?php

namespace App\Http\Controllers;

use App\Models\Package;
use App\Models\Transaction;
use App\Services\Payments\PackagePurchaseService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PackagePurchaseController extends Controller
{
    public function start(Request $request, PackagePurchaseService $purchases): RedirectResponse
    {
        $data = $request->validate([
            'package_id' => ['required', 'string', 'exists:packages,id'],
            'mode' => ['required', 'string', Rule::in([
                PackagePurchaseService::MODE_ACTIVATE,
                PackagePurchaseService::MODE_UPGRADE,
                PackagePurchaseService::MODE_RENEW,
            ])],
        ]);

        $package = Package::query()->findOrFail($data['package_id']);

        $result = $purchases->start($request->user(), $package, (string) $data['mode']);

        if (! $result['ok']) {
            return back()->with('toast', ['type' => 'error', 'message' => $result['error'] ?? 'Could not start the payment.']);
        }

        return redirect()->away($result['checkout_url']);
    }

    public function callback(Request $request, PackagePurchaseService $purchases): RedirectResponse
    {
        $reference = (string) $request->query('reference', '');

        // Never trust the callback: finalize() re-verifies server-side and reconciles amount/currency.
        $status = $reference !== '' ? $purchases->finalize($reference) : 'failed';
        $transaction = $reference !== '' ? Transaction::query()->where('reference', $reference)->first() : null;
        $meta = (array) ($transaction?->meta ?? []);
        $isRenewal = ($meta['mode'] ?? null) === PackagePurchaseService::MODE_RENEW;

        [$message, $type] = match ($status) {
            'completed' => $isRenewal
                ? ['Your share plan has been renewed with a fresh term allocation.', 'success']
                : ['Your eSIM package is active — your Call IDs are ready.', 'success'],
            'pending', 'unavailable' => $isRenewal
                ? ['Payment received — your share plan renewal will be applied shortly.', 'info']
                : ['Payment received — your package will be activated shortly.', 'info'],
            default => ['That package payment did not go through. You were not charged.', 'error'],
        };

        return redirect()->route('dashboard')->with('toast', ['type' => $type, 'message' => $message]);
    }
}
