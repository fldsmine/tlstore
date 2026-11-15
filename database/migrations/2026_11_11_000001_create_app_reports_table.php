<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('app_reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('reporter_user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('app_id')->constrained('apps')->cascadeOnDelete();
            $table->foreignId('app_review_campaign_id')->nullable()->constrained('app_review_campaigns')->nullOnDelete();
            $table->string('reason_code', 48);
            $table->text('details')->nullable();
            $table->string('status', 24)->default('pending');
            $table->text('admin_notes')->nullable();
            $table->foreignId('reviewed_by_admin_id')->nullable()->constrained('admins')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();
            $table->index(['status', 'created_at']);
            $table->index(['app_id', 'status']);
            $table->index(['reporter_user_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('app_reports');
    }
};
