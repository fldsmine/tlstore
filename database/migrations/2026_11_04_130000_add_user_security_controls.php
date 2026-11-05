<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->string('security_pin', 255)->nullable()->after('password');
            $table->boolean('two_factor')->default(false)->after('security_pin');
            $table->text('two_factor_secret')->nullable()->after('two_factor');
            $table->timestamp('two_factor_setup_started_at')->nullable()->after('two_factor_secret');
            $table->timestamp('two_factor_enabled_at')->nullable()->after('two_factor_setup_started_at');
            $table->text('two_factor_recovery_codes')->nullable()->after('two_factor_enabled_at');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn([
                'security_pin',
                'two_factor',
                'two_factor_secret',
                'two_factor_setup_started_at',
                'two_factor_enabled_at',
                'two_factor_recovery_codes',
            ]);
        });
    }
};
