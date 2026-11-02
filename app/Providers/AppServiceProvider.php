<?php

namespace App\Providers;

use App\Models\Lease;
use App\Observers\LeaseObserver;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Lease::observe(LeaseObserver::class);

        // Remember when the queue worker last finished a job, for `php artisan queue:health`.
        \Illuminate\Support\Facades\Event::listen(\Illuminate\Queue\Events\JobProcessed::class, function () {
            \Illuminate\Support\Facades\Cache::put('queue.last_processed_at', now()->toIso8601String(), now()->addDays(7));
        });
    }
}
