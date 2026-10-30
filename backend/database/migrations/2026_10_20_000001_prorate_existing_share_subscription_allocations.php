<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Carbon;

return new class extends Migration
{
    /**
     * Before recurring monthly quotas were prorated, allocated_minutes stored one month's
     * configured quota even when the share term lasted longer or shorter than 30 days. Convert
     * active snapshots once, preserving their stored monthly value and already-recorded usage.
     */
    public function up(): void
    {
        DB::table('share_subscriptions')
            ->where('status', 'active')
            ->whereNotNull('starts_at')
            ->whereNotNull('expires_at')
            ->where('expires_at', '>', now())
            ->orderBy('id')
            ->chunkById(100, function ($subscriptions): void {
                foreach ($subscriptions as $subscription) {
                    $startsAt = Carbon::parse($subscription->starts_at);
                    $expiresAt = Carbon::parse($subscription->expires_at);
                    $termDays = max(1, (int) round(($expiresAt->getTimestamp() - $startsAt->getTimestamp()) / 86400));
                    $allocated = round((float) $subscription->allocated_minutes * $termDays / 30, 2);

                    DB::table('share_subscriptions')
                        ->where('id', $subscription->id)
                        ->update(['allocated_minutes' => $allocated]);
                }
            }, 'id');
    }

    /**
     * This balance correction is intentionally irreversible: claims may have been made against
     * the converted allocation after deployment, so dividing it back could erase earned quota.
     */
    public function down(): void {}
};
