<?php

use App\Http\Controllers\Admin\DownloadCustomerImportTemplateController;
use App\Http\Controllers\Admin\DownloadPinImportTemplateController;
use App\Http\Controllers\Admin\ExportCustomerCsvController;
use App\Http\Controllers\Admin\ExportFinanceOrdersCsvController;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Route;

Route::view('/', 'welcome')->name('home');

Route::get('/locale/{locale}', function (string $locale) {
    if (! in_array($locale, ['en', 'tr'], true)) {
        abort(404);
    }

    session(['locale' => $locale]);
    App::setLocale($locale);

    return redirect()->back();
})->name('locale.switch');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::livewire('dashboard', 'pages::dashboard')->name('dashboard');

    Route::middleware(['logistics.access'])->group(function () {
        Route::prefix('admin')->name('admin.')->group(function () {
            Route::livewire('customers', 'pages::admin.customers-index')->name('customers.index');
            Route::get('customers/export.csv', ExportCustomerCsvController::class)->name('customers.export.csv');
            Route::get('customers/template.xlsx', DownloadCustomerImportTemplateController::class)->name('customers.template.xlsx');
            Route::livewire('vehicles', 'pages::admin.vehicles-index')->name('vehicles.index');
            Route::livewire('orders', 'pages::admin.orders-index')->name('orders.index');
            Route::get('orders/export-finance.csv', ExportFinanceOrdersCsvController::class)->name('orders.export.finance.csv');
            Route::livewire('shipments', 'pages::admin.shipments-index')->name('shipments.index');
            Route::livewire('shipments/{shipment}', 'pages::admin.shipment-show')->name('shipments.show');
            Route::livewire('delivery-numbers', 'pages::admin.delivery-numbers-index')->name('delivery-numbers.index');
            Route::get('delivery-numbers/template.xlsx', DownloadPinImportTemplateController::class)->name('delivery-numbers.template.xlsx');
            Route::livewire('finance', 'pages::admin.finance-index')->name('finance.index');
        });
    });
});

require __DIR__.'/settings.php';
