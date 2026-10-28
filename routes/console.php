<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Process queued jobs every minute.
// --stop-when-empty  → exits when queue is empty (safe for cron)
// --max-jobs=50      → safety cap per run
// --max-time=55      → exits after 55 s so it never overlaps the next cron tick
// withoutOverlapping → prevents stacking if the previous run is still alive
Schedule::command('queue:work --stop-when-empty --max-jobs=50 --max-time=55')
    ->everyMinute()
    ->withoutOverlapping()
    ->runInBackground();

// Daily database backup at 02:00: kept on disk (14 days) and emailed to the superusers.
Schedule::command('backup:database')
    ->dailyAt('02:00')
    ->withoutOverlapping()
    ->onFailure(fn () => \Illuminate\Support\Facades\Log::error('Daily database backup failed'));
