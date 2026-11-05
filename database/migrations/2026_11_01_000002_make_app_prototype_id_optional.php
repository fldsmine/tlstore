<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('apps', function (Blueprint $table): void {
            $table->unsignedInteger('prototype_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        $used = DB::table('apps')->whereNotNull('prototype_id')->pluck('prototype_id')->map(fn ($id) => (int) $id)->all();
        $next = $used === [] ? 1 : max($used) + 1;
        foreach (DB::table('apps')->whereNull('prototype_id')->orderBy('id')->pluck('id') as $id) {
            while (in_array($next, $used, true)) $next++;
            DB::table('apps')->where('id', $id)->update(['prototype_id' => $next]);
            $used[] = $next++;
        }

        Schema::table('apps', function (Blueprint $table): void {
            $table->unsignedInteger('prototype_id')->nullable(false)->change();
        });
    }
};
