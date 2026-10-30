<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
	public function up(): void
	{
		Schema::table('call_records', function (Blueprint $table) {
			$table->decimal('rate_per_minute', 36, 12)
				->default(0)
				->change();

			$table->decimal('earnings', 36, 12)
				->default(0)
				->change();
		});
	}


    /**
     * Reverse the migrations.
     */
	public function down(): void
	{
		Schema::table('call_records', function (Blueprint $table) {
			$table->decimal('rate_per_minute', 36, 6)
				->default(0)
				->change();

			$table->decimal('earnings', 36, 6)
				->default(0)
				->change();
		});
	}
};
