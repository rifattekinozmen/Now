<?php

namespace App\Http\Controllers\Personnel;

use App\Enums\PayrollStatus;
use App\Http\Controllers\Controller;
use App\Models\Payroll;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class PersonnelPayrollPrintController extends Controller
{
    public function __invoke(Request $request, Payroll $payroll): Response
    {
        $user = $request->user();

        // Personnel can only print their own payrolls
        if (! $user || ! $user->employee_id || $payroll->employee_id !== (int) $user->employee_id) {
            abort(403);
        }

        // Only approved or paid payrolls may be printed
        if ($payroll->status === PayrollStatus::Draft) {
            abort(403);
        }

        $payroll->load(['employee', 'approvedBy']);

        return response()->view('admin.payroll-print', compact('payroll'));
    }
}
