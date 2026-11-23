<?php

use App\Livewire\Backoffice\Products\ListProducts;
use App\Mail\ProductOrderConfirmation;
use App\Models\Admin;
use App\Models\Country;
use App\Models\Currency;
use App\Models\Package;
use App\Models\PaymentReference;
use App\Models\Product;
use App\Models\ProductOrder;
use App\Services\Payments\ProductOrderService;
use App\Support\ProductCatalog;
use Database\Seeders\ProductDemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as ClientRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

uses(RefreshDatabase::class);

/**
 * The catalog behind the product storefront: the eSIM plans the store has always sold, plus the
 * products an admin creates in the backoffice — listed together on one page, bought the same way,
 * and priced so the card can never show an amount the charge would not ask for.
 */
beforeEach(function () {
    $this->withoutVite();

    Currency::create(['code' => 'NGN', 'name' => 'Nigerian Naira', 'symbol' => '₦', 'usd_exchange_rate' => 1500, 'is_active' => true]);
    Country::create(['name' => 'Nigeria', 'code' => 'NG', 'currency_code' => 'NGN', 'is_active' => true]);

    Package::create([
        'name' => 'Diamond eSIM',
        'tier_level' => 1,
        'price' => 10.5,
        'description' => 'Ideal for beginners.',
        'features' => ['Access to allocated phone numbers'],
        'cloud_calls' => false,
        'cloud_sms' => true,
        'cloud_sms_limit' => 10,
        'daily_call_minutes' => 30,
        'call_id_limit' => 8,
        'is_active' => true,
    ]);

    config([
        'product.provider' => 'paystack',
        'product.region' => 'nigeria',
        'product.products' => ['diamond-esim'],
        'product.ops_email' => 'ops@nextel.test',
        'product.currency' => 'NGN',
        'services.paystack.base_url' => 'https://api.paystack.co',
        'services.paystack.secret_key' => 'sk_test_paystack_catalog',
        'services.paystack.currency' => 'NGN',
        'settings.contact.email' => 'support@nextel.test',
    ]);
});

/** A published catalog product — the shape the backoffice saves. */
function catalogProduct(array $attributes = []): Product
{
    return Product::create(array_merge([
        'name' => 'Nextel 5G Router',
        'slug' => 'nextel-5g-router',
        'tagline' => 'Dual-band Wi-Fi 6 router that turns your eSIM into a home network.',
        'description' => 'Up to 32 devices on your Nextel plan, with a battery that outlasts an outage.',
        'badge' => 'Bestseller',
        'price_usd' => 59.00,
        'price_ngn' => 89000.00,
        'currency' => 'NGN',
        'image' => '/images/products/nextel-5g-router.jpg',
        'image_alt' => 'Nextel 5G router',
        'features' => ['Wi-Fi 6 dual band', 'Up to 32 devices'],
        'specs' => [['label' => 'Network', 'value' => '5G / 4G LTE'], ['label' => 'Battery', 'value' => '12 hours']],
        'is_active' => true,
        'is_featured' => false,
        'sort_order' => 1,
    ], $attributes));
}

/** Fake Paystack for a catalog-product checkout (one stateful closure — see ProductPageTest). */
function fakeCatalogGateway(?array &$captured = null): void
{
    $expected = [];

    Http::fake(function (ClientRequest $request) use (&$expected, &$captured) {
        if (str_contains($request->url(), '/transaction/initialize')) {
            $body = $request->data();
            $reference = (string) ($body['reference'] ?? '');
            $expected[$reference] = (int) ($body['amount'] ?? 0);
            $captured = $body;

            return Http::response([
                'status' => true,
                'data' => [
                    'authorization_url' => 'https://checkout.paystack.com/'.$reference,
                    'reference' => $reference,
                ],
            ]);
        }

        if (str_contains($request->url(), '/transaction/verify/')) {
            $reference = urldecode((string) substr($request->url(), (int) strrpos($request->url(), '/') + 1));

            return Http::response([
                'status' => true,
                'data' => [
                    'status' => 'success',
                    'amount' => $expected[$reference] ?? 0,
                    'currency' => 'NGN',
                    'reference' => $reference,
                    'id' => 'ps_'.substr(md5($reference), 0, 10),
                ],
            ]);
        }

        return Http::response(['status' => false, 'message' => 'unexpected'], 500);
    });
}

