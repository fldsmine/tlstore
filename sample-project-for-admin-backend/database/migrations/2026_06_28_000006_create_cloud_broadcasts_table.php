<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * An admin-triggered "incoming sponsored broadcast". Online users see it as an incoming call
     * (polling); offline users get it on their next visit while it's unexpired. Per-user delivery +
     * response is tracked on the cloud_broadcast_user pivot.
     */
    public function up(): void
    {
        Schema::create('cloud_broadcasts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('cloud_number_id')->nullable()->constrained('cloud_numbers')->nullOnDelete();
            $table->string('number_name');                       // snapshot for history
            $table->string('caller_name')->nullable();           // snapshot
            $table->uuid('created_by')->nullable();              // admin id
            $table->timestamp('expires_at')->index();
            $table->timestamps();
        });

        Schema::create('cloud_broadcast_user', function (Blueprint $table) {
            $table->foreignUuid('cloud_broadcast_id')->constrained('cloud_broadcasts')->cascadeOnDelete();
            $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('status')->default('delivered');      // delivered | answered | declined | missed
            $table->timestamp('responded_at')->nullable();
            $table->timestamps();
            $table->primary(['cloud_broadcast_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cloud_broadcast_user');
        Schema::dropIfExists('cloud_broadcasts');
    }
};
