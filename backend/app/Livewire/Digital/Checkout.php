<?php

namespace App\Livewire\Digital;

use App\Support\ProductCatalog;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('components.layouts.digital')]
#[Title('Checkout — Nextel digital product')]
class Checkout extends Component
{
    public string $slug = '';

    public function mount(string $slug): void
    {
        $this->slug = $slug;

        abort_if(ProductCatalog::find($slug) === null || ProductCatalog::find($slug)['source'] !== 'product', 404);
    }

    public function render(): View
    {
        $product = ProductCatalog::find($this->slug);

        return view('livewire.digital.checkout', [
            'product' => $product,
            'provider' => (string) config('digital.provider', 'paystack'),
        ]);
    }
}