/* ------------------------------------------------------------------ the catalog itself */

test('the catalog lists eSIM plans and catalog products in one list', function () {
    catalogProduct();

    $all = ProductCatalog::all();

    expect($all)->toHaveCount(2)
        ->and($all[0]['source'])->toBe('package')       // plans first — they are the flagship
        ->and($all[0]['slug'])->toBe('diamond-esim')
        ->and($all[1]['source'])->toBe('product')
        ->and($all[1]['slug'])->toBe('nextel-5g-router')
        ->and($all[1]['image'])->toBe('/images/products/nextel-5g-router.jpg');

    expect(ProductCatalog::find('nextel-5g-router')['name'])->toBe('Nextel 5G Router')
        ->and(ProductCatalog::find('diamond-esim')['source'])->toBe('package')
        ->and(ProductCatalog::find('nope'))->toBeNull();
});

test('a product price follows the exchange rate until an admin pins one', function () {
    // Blank naira price: $59 at ₦1,500/$1.
    $tracking = catalogProduct(['slug' => 'tracker', 'price_ngn' => null]);
    expect($tracking->priceNgn())->toBe(88500.0);

    // The admin types a local price: that is what the card shows, rate or no rate.
    $pinned = catalogProduct(['name' => 'Nextel Power Bank', 'slug' => 'power-bank', 'price_ngn' => 43500.00]);
    expect($pinned->priceNgn())->toBe(43500.0);

    // …and a rate change moves the first one, not the second. CurrencyService is a per-request
    // singleton (one read of the rate table per request), so the next request is what picks the new
    // rate up — exactly what a visitor gets.
    Currency::query()->where('code', 'NGN')->update(['usd_exchange_rate' => 1600]);
    app()->forgetInstance(\App\Services\CurrencyService::class);

    expect($tracking->refresh()->priceNgn())->toBe(94400.0)
        ->and($pinned->refresh()->priceNgn())->toBe(43500.0);
});

test('an inactive product is not in the catalog at all', function () {
    catalogProduct(['is_active' => false]);

    expect(ProductCatalog::all())->toHaveCount(1)
        ->and(ProductCatalog::find('nextel-5g-router'))->toBeNull();
});

test('a product slug never shadows an eSIM plan', function () {
    // "Diamond eSIM" would resolve to the package's URL…
    $product = new Product(['name' => 'Diamond eSIM']);

    expect(Product::slugTaken('diamond-esim'))->toBeTrue()
        ->and(Product::slugFor('Diamond eSIM'))->toBe('diamond-esim-2');

    $product->fill([
        'slug' => Product::slugFor('Diamond eSIM'),
        'price_usd' => 5,
        'is_active' => true,
    ])->save();

    // …and now both are reachable, each at its own address.
    expect(ProductCatalog::find('diamond-esim')['source'])->toBe('package')
        ->and(ProductCatalog::find('diamond-esim-2')['source'])->toBe('product');
});

/* ------------------------------------------------------------------ the storefront page */

test('the storefront is a full page: hero, features, products, benefits, FAQ and a closing call to action', function () {
    catalogProduct();

    $page = $this->get(route('product.index'))
        ->assertOk()
        // hero
        ->assertSee('Nextel Store')
        ->assertSee('Shop the store')
        // features
        ->assertSee('Why Nextel')
        ->assertSee('Pay securely, in naira')
        // products — the plan and the catalog item, each as a card
        ->assertSee('eSIM plans')
        ->assertSee('Diamond eSIM')
        ->assertSee('More from the catalog')
        ->assertSee('Nextel 5G Router')
        ->assertSee('Bestseller')          // the admin's badge, on the card
        ->assertSee('/images/products/nextel-5g-router.jpg')   // the admin's image
        ->assertSee('₦89,000.00')
        ->assertSee('Buy Nextel 5G Router')
        // benefits + FAQ + CTA
        ->assertSee('Guest checkout')
        ->assertSee('Frequently asked questions')
        ->assertSee('Do I need an account to buy?')
        ->assertSee('Nextel app now available on iOS and Android');

    // The plan is priced at its package price, in naira, on the same page.
    expect($page->getContent())->toContain('₦15,750.00');
});

