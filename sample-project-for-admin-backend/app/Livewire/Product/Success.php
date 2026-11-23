<?php

namespace App\Livewire\Product;

use App\Models\ProductOrder;
use App\Services\Payments\ProductOrderService;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * Where the buyer lands after paying (and where a refresh keeps working): the order's real state,
 * read from our own record — never from the query string the gateway returned with.
 *
 * A payment that was still in flight when they came back is normally settled by the webhook or the
 * five-minute requery sweep; while the order is fresh this page also re-checks once, so a refresh
 * shows the truth instead of a stale "we are confirming".
 */
#[Layout('components.layouts.product')]
#[Title('Your eSIM order — Nextel')]
class Success extends Component
{
    public string $reference = '';

    public function mount(string $reference, ProductOrderService $orders): void
    {
        $order = ProductOrder::query()->where('reference', $reference)->first();

        if ($order === null) {
            abort(404);
        }

        $this->reference = $order->reference;

        if ($order->status === ProductOrder::STATUS_PENDING && $order->created_at->gt(now()->subHours(2))) {
            $orders->settle($order->reference);
        }
    }

    public function render(): View
    {
        $order = ProductOrder::query()->where('reference', $this->reference)->firstOrFail();

        return view('livewire.product.success', [
            'order' => $order,
        ]);
    }
}
