<?php

use App\Http\Controllers\Admin\DownloadCustomerImportTemplateController;
use App\Http\Controllers\Admin\DownloadEmployeeImportTemplateController;
use App\Http\Controllers\Admin\DownloadFuelIntakeImportTemplateController;
use App\Http\Controllers\Admin\DownloadFuelPriceImportTemplateController;
use App\Http\Controllers\Admin\DownloadPinImportTemplateController;
use App\Http\Controllers\Admin\DownloadVehicleImportTemplateController;
use App\Http\Controllers\Admin\ExportCustomerCsvController;
use App\Http\Controllers\Admin\ExportFinanceOrdersCsvController;
use App\Http\Controllers\Admin\ExportLogoOrdersXmlController;
use App\Http\Controllers\Admin\ShipmentPodDeliveryPhotoController;
use App\Http\Controllers\Admin\ShipmentPodPrintController;
use App\Http\Controllers\Admin\ShipmentPodSignatureController;
use App\Http\Controllers\Admin\ShipmentQrSvgController;
use App\Http\Controllers\TrackPublicShipmentController;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    if (Auth::check()) {
        return redirect()->route('dashboard');
    }

    return redirect()->route('login');
})->name('home');

Route::get('/locale/{locale}', function (string $locale) {
    if (! in_array($locale, ['en', 'tr'], true)) {
        abort(404);
    }

    session(['locale' => $locale]);
    App::setLocale($locale);

    return redirect()->back();
})->name('locale.switch');

Route::get('track/shipment/{token}', TrackPublicShipmentController::class)->name('track.shipment');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::livewire('dashboard', 'pages::dashboard')->name('dashboard');

    Route::middleware(['logistics.access'])->group(function () {
        Route::prefix('admin')->name('admin.')->group(function () {
            Route::livewire('customers', 'pages::admin.customers-index')->name('customers.index');
            Route::get('customers/export.csv', ExportCustomerCsvController::class)->name('customers.export.csv');
            Route::get('customers/template.xlsx', DownloadCustomerImportTemplateController::class)->name('customers.template.xlsx');
            Route::livewire('customers/{customer}', 'pages::admin.customer-show')->name('customers.show');
            Route::livewire('vehicles', 'pages::admin.vehicles-index')->name('vehicles.index');
            Route::get('vehicles/template.xlsx', DownloadVehicleImportTemplateController::class)->name('vehicles.template.xlsx');
            Route::livewire('fuel-intakes', 'pages::admin.fuel-intakes-index')->name('fuel-intakes.index');
            Route::get('fuel-intakes/template.xlsx', DownloadFuelIntakeImportTemplateController::class)->name('fuel-intakes.template.xlsx');
            Route::livewire('fuel-prices', 'pages::admin.fuel-prices-index')->name('fuel-prices.index');
            Route::get('fuel-prices/template.xlsx', DownloadFuelPriceImportTemplateController::class)->name('fuel-prices.template.xlsx');
            Route::livewire('employees', 'pages::admin.employees-index')->name('employees.index');
            Route::get('employees/template.xlsx', DownloadEmployeeImportTemplateController::class)->name('employees.template.xlsx');
            Route::livewire('orders', 'pages::admin.orders-index')->name('orders.index');
            Route::get('orders/export-finance.csv', ExportFinanceOrdersCsvController::class)->name('orders.export.finance.csv');
            Route::get('orders/export-logo.xml', ExportLogoOrdersXmlController::class)->name('orders.export.logo.xml');
            Route::livewire('orders/{order}', 'pages::admin.order-show')->name('orders.show');
            Route::livewire('shipments', 'pages::admin.shipments-index')->name('shipments.index');
            Route::livewire('shipments/{shipment}', 'pages::admin.shipment-show')->name('shipments.show');
            Route::get('shipments/{shipment}/qr.svg', ShipmentQrSvgController::class)->name('shipments.qr.svg');
            Route::get('shipments/{shipment}/pod-signature.png', ShipmentPodSignatureController::class)->name('shipments.pod.signature');
            Route::get('shipments/{shipment}/pod-delivery-photo', ShipmentPodDeliveryPhotoController::class)->name('shipments.pod.delivery-photo');
            Route::get('shipments/{shipment}/pod/print', ShipmentPodPrintController::class)->name('shipments.pod.print');
            Route::livewire('delivery-numbers', 'pages::admin.delivery-numbers-index')->name('delivery-numbers.index');
            Route::get('delivery-numbers/template.xlsx', DownloadPinImportTemplateController::class)->name('delivery-numbers.template.xlsx');
            Route::livewire('warehouse', 'pages::admin.warehouse-index')->name('warehouse.index');
            Route::livewire('warehouse/{warehouse}', 'pages::admin.warehouse-show')->name('warehouse.show');
            Route::livewire('finance', 'pages::admin.finance-index')->name('finance.index');
            Route::livewire('finance/reports', 'pages::admin.finance-reports')->name('finance.reports');
            Route::livewire('finance/payment-due-calendar', 'pages::admin.finance-payment-due-calendar')->name('finance.payment-due-calendar');
            Route::livewire('finance/bank-statement-csv', 'pages::admin.bank-statement-csv-import')->name('finance.bank-statement-csv');
            Route::livewire('finance/chart-of-accounts', 'pages::admin.chart-accounts-index')->name('finance.chart-accounts.index');
            Route::livewire('finance/journal-entries', 'pages::admin.journal-entries-index')->name('finance.journal-entries.index');
            Route::livewire('finance/trial-balance', 'pages::admin.finance-trial-balance')->name('finance.trial-balance');
            Route::livewire('finance/balance-sheet', 'pages::admin.finance-balance-sheet')->name('finance.balance-sheet');
            Route::livewire('finance/fiscal-opening-balances', 'pages::admin.fiscal-opening-balances-index')->name('finance.fiscal-opening-balances.index');
        });
    });
});

require __DIR__.'/settings.php';
