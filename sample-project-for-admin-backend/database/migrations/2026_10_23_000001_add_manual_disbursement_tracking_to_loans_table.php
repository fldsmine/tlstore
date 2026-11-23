<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('loans', function (Blueprint $table): void {
            $table->timestamp('paid_at')->nullable()->after('status');
            $table->foreignUuid('paid_by')->nullable()->after('paid_at')->constrained('admins')->nullOnDelete();
            $table->timestamp('exported_at')->nullable()->after('paid_by');
        });
    }

    public function down(): void
    {
        Schema::table('loans', function (Blueprint $table): void {
            $table->dropForeign(['paid_by']);
            $table->dropColumn(['paid_at', 'paid_by', 'exported_at']);
        });
    }
};
