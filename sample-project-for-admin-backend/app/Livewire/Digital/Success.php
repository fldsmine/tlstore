<?php

namespace App\Livewire\Digital;

use App\Models\ProductOrder;
use App\Services\Payments\ProductOrderService;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('components.layouts.digital')]
#[Title('Your order — Nextel')]
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

        return view('livewire.digital.success', [
            'order' => $order,
        ]);
    }
}