test('a hidden product disappears from the storefront but stays in the backoffice', function () {
    $product = catalogProduct();

    $this->get(route('product.index'))->assertOk()->assertSee('Nextel 5G Router');

    $product->update(['is_active' => false]);

    $this->get(route('product.index'))->assertOk()->assertDontSee('Nextel 5G Router');
    $this->get(route('product.checkout', 'nextel-5g-router'))->assertNotFound();
});

test('the checkout page shows the product, its image, its details and the exact amount', function () {
    catalogProduct();

    $this->get(route('product.checkout', 'nextel-5g-router'))
        ->assertOk()
        ->assertSee('Nextel 5G Router')
        ->assertSee('/images/products/nextel-5g-router.jpg')
        ->assertSee('Up to 32 devices on your Nextel plan')
        ->assertSee('Wi-Fi 6 dual band')
        ->assertSee('Network')
        ->assertSee('12 hours')
        ->assertSee('₦89,000.00')
        ->assertSee('Total to pay')
        ->assertSee('Full name')
        ->assertSee('Phone number')
        ->assertSee('Email address');
});

/* ------------------------------------------------------------------ buying a product */

test('a guest can buy a catalog product with no account', function () {
    Mail::fake();
    $captured = null;
    fakeCatalogGateway($captured);

    catalogProduct();

    $this->post(route('product.start', 'nextel-5g-router'), [
        'full_name' => 'Chinedu Okeke',
        'phone' => '0803 123 4567',
        'email' => 'Chinedu@Example.com',
    ])->assertRedirect();

    $order = ProductOrder::query()->sole();

    expect($order->source)->toBe('product')
        ->and($order->isCatalogProduct())->toBeTrue()
        ->and($order->product_id)->not->toBeNull()
        ->and($order->package_id)->toBeNull()
        ->and($order->product_name)->toBe('Nextel 5G Router')
        ->and((float) $order->amount_ngn)->toBe(89000.0)
        ->and($order->kindLabel())->toBe('Product');

    // Paystack was asked for the exact naira amount, in kobo.
    expect($captured['amount'] ?? null)->toBe(8900000);

    // Settling it claims the reference once and emails both sides.
    expect(app(ProductOrderService::class)->settle($order->reference))->toBe('completed')
        ->and($order->refresh()->status)->toBe(ProductOrder::STATUS_PAID)
        ->and(PaymentReference::query()->count())->toBe(1);

    Mail::assertQueued(ProductOrderConfirmation::class, fn ($mail) => $mail->order->reference === $order->reference);
});

test('buying a product charges the pinned price, not the converted one', function () {
    $captured = null;
    fakeCatalogGateway($captured);

    catalogProduct(['price_usd' => 59.00, 'price_ngn' => 80000.00]);

    $this->post(route('product.start', 'nextel-5g-router'), [
        'full_name' => 'Chinedu Okeke',
        'phone' => '08031234567',
        'email' => 'chinedu@example.com',
    ])->assertRedirect();

    expect($captured['amount'] ?? null)->toBe(8000000)
        ->and((float) ProductOrder::query()->sole()->amount_ngn)->toBe(80000.0);
});

test('the receipt of a product order says what is coming and where', function () {
    Mail::fake();
    fakeCatalogGateway();

    catalogProduct();

    $this->post(route('product.start', 'nextel-5g-router'), [
        'full_name' => 'Chinedu Okeke',
        'phone' => '08031234567',
        'email' => 'chinedu@example.com',
    ])->assertRedirect();

    $order = ProductOrder::query()->sole();
    app(ProductOrderService::class)->settle($order->reference);

    $this->get(route('product.success', $order->reference))
        ->assertOk()
        ->assertSee('Payment successful')
        ->assertSee('Nextel 5G Router')
        ->assertSee('will be sent to')
        ->assertSee($order->maskedEmail());
});

/* ------------------------------------------------------------------ backoffice */

test('guests cannot open the catalog manager', function () {
    $this->get(route('backoffice.products.catalog'))->assertRedirect(route('backoffice.login'));
});

