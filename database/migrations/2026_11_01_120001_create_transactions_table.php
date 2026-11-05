<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('type');                       // App\Enums\TransactionType
            $table->string('direction');                  // credit | debit
            $table->decimal('amount', 36, 6)->default(0); // always positive; direction decides sign
            $table->decimal('fee', 36, 6)->default(0);    // processing fee (e.g. withdrawals); net = amount - fee
            $table->string('wallet')->nullable();         // which wallet was affected
            $table->string('status')->default('completed'); // completed | pending | failed
            $table->string('reference')->unique();
            $table->string('description')->nullable();
            $table->json('meta')->nullable();             // e.g. { network, call_type }
            $table->timestamps();

            $table->index(['user_id', 'created_at']);
            $table->index(['user_id', 'type']);
            $table->index(['user_id', 'direction', 'created_at']); // today's-credit SUM (Dashboard/Analytics)
            $table->index(['user_id', 'status']);                  // pending/withdrawn status SUMs
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transactions');
    }
};
