<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * apps
 *
 * App listings that can be reviewed and promoted..
 *
 * Relationships:
 *   - has many app_screenshots, app_store_listing_snapshots, app_review_campaigns
 *
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('apps', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('prototype_id')->nullable()->unique();
            $table->string('name', 180);
            $table->string('short_code', 12);
            $table->string('store', 80);
            $table->string('brand_color', 7);
            $table->string('developer', 180);
            $table->string('category', 100);
            $table->string('install_count_label', 32)->nullable();
            $table->text('description');
            $table->string('store_link', 180)->nullable();
            $table->string('app_icon', 255)->nullable()->comment('App icon storage path or URL');
            $table->string('status', 24)->default('active');
            $table->integer('featured')->default(0)->comment('If listing is featured or not [0,1], featured shows on HomeScreen and special pages');
            $table->decimal('paid_amount', 12, 2)->default(0.00)->comment('Total amount owners pay for an app review campaign');
            $table->string('location', 32)->nullable()->comment('ISO country code or region key; NULL means open to all');
            $table->char('currency', 3)->default('USD')->comment('Campaign currency; conversion is display-only');
            $table->decimal('rating', 3, 2)->nullable()->comment('Rating copied from the app store listing');
            $table->unsignedBigInteger('total_reviews')->nullable()->comment('Review count copied from the app store listing');
            $table->decimal('review_reward', 12, 2)->default(0.00)->comment('Per-review reward in campaign currency');
            $table->unsignedInteger('maximum_review')->nullable()->comment('Maximum campaign reviews; NULL means not configured');
            $table->timestamps();
            $table->index(['store', 'category']);
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('apps');
    }
};
