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
    }
}