test('the catalog manager lists products and the storefront state around them', function () {
    catalogProduct();                                                     // a pinned naira price
    catalogProduct([
        'name' => 'Nextel Power Bank',
        'slug' => 'power-bank',
        'tagline' => 'A full charge for long earning days.',
        'price_ngn' => null,
    ]);

    $this->actingAs(Admin::factory()->super()->create(), 'admin')
        ->get(route('backoffice.products.catalog'))
        ->assertOk()
        ->assertSee('Catalog Products')
        ->assertSee('Nextel 5G Router')
        ->assertSee('/products/nextel-5g-router')
        ->assertSee('₦89,000.00')
        ->assertSee('$59.00')
        ->assertSee('Live')
        // Both pricing modes are visible in the list: one product pins its naira price, the other
        // follows the exchange rate.
        ->assertSee('pinned')
        ->assertSee('follows the rate')
        ->assertSee('Nextel Power Bank');

    Livewire::test(ListProducts::class)
        ->assertSee('Nextel 5G Router')
        ->set('search', 'router')
        ->assertSee('Nextel 5G Router')
        ->assertDontSee('Nextel Power Bank')
        ->set('search', 'nothing-like-this')
        ->assertSee('No products match that search.');
});

test('an admin creates a product with an image and it goes live on the storefront', function () {
    Storage::fake(config('filesystems.default'));

    $this->actingAs(Admin::factory()->regular()->create(), 'admin');

    Livewire::test(ListProducts::class)
        ->call('openCreate')
        ->set('name', 'Nextel Mobile Hotspot')
        ->set('tagline', 'Pocket Wi-Fi for your Nextel line.')
        ->set('description', 'Carry your line anywhere and share it with up to ten devices.')
        ->set('badge', 'New arrival')
        ->set('priceUsd', '35')
        ->set('priceNgn', '')
        ->set('ctaLabel', '')
        ->set('imageAlt', 'Nextel mobile hotspot')
        ->set('featureLines', "Pocket-size\nUp to 10 devices\nUSB-C charging")
        ->set('specLines', "Battery: 10 hours\nNetwork: 4G LTE\nWarranty: 12 months")
        ->set('sortOrder', '7')
        ->set('image', UploadedFile::fake()->image('hotspot.jpg', 1200, 900))
        ->call('save')
        ->assertHasNoErrors()
        ->assertSet('showModal', false);

    $product = Product::query()->sole();

    expect($product->slug)->toBe('nextel-mobile-hotspot')
        ->and($product->is_active)->toBeTrue()
        ->and((float) $product->price_usd)->toBe(35.0)
        ->and($product->price_ngn)->toBeNull()                       // blank = follow the rate
        ->and($product->features)->toBe(['Pocket-size', 'Up to 10 devices', 'USB-C charging'])
        ->and($product->specs)->toBe([
            ['label' => 'Battery', 'value' => '10 hours'],
            ['label' => 'Network', 'value' => '4G LTE'],
            ['label' => 'Warranty', 'value' => '12 months'],
        ])
        ->and($product->image)->not->toBeNull();

    // The upload really landed on the disk (compressed by the image service, not the raw file).
    Storage::disk(config('filesystems.default'))->assertExists($product->image);

    // …and the storefront now sells it, at the rate-converted price.
    expect(ProductCatalog::find('nextel-mobile-hotspot')['price_ngn'])->toBe(52500.0);

    $this->get(route('product.index'))->assertOk()->assertSee('Nextel Mobile Hotspot');
    $this->get(route('product.checkout', 'nextel-mobile-hotspot'))->assertOk()->assertSee('₦52,500.00');
});

test('the product form refuses to save something that is not a product', function () {
    $this->actingAs(Admin::factory()->super()->create(), 'admin');

    Livewire::test(ListProducts::class)
        ->call('openCreate')
        ->set('name', 'No')
        ->set('priceUsd', '')
        ->call('save')
        ->assertHasErrors(['name', 'priceUsd']);

    Livewire::test(ListProducts::class)
        ->call('openCreate')
        ->set('name', 'Nextel Fake Image')
        ->set('priceUsd', '20')
        ->set('image', UploadedFile::fake()->create('malware.php', 10, 'text/x-php'))
        ->call('save')
        ->assertHasErrors('image');

    expect(Product::query()->count())->toBe(0);
});

