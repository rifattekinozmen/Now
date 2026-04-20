<?php

use App\Http\Controllers\Admin\DownloadCustomerImportTemplateController;
use App\Http\Controllers\Admin\DownloadDocumentController;
use App\Http\Controllers\Admin\DownloadEmployeeImportTemplateController;
use App\Http\Controllers\Admin\DownloadFuelIntakeImportTemplateController;
use App\Http\Controllers\Admin\DownloadFuelPriceImportTemplateController;
use App\Http\Controllers\Admin\DownloadPinImportTemplateController;
use App\Http\Controllers\Admin\DownloadVehicleImportTemplateController;
use App\Http\Controllers\Admin\ExportCustomerCsvController;
use App\Http\Controllers\Admin\ExportFinanceOrdersCsvController;
use App\Http\Controllers\Admin\ExportLogoOrdersXmlController;
use App\Http\Controllers\Admin\ExportPaymentsCsvController;
use App\Http\Controllers\Admin\ExportVouchersCsvController;
use App\Http\Controllers\Admin\PayrollPrintController;
use App\Http\Controllers\Admin\ShipmentPodDeliveryPhotoController;
use App\Http\Controllers\Admin\ShipmentPodPrintController;
use App\Http\Controllers\Admin\ShipmentPodSignatureController;
use App\Http\Controllers\Admin\ShipmentQrSvgController;
use App\Http\Controllers\Personnel\PersonnelPayrollPrintController;
use App\Http\Controllers\SwitchTenantController;
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

    if ($user = auth()->user()) {
        foreach (['tr', 'en'] as $lang) {
            cache()->forget('sidebar-menu-v4-'.$user->id.'-'.$lang);
            cache()->forget('orders-stats-'.$user->tenant_id.'-'.$lang);
        }
    }

    return redirect()->back();
})->name('locale.switch');

