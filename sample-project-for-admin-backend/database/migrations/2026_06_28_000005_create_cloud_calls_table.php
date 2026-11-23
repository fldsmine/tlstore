<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A Nextel Cloud call session. Outgoing = user dialled a sponsor number; incoming = an admin
     * broadcast the user answered. Earnings are credited on completion (capped by the plan's daily max).
     */
    public function up(): void
    {
        Schema::create('cloud_calls', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignUuid('cloud_number_id')->nullable()->constrained('cloud_numbers')->nullOnDelete();
            $table->string('number_name');                       // snapshot — survives number deletion
            $table->string('direction')->default('outgoing');    // outgoing | incoming
            $table->string('status')->default('active');         // active | completed | missed | declined
            $table->decimal('earned', 36, 6)->default(0);
            $table->unsignedInteger('duration_seconds')->default(0);
            $table->unsignedInteger('required_listen_seconds')->default(0); // server-set at start; credit only if listened ≥ this
            $table->timestamp('started_at')->nullable();
            $table->timestamp('ended_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'status']);
            $table->index(['user_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cloud_calls');
    }
};
