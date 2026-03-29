<?php

namespace App\Http\Controllers\Admin;

use App\Exports\CustomerImportTemplateExport;
use App\Http\Controllers\Controller;
use App\Models\Customer;
use Illuminate\Support\Facades\Gate;
use Maatwebsite\Excel\Facades\Excel;

class DownloadCustomerImportTemplateController extends Controller
{
    public function __invoke()
    {
        Gate::authorize('viewAny', Customer::class);

        return Excel::download(new CustomerImportTemplateExport, 'customer-import-template.xlsx');
    }
}