Route::get('track/shipment/{token}', TrackPublicShipmentController::class)->name('track.shipment');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::post('tenant/switch/{tenant}', SwitchTenantController::class)->name('tenant.switch');

    Route::livewire('dashboard', 'pages::dashboard')->name('dashboard');

    Route::middleware(['logistics.access'])->group(function () {
        Route::prefix('admin')->name('admin.')->group(function () {
            Route::livewire('business-partners', 'pages::admin.business-partners-index')->name('business-partners.index');
            Route::livewire('customers', 'pages::admin.customers-index')->name('customers.index');
            Route::get('customers/export.csv', ExportCustomerCsvController::class)->name('customers.export.csv');
            Route::get('customers/template.xlsx', DownloadCustomerImportTemplateController::class)->name('customers.template.xlsx');
            Route::livewire('customers/{customer}', 'pages::admin.customer-show')->name('customers.show');
            Route::livewire('vehicles', 'pages::admin.vehicles-index')->name('vehicles.index');
            Route::get('vehicles/template.xlsx', DownloadVehicleImportTemplateController::class)->name('vehicles.template.xlsx');
            Route::livewire('vehicles/{vehicle}', 'pages::admin.vehicle-show')->name('vehicles.show');
            Route::livewire('fuel-intakes', 'pages::admin.fuel-intakes-index')->name('fuel-intakes.index');
            Route::get('fuel-intakes/template.xlsx', DownloadFuelIntakeImportTemplateController::class)->name('fuel-intakes.template.xlsx');
            Route::livewire('fuel-prices', 'pages::admin.fuel-prices-index')->name('fuel-prices.index');
            Route::get('fuel-prices/template.xlsx', DownloadFuelPriceImportTemplateController::class)->name('fuel-prices.template.xlsx');
            Route::livewire('employees', 'pages::admin.employees-index')->name('employees.index');
            Route::get('employees/template.xlsx', DownloadEmployeeImportTemplateController::class)->name('employees.template.xlsx');
            Route::livewire('employees/{employee}', 'pages::admin.employee-show')->name('employees.show');
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
            Route::livewire('inventory', 'pages::admin.inventory-index')->name('inventory.index');
            Route::livewire('finance', 'pages::admin.finance-index')->name('finance.index');
            Route::livewire('finance/reports', 'pages::admin.finance-reports')->name('finance.reports');
            Route::livewire('finance/payment-due-calendar', 'pages::admin.finance-payment-due-calendar')->name('finance.payment-due-calendar');
            Route::livewire('finance/bank-statement-csv', 'pages::admin.bank-statement-csv-import')->name('finance.bank-statement-csv');
            Route::livewire('finance/chart-of-accounts', 'pages::admin.chart-accounts-index')->name('finance.chart-accounts.index');
            Route::livewire('finance/journal-entries', 'pages::admin.journal-entries-index')->name('finance.journal-entries.index');
            Route::livewire('finance/trial-balance', 'pages::admin.finance-trial-balance')->name('finance.trial-balance');
            Route::livewire('finance/balance-sheet', 'pages::admin.finance-balance-sheet')->name('finance.balance-sheet');
            Route::livewire('finance/fiscal-opening-balances', 'pages::admin.fiscal-opening-balances-index')->name('finance.fiscal-opening-balances.index');
            Route::livewire('finance/cash-registers', 'pages::admin.cash-registers-index')->name('finance.cash-registers.index');
            Route::livewire('finance/vouchers', 'pages::admin.vouchers-index')->name('finance.vouchers.index');
            Route::get('finance/vouchers/export.csv', ExportVouchersCsvController::class)->name('finance.vouchers.export.csv');
            Route::livewire('finance/current-accounts', 'pages::admin.current-accounts-index')->name('finance.current-accounts.index');
            Route::livewire('finance/account-transactions', 'pages::admin.account-transactions-index')->name('finance.account-transactions.index');
            Route::livewire('finance/bank-dashboard', 'pages::admin.bank-dashboard')->name('finance.bank-dashboard');
            Route::livewire('finance/bank-accounts', 'pages::admin.bank-accounts-index')->name('finance.bank-accounts.index');
            Route::livewire('finance/payments', 'pages::admin.payments-index')->name('finance.payments.index');
            Route::get('finance/payments/export.csv', ExportPaymentsCsvController::class)->name('finance.payments.export.csv');
            Route::livewire('finance/bank-transactions', 'pages::admin.bank-transactions-index')->name('finance.bank-transactions.index');
            Route::livewire('pricing-conditions', 'pages::admin.pricing-conditions-index')->name('pricing-conditions.index');
            Route::livewire('trip-expenses', 'pages::admin.trip-expenses-index')->name('trip-expenses.index');
            Route::livewire('vehicle-finances', 'pages::admin.vehicle-finances-index')->name('vehicle-finances.index');

            // Lookup tables
            Route::livewire('material-codes', 'pages::admin.material-codes-index')->name('material-codes.index');
            Route::livewire('vehicle-fines', 'pages::admin.vehicle-fines-index')->name('vehicle-fines.index');

            // HR Module
            Route::prefix('hr')->name('hr.')->group(function (): void {
                Route::livewire('leaves', 'pages::admin.leaves-index')->name('leaves.index');
                Route::livewire('advances', 'pages::admin.advances-index')->name('advances.index');
                Route::livewire('payroll', 'pages::admin.payroll-index')->name('payroll.index');
                Route::get('payroll/{payroll}/print', PayrollPrintController::class)->name('payroll.print');
                Route::livewire('attendance', 'pages::admin.attendance-index')->name('attendance.index');
                Route::livewire('shifts', 'pages::admin.shifts-index')->name('shifts.index');
            });

            // Operations
            Route::livewire('maintenance', 'pages::admin.maintenance-index')->name('maintenance.index');
            Route::livewire('work-orders', 'pages::admin.work-orders-index')->name('work-orders.index');
            Route::livewire('vehicle-tyres', 'pages::admin.vehicle-tyres-index')->name('vehicle-tyres.index');
            Route::livewire('delivery-imports', 'pages::admin.delivery-imports-index')->name('delivery-imports.index');
            Route::livewire('calendar', 'pages::admin.calendar-index')->name('calendar.index');

            // Notifications
            Route::livewire('notifications', 'pages::admin.notifications-index')->name('notifications.index');
            Route::livewire('notifications/{notification}', 'pages::admin.notification-show')->name('notifications.show');

            // Documents
            Route::livewire('documents', 'pages::admin.documents-index')->name('documents.index');
            Route::livewire('documents/{document}', 'pages::admin.document-show')->name('documents.show');
            Route::get('documents/{document}/download', DownloadDocumentController::class)->name('documents.download');

            // Analytics
            Route::livewire('analytics/fleet', 'pages::admin.fleet-analytics')->name('analytics.fleet');
            Route::livewire('analytics/operations', 'pages::admin.operations-analytics')->name('analytics.operations');
            Route::livewire('analytics/cost-centers', 'pages::admin.cost-center-pl')->name('analytics.cost-centers');

            // Finance reports
            Route::livewire('finance/billing-preview', 'pages::admin.billing-preview')->name('finance.billing-preview');
            Route::livewire('finance/weekly-reconciliation', 'pages::admin.weekly-reconciliation')->name('finance.weekly-reconciliation');
            Route::livewire('finance/invoices', 'pages::admin.invoices-index')->name('finance.invoices.index');

            // Team management (admin-only)
            Route::livewire('team', 'pages::admin.team-users-index')->name('team.index');
        });
    });
});

// Personnel Portal — employee self-service
Route::middleware(['auth', 'verified', 'personnel.access'])
    ->prefix('personnel')
    ->name('personnel.')
    ->group(function (): void {
        Route::livewire('dashboard', 'pages::personnel.dashboard')->name('dashboard');
        Route::livewire('my-payrolls', 'pages::personnel.my-payrolls')->name('payrolls.index');
        Route::get('my-payrolls/{payroll}/print', PersonnelPayrollPrintController::class)->name('payrolls.print');
        Route::livewire('my-leaves', 'pages::personnel.my-leaves')->name('leaves.index');
        Route::livewire('my-advances', 'pages::personnel.my-advances')->name('advances.index');
        Route::livewire('my-shifts', 'pages::personnel.my-shifts')->name('shifts.index');
        Route::livewire('my-profile', 'pages::personnel.my-profile')->name('profile');
    });

// Customer Portal — customer self-service
Route::middleware(['auth', 'verified', 'customer.access'])
    ->prefix('customer')
    ->name('customer.')
    ->group(function (): void {
        Route::livewire('dashboard', 'pages::customer.dashboard')->name('dashboard');
        Route::livewire('my-orders', 'pages::customer.orders-index')->name('orders.index');
        Route::livewire('my-orders/create', 'pages::customer.order-create')->name('orders.create');
        Route::livewire('my-orders/{order}', 'pages::customer.order-show')->name('orders.show');
        Route::livewire('my-shipments', 'pages::customer.shipments-index')->name('shipments.index');
        Route::livewire('my-documents', 'pages::customer.my-documents')->name('documents.index');
        Route::livewire('my-invoices', 'pages::customer.my-invoices')->name('invoices.index');
    });

require __DIR__.'/settings.php';
