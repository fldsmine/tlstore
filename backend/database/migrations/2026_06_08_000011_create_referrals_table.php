<?php

use App\Enums\ReferralStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('referrals', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('referrer_id')->constrained('users')->cascadeOnDelete();
            $table->foreignUuid('referee_id')->constrained('users')->cascadeOnDelete();
            $table->string('status')->default(ReferralStatus::PENDING->value);
            $table->string('reference')->unique();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['referrer_id', 'referee_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('referrals');
    }
};
