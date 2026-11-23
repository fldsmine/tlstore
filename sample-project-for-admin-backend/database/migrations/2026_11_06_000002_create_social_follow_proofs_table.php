<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Proofs are documentation only and intentionally remain separate from follow confirmations.
        Schema::create('user_social_follow_proofs', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('social_handle_id')->constrained('social_handles')->restrictOnDelete();
            $table->string('path');
            $table->string('original_name');
            // Preserve the target the user documented, even if the admin later edits the page URL.
            $table->string('target_url', 500);
            $table->timestamps();

            $table->unique(['user_id', 'social_handle_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_social_follow_proofs');
    }
};
