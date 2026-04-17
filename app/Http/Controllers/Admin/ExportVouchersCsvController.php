<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Voucher;
use App\Services\Logistics\ExportService;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ExportVouchersCsvController extends Controller
{
    public function __invoke(ExportService $export): StreamedResponse
    {
        Gate::authorize('viewAny', Voucher::class);

        $vouchers = Voucher::query()->orderByDesc('voucher_date')->cursor();

        return $export->streamVouchersCsv($vouchers);
    }
}
