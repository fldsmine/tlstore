<?php

namespace App\Services\Mailing;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;

class MailingDispatchSchedule
{
    private const NEXT_SLOT_KEY = 'mailing.dispatch.next_slot';

    private const LOCK_KEY = 'mailing.dispatch.schedule_lock';

    /** @return list<CarbonImmutable> */
    public function reserveSlots(int $count): array
    {
        if ($count < 1) {
            return [];
        }

        $rate = max(1, (int) config('mailing.rate_per_minute', 60));
        // Queue backends store availability at whole-second precision; round conservatively.
        $spacingSeconds = max(1, (int) ceil(60 / $rate));

        return Cache::lock(self::LOCK_KEY, 15)->block(10, function () use ($count, $spacingSeconds): array {
            $now = now()->timestamp;
            $firstSlot = max($now, (int) Cache::get(self::NEXT_SLOT_KEY, $now));
            $timezone = (string) config('app.timezone', 'UTC');
            $slots = [];

            for ($offset = 0; $offset < $count; $offset++) {
                $slots[] = CarbonImmutable::createFromTimestamp($firstSlot + ($offset * $spacingSeconds), $timezone);
            }

            Cache::forever(self::NEXT_SLOT_KEY, $firstSlot + ($count * $spacingSeconds));

            return $slots;
        });
    }
}
