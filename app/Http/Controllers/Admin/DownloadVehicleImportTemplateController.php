<?php

namespace App\Http\Controllers\Admin;

use App\Exports\VehicleImportTemplateExport;
use App\Http\Controllers\Controller;
use App\Models\Vehicle;
use Illuminate\Support\Facades\Gate;
use Maatwebsite\Excel\Facades\Excel;

class DownloadVehicleImportTemplateController extends Controller
{
    public function __invoke()
    {
        Gate::authorize('viewAny', Vehicle::class);

        return Excel::download(new VehicleImportTemplateExport, 'vehicle-import-template.xlsx');
    }
}
