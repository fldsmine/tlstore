<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The webpush package's migration created subscribable_id via morphs() = unsigned BIGINT, but our
     * users are UUID-keyed (HasUuids), so every push subscribe truncated the 36-char id and failed
     * ("Data truncated for column 'subscribable_id'"). No usable rows exist, so drop and recreate the
     * table with uuidMorphs (char(36) subscribable_id).
     */
    public function up(): void
    {
        $connection = config('webpush.database_connection');
        $tableName = config('webpush.table_name');

        Schema::connection($connection)->dropIfExists($tableName);

        Schema::connection($connection)->create($tableName, function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->uuidMorphs('subscribable', 'push_subscriptions_subscribable_morph_idx');
            $table->string('endpoint', 500)->unique();
            $table->string('public_key')->nullable();
            $table->string('auth_token')->nullable();
            $table->string('content_encoding')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        $connection = config('webpush.database_connection');
        $tableName = config('webpush.table_name');

        Schema::connection($connection)->dropIfExists($tableName);

        Schema::connection($connection)->create($tableName, function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->morphs('subscribable', 'push_subscriptions_subscribable_morph_idx');
            $table->string('endpoint', 500)->unique();
            $table->string('public_key')->nullable();
            $table->string('auth_token')->nullable();
            $table->string('content_encoding')->nullable();
            $table->timestamps();
        });
    }
};
