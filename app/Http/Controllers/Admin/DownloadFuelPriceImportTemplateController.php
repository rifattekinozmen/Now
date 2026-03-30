<?php

namespace App\Http\Controllers\Admin;

use App\Exports\FuelPriceImportTemplateExport;
use App\Http\Controllers\Controller;
use App\Models\FuelPrice;
use Illuminate\Support\Facades\Gate;
use Maatwebsite\Excel\Facades\Excel;

class DownloadFuelPriceImportTemplateController extends Controller
{
    public function __invoke()
    {
        Gate::authorize('viewAny', FuelPrice::class);

        return Excel::download(new FuelPriceImportTemplateExport, 'fuel-price-import-template.xlsx');
    }
}
