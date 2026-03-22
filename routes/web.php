<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use AshAllenDesign\LaravelExchangeRates\Classes\ExchangeRate;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\UnitController;
use App\Http\Controllers\TenantController;
use App\Http\Controllers\LeaseController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\MaintenanceController;
use App\Http\Controllers\DocumentController;
use App\Http\Controllers\InvoiceController;
use App\Http\Controllers\ElectricityController;
use App\Http\Controllers\AccountingController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\SuperuserController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\TeamController;
use App\Http\Controllers\AuditTrailController;

Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login'])->name('login.attempt');
});

Route::middleware('auth')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
    Route::get('/first-password-change', [AuthController::class, 'showForcePasswordChange'])->name('password.force');
    Route::post('/first-password-change', [AuthController::class, 'forcePasswordChange'])->name('password.force.update');

    Route::middleware('force.password.change')->group(function () {
        Route::get('/', [DashboardController::class, 'index'])->name('dashboard');
        Route::get('/profile', [ProfileController::class, 'index'])->name('profile');
        Route::get('/superuser/profile', [ProfileController::class, 'superuser'])->name('superuser.profile');

        Route::resource('units', UnitController::class)->only(['index', 'store', 'update', 'destroy']);
        Route::resource('tenants', TenantController::class)->only(['index', 'store', 'update', 'destroy']);
        Route::resource('leases', LeaseController::class)->only(['index', 'store', 'update', 'destroy']);
        Route::resource('payments', PaymentController::class)->only(['index', 'store', 'update', 'destroy']);
        Route::resource('maintenance', MaintenanceController::class)->only(['index', 'store', 'update', 'destroy']);
        Route::resource('documents', DocumentController::class)->only(['index', 'store', 'update', 'destroy']);
        Route::resource('invoices', InvoiceController::class)->only(['index', 'store', 'update', 'destroy']);
        Route::get('team', [TeamController::class, 'index'])->name('team.index');
        Route::post('team', [TeamController::class, 'store'])->name('team.store');
        Route::patch('team/{user}/permissions', [TeamController::class, 'updatePermissions'])->name('team.permissions.update');
        Route::patch('team/{user}/status', [TeamController::class, 'toggleStatus'])->name('team.status.toggle');
        Route::patch('team/{userId}/restore', [TeamController::class, 'restore'])->name('team.restore');
        Route::delete('team/{user}', [TeamController::class, 'destroy'])->name('team.destroy');
        Route::get('audit', [AuditTrailController::class, 'index'])->name('audit.index');

        Route::get('electricity', [ElectricityController::class, 'index'])->name('electricity');
        Route::post('electricity/readings', [ElectricityController::class, 'storeReading'])->name('electricity.readings.store');
        Route::post('electricity/outages', [ElectricityController::class, 'storeOutage'])->name('electricity.outages.store');
        Route::post('electricity/fuel', [ElectricityController::class, 'storeFuelLog'])->name('electricity.fuel.store');

        Route::get('accounting', [AccountingController::class, 'index'])->name('accounting');
        Route::get('reports', [ReportController::class, 'index'])->name('reports');
        Route::get('superuser', [SuperuserController::class, 'index'])->name('superuser.index');
        Route::post('superuser/properties', [SuperuserController::class, 'storeProperty'])->name('superuser.properties.store');
        Route::post('superuser/managers', [SuperuserController::class, 'storeManager'])->name('superuser.managers.store');
        Route::patch('superuser/properties/{property}/assign-manager', [SuperuserController::class, 'assignManager'])->name('superuser.properties.assign-manager');
        Route::post('accounting/accounts', [AccountingController::class, 'storeAccount'])->name('accounting.accounts.store');
        Route::post('accounting/journal-entries', [AccountingController::class, 'storeJournalEntry'])->name('accounting.journal.store');
        Route::patch('accounting/journal-entries/{journalEntry}', [AccountingController::class, 'updateJournalEntry'])->name('accounting.journal.update');

        Route::get('exchange-rate', function (Request $request, ExchangeRate $exchangeRate) {
            $base = strtoupper($request->query('base', 'USD'));
            $target = strtoupper($request->query('target', 'TZS'));

            $cacheKey = sprintf('exchange_rate:%s:%s', $base, $target);
            $fallbackRate = (float) config('app.fx_fallback_rate', 2650);

            try {
                $rate = (float) $exchangeRate->exchangeRate($base, $target);
                if ($rate <= 0) {
                    throw new \RuntimeException('Invalid exchange rate value returned by provider.');
                }

                Cache::put($cacheKey, $rate, now()->addDay());
            } catch (\Throwable $e) {
                $rate = (float) Cache::get($cacheKey, $fallbackRate);

                Log::warning('Exchange rate provider unavailable, using cached/fallback value.', [
                    'base' => $base,
                    'target' => $target,
                    'error' => $e->getMessage(),
                    'fallback_used' => true,
                ]);
            }

            return response()->json([
                'base' => $base,
                'target' => $target,
                'rate' => $rate,
                'fetched_at' => now()->toIso8601String(),
            ]);
        })->name('exchange-rate');
    });
});
