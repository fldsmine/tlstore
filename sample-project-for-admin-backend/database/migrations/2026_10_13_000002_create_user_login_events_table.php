<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * One row per sign-in.
     *
     * users.last_login_at is an overwrite — it only ever remembers the most recent
     * sign-in, so "who signed in today" becomes unanswerable the moment someone signs
     * in again tomorrow, and a repeat sign-in leaves no trace at all. The backoffice
     * sign-in report needs the events themselves, so every login now appends here
     * (see LoginService::touchLastLogin()).
     */
    public function up(): void
    {
        Schema::create('user_login_events', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('user_id')->index()->constrained()->cascadeOnDelete();
            $table->string('channel', 24)->default('web'); // web | app | telegram
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamp('created_at')->nullable()->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_login_events');
    }
};
