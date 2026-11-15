<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('app_review_campaigns', function (Blueprint $table) {
            $table->foreignId('review_template_version_id')
                ->nullable()
                ->after('app_id')
                ->constrained('review_template_versions')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('app_review_campaigns', function (Blueprint $table) {
            $table->dropConstrainedForeignId('review_template_version_id');
        });
    }
};
