<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cloud_notes', function (Blueprint $table) {
            $table->string('voice_name')->nullable()->after('voice');
        });

        // Backfill a display name so every existing note indicates its voice. Browser/Default
        // notes (no stored MP3) resolve here; ElevenLabs notes keep it null so the live voice
        // list resolves their id to a friendly label whenever the integration is switched on.
        DB::statement("UPDATE cloud_notes SET voice_name = 'Default' WHERE voice_name IS NULL AND audio_path IS NULL AND voice IS NULL");
        DB::statement('UPDATE cloud_notes SET voice_name = voice WHERE voice_name IS NULL AND audio_path IS NULL AND voice IS NOT NULL');
    }

    public function down(): void
    {
        Schema::table('cloud_notes', function (Blueprint $table) {
            $table->dropColumn('voice_name');
        });
    }
};
