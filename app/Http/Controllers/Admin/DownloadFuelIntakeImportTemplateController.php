<?php

namespace App\Http\Controllers\Admin;

use App\Exports\FuelIntakeImportTemplateExport;
use App\Http\Controllers\Controller;
use App\Models\FuelIntake;
use Illuminate\Support\Facades\Gate;
use Maatwebsite\Excel\Facades\Excel;

class DownloadFuelIntakeImportTemplateController extends Controller
{
    public function __invoke()
    {
        Gate::authorize('viewAny', FuelIntake::class);

        return Excel::download(new FuelIntakeImportTemplateExport, 'fuel-intake-import-template.xlsx');
    }
}
