<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A single shared, ownerless daily "network pool" of calls. It is transient
     * (regenerated/pruned daily) and read by created_at; users claim a share via call_claims.
     */
    public function up(): void
    {
        Schema::create('call_records', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('call_type_id')->nullable()->constrained()->nullOnDelete();
            $table->string('direction')->default('incoming'); // incoming | outgoing
            $table->string('counterparty')->nullable();        // the network number on the call
            $table->decimal('duration_minutes', 10, 2)->default(0);
            $table->decimal('rate_per_minute', 36, 6)->default(0);
            $table->decimal('earnings', 36, 6)->default(0);    // gross network earnings for the call
            $table->timestamps();

            $table->index(['call_type_id', 'created_at']);
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('call_records');
    }
};
