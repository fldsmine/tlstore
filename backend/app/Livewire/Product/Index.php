<?php

namespace App\Livewire\Product;

use App\Support\ProductCatalog;
use App\Support\ProductPageContent;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * The storefront: the eSIM plans and the catalog products, priced from the packages and the products
 * themselves, wrapped in the marketing page around them (hero, features, benefits, FAQ).
 *
 * Reachable by anyone — no account, no middleware — because the whole point of this page is to sell
 * to a visitor who has never signed up. It is served on the dedicated product domain's root and at
 * /products on every host.
 */
#[Layout('components.layouts.product')]
#[Title('Buy an eSIM — connect and start earning')]
class Index extends Component
{
    public function render(): View
    {
        return view('livewire.product.index', [
            'products' => ProductCatalog::all(),
            'hero' => ProductPageContent::hero(),
            'features' => ProductPageContent::features(),
            'benefits' => ProductPageContent::benefits(),
            'faqs' => ProductPageContent::faqs(),
            'final' => ProductPageContent::final(),
            'products_heading' => ProductPageContent::productsHeading(),
            'plans_heading' => ProductPageContent::plansHeading(),
            'extras_heading' => ProductPageContent::extrasHeading(),
        ]);
    }
}
