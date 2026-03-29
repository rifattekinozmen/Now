<?php

namespace App\Http\Controllers\Admin;

use App\Exports\PinImportTemplateExport;
use App\Http\Controllers\Controller;
use App\Models\DeliveryNumber;
use Illuminate\Support\Facades\Gate;
use Maatwebsite\Excel\Facades\Excel;

class DownloadPinImportTemplateController extends Controller
{
    public function __invoke()
    {
        Gate::authorize('viewAny', DeliveryNumber::class);

        return Excel::download(new PinImportTemplateExport, 'pin-import-template.xlsx');
    }
}
