<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Promotional popups shown over the user dashboard.
     *
     * Everything except the body is optional: an admin can post a plain message, or build the
     * full card (media panel, badge, headline, two action links, footer). Blank pieces are simply
     * not rendered — see resources/views/components/app/popup-card.blade.php — and a popup with no
     * header image shows no media panel at all.
     */
    public function up(): void
    {
        Schema::create('popups', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('title', 80)->nullable();           // pill above the headline
            $table->string('heading', 160)->nullable();        // the headline itself
            $table->text('body');                              // required message
            $table->string('image_caption', 80)->nullable();   // pill over the media panel
            $table->string('header_image')->nullable();        // media panel image; null = gradient
            $table->string('background_image')->nullable();    // behind the text column
            $table->string('primary_label', 60)->nullable();   // main button
            $table->string('primary_url', 255)->nullable();
            $table->string('secondary_label', 60)->nullable(); // underlined link
            $table->string('secondary_url', 255)->nullable();
            $table->string('footer_text', 160)->nullable();    // small print under the links
            $table->boolean('is_active')->default(true);
            $table->boolean('is_recursive')->default(true);    // true = shown again on every visit
            $table->timestamps();

            $table->index('is_active');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('popups');
    }
};
