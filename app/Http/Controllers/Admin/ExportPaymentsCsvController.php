<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Services\Logistics\ExportService;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ExportPaymentsCsvController extends Controller
{
    public function __invoke(ExportService $export): StreamedResponse
    {
        Gate::authorize('viewAny', Payment::class);

        $payments = Payment::query()->orderByDesc('payment_date')->cursor();

        return $export->streamPaymentsCsv($payments);
    }
}