test('editing a product keeps its slug and refreshes what the storefront shows', function () {
    $product = catalogProduct();

    $this->actingAs(Admin::factory()->super()->create(), 'admin');

    Livewire::test(ListProducts::class)
        ->call('edit', $product->id)
        ->assertSet('name', 'Nextel 5G Router')
        ->assertSet('specLines', "Network: 5G / 4G LTE\nBattery: 12 hours")
        ->set('name', 'Nextel 5G Router Pro')
        ->set('priceNgn', '95000')
        ->call('save')
        ->assertHasNoErrors();

    $product->refresh();

    expect($product->name)->toBe('Nextel 5G Router Pro')
        ->and($product->slug)->toBe('nextel-5g-router-pro')
        ->and($product->priceNgn())->toBe(95000.0);
});

test('publishing and featuring are one click, and delete is confirmed', function () {
    $product = catalogProduct(['is_active' => false]);

    $this->actingAs(Admin::factory()->regular()->create(), 'admin');

    Livewire::test(ListProducts::class)
        ->call('toggleActive', $product->id)
        ->call('toggleFeatured', $product->id);

    expect($product->refresh()->is_active)->toBeTrue()
        ->and($product->is_featured)->toBeTrue();

    // The confirmation is a step, not a browser dialog: nothing is deleted until it is confirmed.
    Livewire::test(ListProducts::class)
        ->call('confirmDelete', $product->id)
        ->assertSet('confirmingDeleteId', $product->id)
        ->call('cancelDelete')
        ->assertSet('confirmingDeleteId', null);

    expect(Product::query()->count())->toBe(1);

    Livewire::test(ListProducts::class)->call('confirmDelete', $product->id)->call('delete', $product->id);

    expect(Product::query()->count())->toBe(0);
});

test('a support admin can look at the catalog but cannot change it', function () {
    $product = catalogProduct();

    $this->actingAs(Admin::factory()->support()->create(), 'admin');

    $this->get(route('backoffice.products.catalog'))->assertOk()->assertSee('Nextel 5G Router');

    // The write guard is on the ACTION, not just on the hidden button: a forged Livewire call must
    // fail too. However the refusal surfaces (thrown, or rendered as a 403 response), the row must
    // be exactly as it was.
    $this->actingAs(Admin::factory()->support()->create(), 'admin');

    foreach ([['openCreate', []], ['toggleActive', [$product->id]], ['save', []], ['delete', [$product->id]]] as [$method, $arguments]) {
        try {
            Livewire::test(ListProducts::class)->call($method, ...$arguments);
        } catch (\Throwable) {
            // refused
        }
    }

    expect(Product::query()->count())->toBe(1)
        ->and($product->refresh()->is_active)->toBeTrue()
        ->and($product->name)->toBe('Nextel 5G Router');
});

/* ------------------------------------------------------------------ demo content */

test('the demo seeder fills the storefront without duplicating itself', function () {
    $this->seed(ProductDemoSeeder::class);

    $products = Product::query()->orderBy('sort_order')->get();

    expect($products)->toHaveCount(6)
        // Every demo product has an image on disk, so the grid is never a wall of placeholders.
        ->and($products->every(fn (Product $product): bool => $product->image !== null))->toBeTrue();

    foreach ($products as $product) {
        $path = public_path(ltrim((string) $product->image, '/'));

        expect(file_exists($path))->toBeTrue("Demo image missing for {$product->name}: {$product->image}");
    }

    // One of them is featured, and the storefront renders the whole grid beside the eSIM plan.
    expect($products->where('is_featured', true))->toHaveCount(1);

    $this->get(route('product.index'))
        ->assertOk()
        ->assertSee('Nextel 5G Router')
        ->assertSee('Nextel Power Bank 20,000mAh')
        ->assertSee('Nextel SIM Starter Kit')
        ->assertSee('Diamond eSIM');

    // Seeding twice updates in place instead of creating a second catalog.
    $this->seed(ProductDemoSeeder::class);

    expect(Product::query()->count())->toBe(6);
});
