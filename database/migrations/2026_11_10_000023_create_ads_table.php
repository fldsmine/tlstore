<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * ads
 *
 * Sponsored ad campaigns that pay users per verified public share.
 *
 * Relationships:
 *   - owner_id -> users.id (null on delete)
 *   - has many ad_share_tasks
 *
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ads', function (Blueprint $table) {
            $table->id();
            $table->string('campaign_key', 64)->unique();
            $table->string('title', 180);
            $table->string('type', 48)->default('others')->index();
            $table->text('description')->nullable();
            $table->text('share_message')->nullable();
            $table->unsignedBigInteger('owner_id')->nullable()->comment('References the sponsoring owner account');
            $table->string('creative_path', 255)->nullable();
            $table->string('destination_url', 2048)->nullable();
            $table->decimal('paid_amount', 12, 2)->default(0.00)->comment('Total amount owners pay for ad shares');
            $table->string('location', 32)->nullable()->comment('ISO country code or region key; NULL means open to all');
            $table->char('currency', 3)->default('USD')->comment('Campaign currency; conversion is display-only');
            $table->unsignedBigInteger('total_share')->default(0)->comment('Number of approved ad shares');
            $table->unsignedBigInteger('reserved_share')->default(0)->comment('Share slots temporarily reserved for active tasks');
            $table->decimal('share_reward', 12, 2)->default(0.00)->comment('Per-share reward in campaign currency');
            $table->unsignedBigInteger('maximum_share')->nullable()->comment('Maximum campaign shares; NULL means not configured');
            $table->string('status', 24)->default('draft');
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->integer('featured')->default(0)->comment('If listing is featured or not [0,1], featured shows on HomeScreen and special pages');
            $table->timestamps();
            $table->index(['status', 'location']);
            $table->index(['starts_at', 'ends_at']);
            $table->foreign('owner_id')->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ads');
    }
};
