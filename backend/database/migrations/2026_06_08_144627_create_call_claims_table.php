<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Per-user claim ledger over the shared call_records pool. A user claims a given pool
     * call at most once; the amount is their share of that call's gross earnings.
     */
    public function up(): void
    {
        Schema::create('call_claims', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('call_record_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignUuid('call_type_id')->nullable()->constrained()->nullOnDelete();
            $table->uuid('minute_top_up_id')->nullable()->index(); // which additional-minute top-up funded this claim (null = base-package allowance)
            $table->decimal('duration_minutes', 10, 2)->default(0); // snapshot, for the daily allowance
            $table->decimal('amount', 36, 6)->default(0);           // the user's claimed share
            $table->string('network')->nullable();
            $table->timestamp('claimed_at');
            $table->timestamps();

            // A user can claim a given pool call at most once (NULLs allowed for pruned/legacy rows).
            $table->unique(['user_id', 'call_record_id']);
            $table->index(['user_id', 'claimed_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('call_claims');
    }
};
