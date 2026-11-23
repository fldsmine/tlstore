<?php

namespace App\Livewire\Product;

use App\Support\ProductCatalog;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * One product's page: what it is, what it costs, and the three details a guest has to leave to buy it.
 *
 * The form posts to ProductCheckoutController (a plain POST, then a redirect away to the gateway),
 * so the payment step works exactly like every other charge in this app and never depends on
 * JavaScript running in the browser.
 */
#[Layout('components.layouts.product')]
#[Title('Checkout — Nextel eSIM')]
class Checkout extends Component
{
    /** The product slug from the route. */
    public string $slug = '';

    public function mount(string $slug): void
    {
        $this->slug = $slug;

        // Unknown, inactive or unlisted products are a 404, never a blank page with a broken form.
        abort_if(ProductCatalog::find($slug) === null, 404);
    }

    public function render(): View
    {
        $product = ProductCatalog::find($this->slug);

        return view('livewire.product.checkout', [
            'product' => $product,
            'provider' => (string) config('product.provider', 'paystack'),
        ]);
    }
}
