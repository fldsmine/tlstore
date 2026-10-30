<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Voice notes (the "sponsor message") — a TEXT message read aloud via the browser's Web Speech
     * API (text-to-speech). A number's notes play in sort order during a call; the call ends when
     * the last note finishes (TTS length is measured live on the client).
     */
    public function up(): void
    {
        Schema::create('cloud_notes', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('cloud_number_id')->constrained('cloud_numbers')->cascadeOnDelete();
            $table->string('title');
            $table->text('message');                             // spoken via text-to-speech
            $table->string('voice')->nullable();                 // selected Web Speech API voice name (client-resolved)
            $table->string('lang')->nullable();                  // voice BCP-47 lang tag (fallback match, e.g. en-US)
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cloud_notes');
    }
};
