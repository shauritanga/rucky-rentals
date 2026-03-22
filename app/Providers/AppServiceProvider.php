<?php

namespace App\Providers;

use App\Models\Invoice;
use App\Models\MaintenanceRecord;
use App\Models\Payment;
use App\Observers\InvoiceObserver;
use App\Observers\MaintenanceRecordObserver;
use App\Observers\PaymentObserver;
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
        // Register model observers for automatic accounting event handling
        Invoice::observe(InvoiceObserver::class);
        Payment::observe(PaymentObserver::class);
        MaintenanceRecord::observe(MaintenanceRecordObserver::class);
    }
}
