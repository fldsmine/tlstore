<?php

namespace App\Livewire\Digital;

use App\Support\ProductCatalog;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('components.layouts.digital')]
#[Title('Digital products — Nextel')]
class Index extends Component
{
    public function render(): View
    {
        // Only digital (catalog) products are sold here — eSIM plans stay on the main storefront.
        $products = ProductCatalog::products();

        return view('livewire.digital.index', [
            'products' => $products,
        ]);
    }
}
