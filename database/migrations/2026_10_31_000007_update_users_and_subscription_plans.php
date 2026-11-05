<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->renameColumn('name', 'full_name');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->string('username', 80)->unique()->after('full_name');
            $table->string('phone', 32)->nullable()->after('email');
            $table->string('avatar', 512)->nullable();
            $table->string('cover_image', 512)->nullable();
            $table->boolean('is_banned')->default(false);
            $table->text('ban_reason')->nullable();
        });

        Schema::table('subscription_plans', function (Blueprint $table) {
            $table->double('discount')->default(0)->comment('Discount if the package has one');
            $table->double('bonus')->default(0)->comment('Welcome bonus awarded for purchasing the package');
            $table->integer('is_special')->default(0)->comment('Whether the package is featured: 0 or 1');
        });
    }

    public function down(): void
    {
        Schema::table('subscription_plans', function (Blueprint $table) {
            $table->dropColumn(['discount', 'bonus', 'is_special']);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['username']);
            $table->dropColumn(['username', 'phone', 'avatar', 'cover_image', 'is_banned', 'ban_reason']);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->renameColumn('full_name', 'name');
        });
    }
};
