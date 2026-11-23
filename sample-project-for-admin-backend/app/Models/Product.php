<?php

namespace App\Models;

use App\Services\CurrencyService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * Something the storefront sells besides the eSIM packages — created and edited by an admin in the
 * backoffice (Backoffice → Catalog → Catalog Products).
 *
 * Prices follow the same rule as the packages: `price_usd` is the base, and `price_ngn` is either the
 * admin's own local price or, when left blank, the USD price converted at the current rate (so the
 * item tracks the exchange rate until someone pins a price). Whichever it is, the amount is frozen
 * onto the order the moment a guest starts a checkout — the buyer pays what the card showed them.
 */
class Product extends Model
{
    use HasFactory, HasUuids;

    protected function casts(): array
    {
        return [
            'features' => 'array',
            'specs' => 'array',
            'meta' => 'array',
            'price_usd' => 'decimal:6',
            'price_ngn' => 'decimal:2',
            'is_active' => 'boolean',
            'is_featured' => 'boolean',
            'discount' => 'decimal:2',
            'sort_order' => 'integer',
        ];
    }

    /** The storefront listing: active first, the admin's own order within that. */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('sort_order')->orderBy('name');
    }

    /**
     * The naira price this product is sold at: the admin's pinned price, or the USD price at the
     * live rate. Never negative, and rounded to the kobo the gateway will be asked for.
     */
    public function priceNgn(): float
    {
        if ($this->price_ngn !== null) {
            return round(max(0.0, (float) $this->price_ngn), 2);
        }

        return round(max(0.0, app(CurrencyService::class)->toNgn((float) $this->price_usd)), 2);
    }


	function actualPrice(float $price, int $discount): float
	{
		if ($discount < 0 || $discount >= 100) {
			return $price;
		}

		return $price / (1 - ($discount / 100));
	}


    /** A unique, URL-safe slug — never one that collides with an eSIM package's slug. */
    public static function slugFor(string $name, ?string $ignoreId = null): string
    {
        $base = Str::slug($name) ?: 'product';
        $slug = $base;
        $suffix = 2;

        while (static::slugTaken($slug, $ignoreId)) {
            $slug = $base.'-'.$suffix++;
        }

        return $slug;
    }

    /**
     * Slugs share one namespace with the packages: both are sold at /products/{slug}, so a product
     * called "Diamond eSIM" must not silently shadow the package of the same name.
     */
    public static function slugTaken(string $slug, ?string $ignoreId = null): bool
    {
        $taken = static::query()
            ->where('slug', $slug)
            ->when($ignoreId !== null, fn (Builder $query) => $query->whereKeyNot($ignoreId))
            ->exists();

        if ($taken) {
            return true;
        }

        return Package::query()
            ->get(['name'])
            ->contains(fn (Package $package): bool => Str::slug((string) $package->name) === $slug);
    }

    /** The card's image, or null so the storefront draws its own placeholder. */
    public function imageUrl(): ?string
    {
        $image = (string) ($this->image ?? '');

        if ($image === '') {
            return null;
        }

        return media_url($image) ?? $image;
    }
}
