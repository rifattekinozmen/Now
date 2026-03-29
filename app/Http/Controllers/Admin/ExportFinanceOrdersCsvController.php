<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Services\Logistics\ExportService;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ExportFinanceOrdersCsvController extends Controller
{
    public function __invoke(ExportService $export): StreamedResponse
    {
        Gate::authorize('viewAny', Order::class);

        $orders = Order::query()->with('customer')->orderByDesc('id')->cursor();

        return $export->streamOrdersFinanceCsv($orders);
    }
}
