<?php

namespace App\Http\Controllers\Digital;

use App\Http\Controllers\Controller;
use App\Models\ProductOrder;
use App\Services\Payments\ProductOrderService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class DigitalCheckoutController extends Controller
{
    public function start(Request $request, string $slug, ProductOrderService $orders): RedirectResponse
    {
        $data = $request->validate([
            'full_name' => ['required', 'string', 'min:2', 'max:120'],
            'phone' => ['required', 'string', 'max:20', 'regex:/^\+?[0-9][0-9\s\-().]{6,19}$/'],
            'email' => ['required', 'string', 'email', 'max:150'],
            'provider' => ['required', 'string', 'in:paystack,korapay'],
        ], [], [
            'full_name' => 'full name',
            'phone' => 'phone number',
            'email' => 'email address',
            'provider' => 'payment method',
        ]);

        $result = $orders->start($slug, [
            'full_name' => Str::of($data['full_name'])->squish()->limit(120, '')->toString(),
            'phone' => trim((string) $data['phone']),
            'email' => Str::lower(trim((string) $data['email'])),
        ], (string) $data['provider']);

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

    public function callback(Request $request, string $reference, ProductOrderService $orders): RedirectResponse
    {
        $order = ProductOrder::query()->where('reference', $reference)->first();

        if ($order === null) {
            abort(404);
        }

        $orders->settle($order->reference);

        return redirect()->route('digital.success', ['reference' => $order->reference]);
    }
}
