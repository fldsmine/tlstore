<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('tasks:expire-reservations')->everyMinute();
Schedule::command('rankings:check-eligibility')->dailyAt('00:05');
Schedule::command('rankings:reward-monthly')->dailyAt('00:10');
Schedule::command('demo:generate-listings')
    ->dailyAt('00:20')
    ->environments(['local', 'testing', 'staging'])
    ->withoutOverlapping();
Schedule::command('app:auto-approve-ad-shares')
    ->everyMinute()
    ->environments(['local', 'testing', 'staging'])
    ->withoutOverlapping();
Schedule::command('app:auto-approve-app-reviews')
    ->everyMinute()
    ->environments(['local', 'testing', 'staging'])
    ->withoutOverlapping();
