<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // An admin distribution: pick a Cloud contact, type an amount, choose promoter|shareholder →
        // ONE row. Savers' claimable bonuses are computed on demand (no per-saver fan-out).
        Schema::create('cloud_distributions', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('cloud_number_id')->nullable()->index();
            $table->string('type'); // promoter | shareholder
            $table->decimal('amount', 36, 6); // per-saver amount
            $table->string('sender_name');       // the contact's brand/name at send time
            $table->string('avatar')->nullable();
            $table->string('note')->nullable();
            $table->uuid('created_by')->nullable();
            $table->unsignedInteger('recipients_count')->default(0); // saver count snapshot (info only)
            $table->timestamps();
        });

        // Written ONLY when a saver actually claims a distribution — prevents double-claim + audits it.
        Schema::create('cloud_bonus_claims', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained()->cascadeOnDelete();
            $table->uuid('cloud_distribution_id')->index();
            $table->uuid('cloud_number_id')->nullable();
            $table->string('type');
            $table->decimal('amount', 36, 6);
            $table->string('sender_name');
            $table->string('avatar')->nullable();
            $table->timestamp('claimed_at')->nullable();
            $table->timestamps();

            // One claim per saver per distribution — the idempotency guard.
            $table->unique(['cloud_distribution_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cloud_bonus_claims');
        Schema::dropIfExists('cloud_distributions');
    }
};
