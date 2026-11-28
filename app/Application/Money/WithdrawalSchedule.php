<?php

namespace App\Application\Money;

use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Log;

/**
 * Parses `subscription_plans.withdrawal_days` and decides whether a withdrawal
 * may be requested today.
 *
 * Supported tokens (case-insensitive):
 *   - `daily`, `any`, `everyday`           -> always allowed
 *   - `monday` … `sunday`, `mon` … `sun`   -> that weekday
 *   - `monthly 28th` / `monthly 28`        -> that day of the month
 *   - `monthly last` / `month end`         -> the last day of the month
 *   - `monthly` (on its own)               -> no specific day; treated as allowed
 *
 * Anything else is ignored. If a schedule contains *nothing* recognisable the
 * day is allowed and a warning is logged: a typo in plan data must never lock a
 * user out of their own balance.
 */
final class WithdrawalSchedule
{
    private const ALWAYS_ALLOWED = ['daily', 'any', 'anytime', 'everyday', 'every_day'];

    private const WEEKDAYS = [
        'monday' => 1, 'mon' => 1,
        'tuesday' => 2, 'tue' => 2, 'tues' => 2,
        'wednesday' => 3, 'wed' => 3,
        'thursday' => 4, 'thu' => 4, 'thur' => 4, 'thurs' => 4,
        'friday' => 5, 'fri' => 5,
        'saturday' => 6, 'sat' => 6,
        'sunday' => 7, 'sun' => 7,
    ];

    /** @param array<int, string>|null $days */
    public static function allows(?array $days, CarbonInterface $date): bool
    {
        $days = array_values(array_filter(array_map(
            static fn ($day): string => strtolower(trim((string) $day)),
            $days ?? [],
        ), static fn (string $day): bool => $day !== ''));

        if ($days === []) {
            return true;
        }

        $recognised = 0;

        foreach ($days as $day) {
            if (in_array($day, self::ALWAYS_ALLOWED, true)) {
                return true;
            }

            if (isset(self::WEEKDAYS[$day])) {
                $recognised++;
                if ($date->isoWeekday() === self::WEEKDAYS[$day]) {
                    return true;
                }

                continue;
            }

            // `monthly 28th`, `monthly 28`
            if (preg_match('/^monthly\s+(\d{1,2})(?:st|nd|rd|th)?$/', $day, $matches) === 1) {
                $recognised++;
                if ($date->day === (int) $matches[1]) {
                    return true;
                }

                continue;
            }

            // `monthly last`, `month end`
            if (in_array($day, ['monthly last', 'month end', 'last day'], true)) {
                $recognised++;
                if ($date->day === $date->daysInMonth) {
                    return true;
                }

                continue;
            }

            // A bare `monthly` states no particular day; it is display metadata
            // and must not block a withdrawal.
            if ($day === 'monthly') {
                return true;
            }
        }

        if ($recognised === 0) {
            Log::warning('Unrecognised withdrawal schedule; allowing the request.', ['days' => $days]);

            return true;
        }

        return false;
    }

    /**
     * Human description for validation errors ("Friday and Wednesday").
     *
     * @param array<int, string>|null $days
     */
    public static function describe(?array $days): string
    {
        $days = array_values(array_filter(array_map(
            static fn ($day): string => trim((string) $day),
            $days ?? [],
        ), static fn (string $day): bool => $day !== ''));

        return $days === [] ? 'any day' : implode(' and ', $days);
    }
}
