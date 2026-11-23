<?php

namespace App\Livewire\Backoffice\Products;

use App\Livewire\Backoffice\Concerns\AuthorizesAdminActions;
use App\Models\PaymentReference;
use App\Models\ProductOrder;
use App\Services\Payments\ProductOrderService;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * Backoffice → Product orders → one order: the buyer, the money trail, and the box you paste the
 * product into.
 *
 * The delivery box is the fulfilment: whatever is typed there is emailed to the buyer verbatim, and
 * only the fact that it was sent (plus the staff note) is stored — live activation details never sit
 * in this database.
 */
#[Layout('components.layouts.backoffice')]
#[Title('Product Order — Backoffice')]
class OrderShow extends Component
{
    use AuthorizesAdminActions;

    public string $orderId = '';

    public string $deliveryDetails = '';

    public string $deliveryNote = '';

    public function mount(string $order): void
    {
        $this->orderId = $order;

        $this->order(); // 404s when the id is unknown rather than rendering an empty shell
    }

    protected function order(): ProductOrder
    {
        return ProductOrder::query()->findOrFail($this->orderId);
    }

    /** Send the product to the buyer (a resend when the order was already fulfilled). */
    public function send(ProductOrderService $orders): void
    {
        $this->authorizeWrite();

        $validated = $this->validate([
            'deliveryDetails' => ['required', 'string', 'min:2', 'max:5000'],
            'deliveryNote' => ['nullable', 'string', 'max:255'],
        ], [], [
            'deliveryDetails' => 'product details',
            'deliveryNote' => 'note',
        ]);

        $order = $this->order();
        $wasFulfilled = $order->isFulfilled();

        $result = $orders->deliver($order, $validated['deliveryDetails'], $validated['deliveryNote'] ?? null);

        if (! $result['ok']) {
            $this->dispatch('toast', message: $result['error'] ?? 'The product could not be sent.', type: 'error');

            return;
        }

        $this->reset('deliveryDetails', 'deliveryNote');

        $this->dispatch('toast', message: $wasFulfilled
            ? 'Sent again to '.$order->email.'.'
            : 'Sent to '.$order->email.' — the order is fulfilled.', type: 'success');
    }

    public function render(): View
    {
        $order = $this->order();

        return view('livewire.backoffice.products.order-show', [
            'order' => $order,
            'reference' => PaymentReference::query()->where('reference', $order->reference)->first(),
        ]);
    }
}
