<?php

use Illuminate\Support\Facades\Route;
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
use App\Http\Controllers\ProfileController;

Route::get('/', [DashboardController::class, 'index'])->name('dashboard');
Route::get('/profile', [ProfileController::class, 'index'])->name('profile');

Route::resource('units', UnitController::class)->only(['index','store','update','destroy']);
Route::resource('tenants', TenantController::class)->only(['index','store','update','destroy']);
Route::resource('leases', LeaseController::class)->only(['index','store','update','destroy']);
Route::resource('payments', PaymentController::class)->only(['index','store','update','destroy']);
Route::resource('maintenance', MaintenanceController::class)->only(['index','store','update','destroy']);
Route::resource('documents', DocumentController::class)->only(['index','store','update','destroy']);
Route::resource('invoices', InvoiceController::class)->only(['index','store','update','destroy']);

Route::get('electricity', [ElectricityController::class, 'index'])->name('electricity');
Route::post('electricity/readings', [ElectricityController::class, 'storeReading'])->name('electricity.readings.store');
Route::post('electricity/outages', [ElectricityController::class, 'storeOutage'])->name('electricity.outages.store');
Route::post('electricity/fuel', [ElectricityController::class, 'storeFuelLog'])->name('electricity.fuel.store');

Route::get('accounting', [AccountingController::class, 'index'])->name('accounting');
Route::post('accounting/accounts', [AccountingController::class, 'storeAccount'])->name('accounting.accounts.store');
Route::post('accounting/journal-entries', [AccountingController::class, 'storeJournalEntry'])->name('accounting.journal.store');
Route::patch('accounting/journal-entries/{journalEntry}', [AccountingController::class, 'updateJournalEntry'])->name('accounting.journal.update');
