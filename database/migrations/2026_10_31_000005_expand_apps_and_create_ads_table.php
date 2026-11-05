<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('apps', function (Blueprint $table) {
            $table->decimal('paid_amount', 12, 2)->default(0.00)->comment('Total amount owners pay for an app review campaign');
            $table->string('location', 32)->nullable()->comment('ISO country code or region key; NULL means open to all');
            $table->char('currency', 3)->default('USD')->comment('Campaign currency; conversion is display-only');
            $table->decimal('rating', 3, 2)->nullable()->comment('Rating copied from the app store listing');
            $table->unsignedBigInteger('total_reviews')->nullable()->comment('Review count copied from the app store listing');
            $table->decimal('review_reward', 12, 2)->default(0.00)->comment('Per-review reward in campaign currency');
            $table->string('app_icon', 255)->nullable()->comment('App icon storage path or URL');
            $table->unsignedInteger('maximum_review')->nullable()->comment('Maximum campaign reviews; NULL means not configured');
        });

        Schema::create('ads', function (Blueprint $table) {
            $table->id();
            $table->string('campaign_key', 64)->unique();
            $table->string('title', 180);
            $table->text('description')->nullable();
            $table->text('share_message')->nullable();
            $table->unsignedBigInteger('owner_id')->nullable()->comment('References the sponsoring owner account');
            $table->string('creative_path', 255)->nullable();
            $table->string('destination_url', 2048)->nullable();
            $table->decimal('paid_amount', 12, 2)->default(0.00)->comment('Total amount owners pay for ad shares');
            $table->string('location', 32)->nullable()->comment('ISO country code or region key; NULL means open to all');
            $table->char('currency', 3)->default('USD')->comment('Campaign currency; conversion is display-only');
            $table->unsignedBigInteger('total_share')->default(0)->comment('Number of approved ad shares');
            $table->decimal('share_reward', 12, 2)->default(0.00)->comment('Per-share reward in campaign currency');
            $table->unsignedBigInteger('maximum_share')->nullable()->comment('Maximum campaign shares; NULL means not configured');
            $table->string('status', 24)->default('draft');
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->integer('featured')->default(0)->comment('If listing is featured or not [0,1], featured shows on HomeScreen and special pages');
            $table->timestamps();

            $table->index(['status', 'location']);
            $table->index(['starts_at', 'ends_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ads');

        Schema::table('apps', function (Blueprint $table) {
            $table->dropColumn([
                'paid_amount',
                'location',
                'currency',
                'rating',
                'total_reviews',
                'review_reward',
                'app_icon',
                'maximum_review',
            ]);
        });
    }
};
