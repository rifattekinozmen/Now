<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Payroll;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

class PayrollPrintController extends Controller
{
    public function __invoke(Request $request, Payroll $payroll): Response
    {
        Gate::authorize('view', $payroll);

        $payroll->load(['employee', 'approvedBy']);

        return response()->view('admin.payroll-print', compact('payroll'));
    }
}
