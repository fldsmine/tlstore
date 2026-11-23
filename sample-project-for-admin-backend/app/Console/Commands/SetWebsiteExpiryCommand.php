<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

#[Signature('website:backfill-expiry {--force : Skip the confirmation prompt} {--overwrite : Also reset users who already have an expiry set}')]
#[Description("Set each existing user's personalized-website expiry to the later of the grace floor (settings.website.expiry_floor) and their registration date + the configured duration (settings.website.duration_days).")]
class SetWebsiteExpiryCommand extends Command
{
    public function handle(): int
    {
        $days = (int) config('settings.website.duration_days', 30);

        if ($days <= 0) {
            $this->error('settings.website.duration_days must be a positive number of days.');

            return self::FAILURE;
        }

        // Same grace floor the first-activation stamp uses: nobody's window ends before this date.
        $floor = config('settings.website.expiry_floor');
        $floorAt = filled($floor) ? Carbon::parse($floor)->endOfDay() : null;

        $query = User::query()->whereNotNull('created_at');
        if (! $this->option('overwrite')) {
            $query->whereNull('website_expires_at');
        }

        $count = (clone $query)->count();
        if ($count === 0) {
            $this->info('No users to update.');

            return self::SUCCESS;
        }

        $rule = $floorAt !== null
            ? "max({$floorAt->toDateString()}, registration date + {$days} days)"
            : "registration date + {$days} days";

        if (! $this->option('force') && ! $this->confirm("Set website expiry ({$rule}) for {$count} user(s)?")) {
            $this->warn('Aborted — nothing changed.');

            return self::SUCCESS;
        }

        $now = now();
        $updated = 0;
        $alreadyExpired = 0;

        $query->select('id', 'created_at')->chunkById(500, function ($users) use ($days, $floorAt, $now, &$updated, &$alreadyExpired): void {
            foreach ($users as $user) {
                $expiry = $user->created_at->copy()->addDays($days);
                if ($floorAt !== null && $floorAt->greaterThan($expiry)) {
                    $expiry = $floorAt->copy();
                }

                $user->updateQuietly(['website_expires_at' => $expiry]);
                $updated++;

                if ($expiry->lt($now)) {
                    $alreadyExpired++;
                }
            }
        });

        $this->info("Set website expiry for {$updated} user(s) — {$rule}.");

        if ($alreadyExpired > 0) {
            $this->warn("{$alreadyExpired} user(s) still resolved to a past date and will need renewal.");
        }

        return self::SUCCESS;
    }
}
