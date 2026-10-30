<?php

namespace App\Http\Controllers\Product;

use App\Http\Controllers\Controller;
use App\Models\ProductOrder;
use App\Services\Payments\ProductOrderService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * The two server-side steps of a guest checkout: open a payment link, and come back to settle it.
 *
 * Both are deliberately thin — the price, the reference and the settlement rules live in
 * ProductOrderService, so nothing the browser sends (not even the amount, which it never sends) can
 * change what is charged or what gets marked paid.
 */
class ProductCheckoutController extends Controller
{
    /**
     * Start a checkout: validate the buyer's details, create the order and send them to the gateway.
     */
    public function start(Request $request, string $slug, ProductOrderService $orders): RedirectResponse
    {
        $data = $request->validate([
            'full_name' => ['required', 'string', 'min:2', 'max:120'],
            // Common Nigerian and international formats: 0803…, 0803 123 4567, +234 803 123 4567.
            'phone' => ['required', 'string', 'max:20', 'regex:/^\+?[0-9][0-9\s\-().]{6,19}$/'],
            'email' => ['required', 'string', 'email', 'max:150'],
        ], [], [
            'full_name' => 'full name',
            'phone' => 'phone number',
            'email' => 'email address',
        ]);

        $result = $orders->start($slug, [
            'full_name' => Str::of($data['full_name'])->squish()->limit(120, '')->toString(),
            'phone' => trim((string) $data['phone']),
            'email' => Str::lower(trim((string) $data['email'])),
        ]);

        if ($result['not_found'] ?? false) {
            abort(404);
        }

        if (! $result['ok']) {
            return back()
                ->withInput($request->only('full_name', 'phone', 'email'))
                ->with('toast', ['type' => 'error', 'message' => $result['error'] ?? 'We could not start that payment. Please try again.']);
        }

        return redirect()->away($result['checkout_url']);
    }

    /**
     * The gateway sends the buyer back here. Nothing about the redirect is trusted — settlement
     * re-verifies with the gateway, reconciles the amount and claims the single-use ledger before
     * the order is marked paid — so this only has to route the buyer to their receipt.
     */
    public function callback(Request $request, string $reference, ProductOrderService $orders): RedirectResponse
    {
        $order = ProductOrder::query()->where('reference', $reference)->first();

        if ($order === null) {
            abort(404);
        }

        $orders->settle($order->reference);

        return redirect()->route('product.success', ['reference' => $order->reference]);
    }
}
