<?php

namespace App\Http\Controllers;

use App\Services\CurrencyService;
use App\Services\Payments\BillTopUpService;
use App\Support\DisplayCurrency;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class BillTopUpController extends Controller
{
    public function start(Request $request, BillTopUpService $topUps): RedirectResponse
    {
        $data = $request->validate([
            'amount' => ['required', 'numeric', 'min:1'],
        ]);

        $user = $request->user();
        $fx = app(CurrencyService::class);

        // Amount is typed in the reader's display currency. Convert to the USD wallet value,
        // then to the NGN the bill gateway charges in.
        $usd = DisplayCurrency::isLocal($user) ? $fx->toUsd((float) $data['amount'], $user->country) : (float) $data['amount'];
        $amountNgn = $fx->toNgn($usd);

        $result = $topUps->start($user, $amountNgn);

        if (! $result['ok']) {
            return back()->with('toast', ['type' => 'error', 'message' => $result['error'] ?? 'Could not start the top-up.']);
        }

        return redirect()->away($result['checkout_url']);
    }

    public function callback(Request $request, BillTopUpService $topUps): RedirectResponse
    {
        $reference = (string) $request->query('reference', '');

        $status = $reference !== '' ? $topUps->finalize($reference) : 'failed';

        [$message, $type] = match ($status) {
            'completed' => ['Your VTU balance has been topped up.', 'success'],
            'pending', 'unavailable' => ['Payment received — your wallet will be credited shortly.', 'info'],
            default => ['That top-up did not go through. You were not charged.', 'error'],
        };

        return redirect()->route('vas')->with('toast', ['type' => $type, 'message' => $message]);
    }
}
