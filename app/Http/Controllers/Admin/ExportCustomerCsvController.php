<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Services\Logistics\ExportService;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ExportCustomerCsvController extends Controller
{
    public function __invoke(ExportService $export): StreamedResponse
    {
        Gate::authorize('viewAny', Customer::class);

        $customers = Customer::query()->orderBy('id')->cursor();

        return $export->streamCustomersCsv($customers);
    }
}
