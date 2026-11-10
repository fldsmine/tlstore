<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * system_settings
 *
 * Key/value application settings (payment gateways, about page copy, reward switches, ...).
 *
 * Relationships:
 *   - updated_by_admin_id -> admins.id (null on delete)
 *
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('system_settings', function (Blueprint $table) {
            $table->id();
            $table->string('setting_key', 120)->unique();
            $table->json('value')->nullable();
            $table->boolean('is_public')->default(false);
            $table->foreignId('updated_by_admin_id')->nullable()->constrained('admins')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('system_settings');
    }
};
