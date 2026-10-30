<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Pre-aggregated daily rollups for the Backoffice Reports page. One row per (day, metric, bucket)
     * lets the report read a few hundred summary rows instead of scanning millions of transactions /
     * call_claims. Rebuilt incrementally by reports:rollup. Historical days are immutable.
     */
    public function up(): void
    {
        Schema::create('report_daily_stats', function (Blueprint $table): void {
            $table->id();
            $table->date('day');
            $table->string('metric', 40);
            $table->string('bucket', 64)->default(''); // sub-key: type / wallet / level / call_type_id ('' when none)
            $table->unsignedBigInteger('cnt')->default(0);
            $table->decimal('amount', 36, 6)->default(0);
            $table->timestamps();

            $table->unique(['day', 'metric', 'bucket']);
            $table->index(['metric', 'day']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('report_daily_stats');
    }
};
