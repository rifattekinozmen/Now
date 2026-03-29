<?php

namespace App\Http\Controllers\Admin;

use App\Exports\EmployeeImportTemplateExport;
use App\Http\Controllers\Controller;
use App\Models\Employee;
use Illuminate\Support\Facades\Gate;
use Maatwebsite\Excel\Facades\Excel;

class DownloadEmployeeImportTemplateController extends Controller
{
    public function __invoke()
    {
        Gate::authorize('viewAny', Employee::class);

        return Excel::download(new EmployeeImportTemplateExport, 'employee-import-template.xlsx');
    }
}
