<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('withdrawal_options', function (Blueprint $table) {
            $table->id();
            $table->string('option_id', 191)->unique();
            $table->string('name', 191);
            $table->string('icon', 191)->nullable();
            $table->text('description')->nullable();
            $table->longText('fields');
            $table->decimal('tax', 5, 2)->default(0.00);
            $table->decimal('min_withdrawal', 5, 2)->default(5.00);
            $table->boolean('is_available')->default(true);
            $table->integer('order')->default(0);
            $table->timestamps();
        });

        /*DB::statement(
            'ALTER TABLE `withdrawal_options` ADD CONSTRAINT `withdrawal_options_chk_1` CHECK (JSON_VALID(`fields`))'
        );*/
    }

    public function down(): void
    {
        Schema::dropIfExists('withdrawal_options');
    }
};
