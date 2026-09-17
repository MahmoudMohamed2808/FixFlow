<?php

use App\Http\Controllers\InvoicePrintController;
use App\Http\Controllers\ReportsExportController;
use Illuminate\Support\Facades\Route;

Route::view('/', 'welcome')->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::livewire('/dashboard', 'pages::dashboard')->name('dashboard');

    Route::livewire('/assets', 'pages::assets.index')->name('assets.index');
    Route::livewire('/assets/{asset}', 'pages::assets.show')->name('assets.show');
    Route::livewire('/work-orders', 'pages::work-orders.index')->name('work-orders.index');
    Route::livewire('/work-orders/{workOrder}', 'pages::work-orders.show')->name('work-orders.show');
    Route::livewire('/schedules', 'pages::schedules.index')->name('schedules.index');
    Route::livewire('/inventory', 'pages::inventory.index')->name('inventory.index');
    Route::livewire('/suppliers', 'pages::suppliers.index')->name('suppliers.index');
    Route::livewire('/reports', 'pages::reports.index')->name('reports.index');

    Route::livewire('/clients', 'pages::clients.index')->name('clients.index');
    Route::livewire('/invoices', 'pages::invoices.index')->name('invoices.index');
    Route::livewire('/invoices/{invoice}', 'pages::invoices.show')->name('invoices.show');
    Route::get('/invoices/{invoice}/print', InvoicePrintController::class)
        ->middleware('can:invoices.view')
        ->name('invoices.print');
    Route::livewire('/budgets', 'pages::budgets.index')->name('budgets.index');
    Route::get('/reports/export', ReportsExportController::class)
        ->middleware('can:reports.view')
        ->name('reports.export');

    Route::livewire('/users', 'pages::users.index')
        ->middleware('can:users.manage')
        ->name('users.index');

    Route::livewire('/notifications', 'pages::notifications.index')->name('notifications.index');

    Route::livewire('/activity-logs', 'pages::activity-logs.index')
        ->middleware('can:users.manage')
        ->name('activity-logs.index');
});

require __DIR__.'/settings.php';
