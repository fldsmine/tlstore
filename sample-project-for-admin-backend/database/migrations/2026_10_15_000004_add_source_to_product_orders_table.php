<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A guest order can now come from either kind of storefront item, so the row records which one.
 *
 * `package_id` stays what it always was (the eSIM package sold); `product_id` is the catalog product
 * an admin created. Both are snapshots, not foreign keys — an order must survive its product being
 * renamed, repriced or deleted, and the buyer's receipt has to keep saying what they bought.
 *
 * Existing rows default to `package`, which is what every order before this migration was.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('product_orders', function (Blueprint $table) {
            $table->string('source')->default('package')->after('product_name');
            $table->uuid('product_id')->nullable()->after('package_id');
        });

        Schema::table('product_orders', function (Blueprint $table) {
            $table->index('source');
        });
    }

    public function down(): void
    {
        Schema::table('product_orders', function (Blueprint $table) {
            $table->dropIndex(['source']);
            $table->dropColumn(['source', 'product_id']);
        });
    }
};
