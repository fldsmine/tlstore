<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Pay Later availability is an admin feature flag (config/settings.php → settings.paylater.*),
        // not a per-package switch.
        Schema::create('minute_credits', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('additional_minute_plan_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignUuid('minute_top_up_id')->nullable()->constrained()->nullOnDelete(); // the granted minutes
            $table->string('plan_name');                          // snapshot
            $table->decimal('amount', 36, 6)->default(0);         // to repay (= plan price; interest-free, no fee)
            $table->decimal('minutes', 10, 2)->default(0);        // daily minutes bought on credit
            $table->date('due_date');
            $table->string('status')->default('active');          // active | repaid | defaulted (instant, no approval)
            $table->timestamp('repaid_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('minute_credits');
    }
};
