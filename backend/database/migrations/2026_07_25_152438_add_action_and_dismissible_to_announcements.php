<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('announcements', function (Blueprint $table) {
            $table->string('action_url')->nullable()->after('body');
            $table->string('action_label', 60)->nullable()->after('action_url');
            $table->boolean('is_dismissible')->default(true)->after('action_label');
        });
    }

    public function down(): void
    {
        Schema::table('announcements', function (Blueprint $table) {
            $table->dropColumn(['action_url', 'action_label', 'is_dismissible']);
        });
    }
};
