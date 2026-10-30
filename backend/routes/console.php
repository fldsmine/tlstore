<?php

use Illuminate\Support\Facades\Schedule;

// Scheduled tasks — grouped by cadence; the daily block is ordered by run time
// so collisions are easy to spot. Frequent workers use withoutOverlapping().

// Every five minutes
Schedule::command('vtu:requery-pending')->everyFiveMinutes()->withoutOverlapping();
Schedule::command('payments:requery-pending')->everyFiveMinutes()->withoutOverlapping();
Schedule::command('mailing:dispatch-scheduled')->everyMinute()->withoutOverlapping();

// Pre-aggregate the heavy Backoffice Reports metrics into report_daily_stats. Recomputes the last
// few days (cheap) so past days stay exact; the report computes today live, so figures are real-time.
Schedule::command('reports:rollup')->hourly()->withoutOverlapping();

Schedule::command('ads:grow')->hourly();

Schedule::command('vas:cache-catalog')->everyFourHours()->withoutOverlapping()->when(fn (): bool => (bool) config('settings.vtu.enabled'));

// One daily pool; earnings/shareholder views lazily generate it if the scheduler is unavailable.
Schedule::command('calls:generate')->dailyAt('00:00')->withoutOverlapping();

Schedule::command('calls:prune')->dailyAt('00:01');
Schedule::command('rewards:settle')->dailyAt('00:05');
Schedule::command('paylater:forfeit-overdue')->dailyAt('00:10');
Schedule::command('system:prune')->dailyAt('00:20');
Schedule::command('livewire:prune-uploads')->dailyAt('00:25');
Schedule::command('telescope:prune --hours=48')->dailyAt('00:30');
Schedule::command('chats:prune')->dailyAt('00:35');

// Weekly (Sunday 00:00)
Schedule::command('ads:prune-shares')->weeklyOn(0, '00:00');

// Backups (only when enabled in admin settings)
Schedule::command('backup:clean')->dailyAt('01:00')->when(fn (): bool => (bool) config('settings.backup.enabled'));
Schedule::command('backup:run')->dailyAt('01:30')->when(fn (): bool => (bool) config('settings.backup.enabled'));
