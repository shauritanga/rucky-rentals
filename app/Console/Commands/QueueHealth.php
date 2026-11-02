<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class QueueHealth extends Command
{
    protected $signature = 'queue:health';

    protected $description = 'Show whether the queue worker is running: waiting jobs, failed jobs, and when it last sent something';

    public function handle(): int
    {
        $connection = config('queue.default');
        $this->line("Queue connection: {$connection}");

        if ($connection === 'database' && Schema::hasTable('jobs')) {
            $waiting = DB::table('jobs')->count();
            $oldest = DB::table('jobs')->min('created_at');
            $this->line("Waiting jobs: {$waiting}" . ($oldest ? ' (oldest waiting ' . now()->diffForHumans(\Carbon\Carbon::createFromTimestamp($oldest), true) . ')' : ''));
        }

        $failed = Schema::hasTable('failed_jobs') ? DB::table('failed_jobs')->count() : 0;
        $this->line("Failed jobs: {$failed}" . ($failed ? '  (php artisan queue:failed to see them, php artisan queue:retry all to try again)' : ''));

        $last = Cache::get('queue.last_processed_at');
        if (! $last) {
            $this->warn('The worker has not processed any job yet (or the cache was cleared). Create something that sends a notification, wait a minute, then run this again.');

            return self::SUCCESS;
        }

        $age = abs(\Carbon\Carbon::parse($last)->diffInMinutes(now()));
        $this->info('Last job processed: ' . \Carbon\Carbon::parse($last)->diffForHumans());
        if (isset($waiting) && $waiting > 0 && $age > 5) {
            $this->error('Jobs are waiting but nothing was processed for over 5 minutes: the scheduler cron or the worker is not running.');

            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
