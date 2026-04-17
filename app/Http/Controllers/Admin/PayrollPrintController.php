<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Payroll;
use App\Models\Tenant;
use App\Models\TenantSetting;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

class PayrollPrintController extends Controller
{
    public function __invoke(Request $request, Payroll $payroll): Response
    {
        Gate::authorize('view', $payroll);

        $payroll->load(['employee', 'approvedBy']);

        $tenantId = (int) $payroll->tenant_id;
        $tenant = Tenant::find($tenantId);
        $companyName = $tenant?->name ?? config('app.name');
        $companyTaxId = TenantSetting::get($tenantId, 'company_tax_id');
        $companyAddress = TenantSetting::get($tenantId, 'company_address');
        $companyCity = TenantSetting::get($tenantId, 'company_city');

        return response()->view('admin.payroll-print', compact(
            'payroll',
            'companyName',
            'companyTaxId',
            'companyAddress',
            'companyCity',
        ));
    }
}
