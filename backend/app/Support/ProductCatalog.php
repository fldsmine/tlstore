<?php

namespace App\Support;

use App\Models\Coupon;
use App\Models\Package;
use App\Models\Product;
use App\Services\CurrencyService;
use Illuminate\Support\Str;

/**
 * The storefront catalog behind the product pages: everything on sale, in one list.
 *
 * Two sources feed it and both come out in the same shape, so the pages render one grid rather than
 * caring where an item came from:
 *
 *  - eSIM packages (`packages`), the ones the storefront has always sold, listed by `tier_level`
 *    and enabled per product page through `product.products`;
 *  - catalog products (`products`), created by an admin in the backoffice, listed by `sort_order`.
 *
 * Nothing about a price or a detail is hard-coded for either: package prices come from the package's
 * own customer price for the selling region, product prices from the admin's USD base (or their
 * pinned naira price), both converted at the live rate. So a change in the backoffice moves the
 * storefront with it, and the page can never advertise a price the charge would not honour.
 *
 * A guest has no account, so there is no per-visitor region to read: the storefront prices for the
 * region in `product.region` (Nigeria by default) and always charges the naira amount it shows.
 */
class ProductCatalog
{
    /**
     * Every item on sale, eSIM plans first, then the catalog products.
     *
     * @return list<array<string, mixed>>
     */
    public static function all(): array
    {
        return array_merge(self::packageItems(), self::productItems());
    }

    /** One item by slug, or null when it is not on sale (unknown, inactive, unlisted or unorderable). */
    public static function find(string $slug): ?array
    {
        $slug = Str::slug($slug);

        foreach (self::all() as $item) {
            if ($item['slug'] === $slug) {
                return $item;
            }
        }

        return null;
    }

    /** Just the eSIM plans — the storefront's own listing, and what the checkout uses for a package. */
    public static function packages(): array
    {
        return self::packageItems();
    }

    /** Just the admin-created products. */
    public static function products(): array
    {
        return self::productItems();
    }

    /**
     * The products the package listing shows, and in what order.
     *
     * `product.products` is the display list; the packages table holds a handful of rows, so matching
     * by slug in PHP keeps this database-agnostic (Str::slug is not a SQL function on either engine).
     * A slug with no active package behind it is dropped rather than rendered broken.
     *
     * @return list<array<string, mixed>>
     */
    private static function packageItems(): array
    {
        $slugs = (array) config('product.products', []);

        if ($slugs === []) {
            return [];
        }

        $packages = Package::query()
            ->where('is_active', true)
            ->orderBy('tier_level')
            ->get()
            ->keyBy(fn (Package $package): string => self::slugFor($package));

        $items = [];

        foreach ($slugs as $slug) {
            $package = $packages->get($slug);

            if ($package instanceof Package) {
                $items[] = self::presentPackage($package);
            }
        }

        return $items;
    }

    /**
     * Every active catalog product, in the admin's order.
     *
     * @return list<array<string, mixed>>
     */
    private static function productItems(): array
    {
        return Product::query()
            ->active()
            ->ordered()
            ->get()
            ->map(fn (Product $product): array => self::presentProduct($product))
            ->all();
    }

    public static function slugFor(Package $package): string
    {
        return Str::slug((string) $package->name);
    }

    /** The price the buyer is charged, in the storefront's currency. */
    public static function currency(): string
    {
        return strtoupper((string) config('product.currency', 'NGN'));
    }

    /**
     * An eSIM plan as the pages render it.
     *
     * @return array<string, mixed>
     */
    private static function presentPackage(Package $package): array
    {
        $priceUsd = self::priceUsd($package);
        $priceNgn = self::priceNgn($package);
        $slug = self::slugFor($package);

        return [
            'source' => 'package',
            'id' => $package->id,
            'package_id' => $package->id,
            'product_id' => null,
            'slug' => $slug,
            'name' => (string) $package->name,
            'kind' => 'eSIM plan',
            'tagline' => $package->description,
            'description' => $package->description,
            'badge' => null,
            'features' => self::featureList((array) ($package->features ?? [])),
            'specs' => self::specsFor($package),
            'image' => null,
            'image_alt' => null,
            'price_usd' => $priceUsd,
            'price_ngn' => $priceNgn,
            'currency' => self::currency(),
            'formatted_price' => self::format($priceNgn),
            'cta_label' => 'Buy '.$package->name,
            'sort_key' => (int) $package->tier_level,
            'is_featured' => (bool) $package->is_featured,
            // A plan with no resolvable price (missing FX row) must not be sellable.
            'orderable' => $priceNgn > 0,
        ];
    }

