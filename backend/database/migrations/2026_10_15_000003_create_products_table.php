<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Products the storefront sells besides the eSIM packages — the physical/accessory items an admin
 * creates from the backoffice (routers, power banks, cameras, whatever the catalog grows into).
 *
 * A separate table from `packages` on purpose: a package drives entitlements all over the app
 * (call minutes, cloud features, referral bonuses, activation), while a catalog product is just
 * something sold on the product pages and sent by hand. Hanging two routers off `packages` would
 * mean inventing call minutes and cloud limits for them.
 *
 * `price_ngn` nullable = "follow the exchange rate": the storefront converts `price_usd` at the
 * current rate on every read, and the amount is frozen onto the order at checkout either way.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->string('name');
            $table->string('slug')->unique();             // the storefront URL: /products/{slug}
            $table->string('tagline')->nullable();        // one line under the name on the card
            $table->text('description')->nullable();      // the longer story on the checkout page
            $table->string('badge')->nullable();          // "Just arrived", "Limited", …
            $table->json('features')->nullable();         // list<string>
            $table->json('specs')->nullable();            // list<{label, value}>

            $table->decimal('price_usd', 18, 6)->default(0);   // base price, like the packages
            $table->decimal('price_ngn', 18, 2)->nullable();   // null = derive from USD at the live rate
            $table->string('currency', 8)->default('NGN');

            $table->string('image')->nullable();          // stored path on the default disk
            $table->string('image_alt')->nullable();
            $table->string('cta_label')->nullable();      // defaults to "Buy {name}"

            $table->boolean('is_active')->default(true);  // only active products reach the storefront
            $table->boolean('is_featured')->default(false);
            $table->unsignedInteger('sort_order')->default(0);

            $table->json('meta')->nullable();
            $table->timestamps();

            $table->index(['is_active', 'sort_order']);   // the storefront listing
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