    /**
     * A catalog product as the pages render it.
     *
     * @return array<string, mixed>
     */
    private static function presentProduct(Product $product): array
    {
        $priceNgn = $product->priceNgn();

        return [
            'source' => 'product',
            'id' => $product->id,
            'package_id' => null,
            'product_id' => $product->id,
            'slug' => (string) $product->slug,
            'name' => (string) $product->name,
            'kind' => 'Product',
            'tagline' => $product->tagline,
            'description' => $product->description,
            'badge' => $product->badge,
            'features' => self::featureList((array) ($product->features ?? [])),
            'specs' => self::specList((array) ($product->specs ?? [])),
            'image' => $product->imageUrl(),
            'image_alt' => $product->image_alt ?: $product->name,
            'image_2' => $product->image_2,
            'image_3' => $product->image_3,
            'image_4' => $product->image_4,
            'image_2_url' => $product->image_2 ? (media_url($product->image_2) ?? $product->image_2) : null,
            'image_3_url' => $product->image_3 ? (media_url($product->image_3) ?? $product->image_3) : null,
            'image_4_url' => $product->image_4 ? (media_url($product->image_4) ?? $product->image_4) : null,
            'discount' => $product->discount !== null ? (float) $product->discount : null,
            'price_usd' => round((float) $product->price_usd, 2),
            'price_ngn' => $priceNgn,
            'currency' => self::currency(),
            'formatted_price' => self::format($priceNgn),
            'actual_price' => self::actualPrice($priceNgn, $product->discount),
            'cta_label' => $product->cta_label ?: 'Buy '.$product->name,
            'sort_key' => (int) $product->sort_order,
            'is_featured' => (bool) $product->is_featured,
            'orderable' => $priceNgn > 0,
        ];
    }

    /** The customer price of a package in USD for the configured selling region. */
    public static function priceUsd(Package $package): float
    {
        $region = (string) config('product.region', Coupon::REGION_NIGERIA);

        return round(RegionPricing::customer($package, $region), 2);
    }

    /** …and the same price in the currency the gateway is actually asked for. */
    public static function priceNgn(Package $package): float
    {
        return round(app(CurrencyService::class)->toNgn(self::priceUsd($package)), 2);
    }

    private static function format(float $amount): string
    {
        return '₦'.number_format($amount, 2);
    }


	private static function actualPrice(float $price, ?int $discount): float
	{
		if ($discount === null || $discount === 0) {
			return $price;
		}

		if ($discount < 0 || $discount >= 100) {
			return $price;
		}

		return round($price / (1 - ($discount / 100)), 2);
	}

    /**
     * @param  array<int, mixed>  $features
     * @return list<string>
     */
    private static function featureList(array $features): array
    {
        return array_values(array_filter(array_map(
            fn ($feature): string => trim((string) $feature),
            $features,
        ), fn (string $feature): bool => $feature !== ''));
    }

    /**
     * Admin-entered specs, kept as they were typed but stripped of empty pairs.
     *
     * @param  array<int, mixed>  $specs
     * @return list<array{label: string, value: string}>
     */
    private static function specList(array $specs): array
    {
        $out = [];

        foreach ($specs as $spec) {
            if (! is_array($spec)) {
                continue;
            }

            $label = trim((string) ($spec['label'] ?? ''));
            $value = trim((string) ($spec['value'] ?? ''));

            if ($label !== '' && $value !== '') {
                $out[] = ['label' => $label, 'value' => $value];
            }
        }

        return $out;
    }

    /**
     * An eSIM plan's own details, straight off the package — the numbers a buyer is deciding on.
     *
     * @return list<array{label: string, value: string}>
     */
    private static function specsFor(Package $package): array
    {
        $specs = [];

        if ((float) $package->daily_call_minutes > 0) {
            $specs[] = ['label' => 'Call minutes per day', 'value' => number_format((float) $package->daily_call_minutes, 0).' mins'];
        }

        if ((int) $package->call_id_limit > 0) {
            $specs[] = ['label' => 'Call IDs', 'value' => (string) (int) $package->call_id_limit];
        }

        $specs[] = ['label' => 'Make & receive calls', 'value' => $package->cloud_calls ? 'Yes' : 'Messages only'];
        $specs[] = ['label' => 'Receive text messages', 'value' => $package->cloud_sms ? 'Yes' : 'No'];

        if ((int) $package->cloud_sms_limit > 0) {
            $specs[] = ['label' => 'Cloud SMS', 'value' => (string) (int) $package->cloud_sms_limit];
        }

        if ((int) $package->cloud_contact_limit > 0) {
            $specs[] = ['label' => 'Cloud contacts', 'value' => (string) (int) $package->cloud_contact_limit];
        }

        return $specs;
    }
}
