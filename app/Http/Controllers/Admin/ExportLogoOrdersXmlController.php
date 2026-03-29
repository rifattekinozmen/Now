<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Services\Integrations\Logo\LogoErpExportService;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\Response;

class ExportLogoOrdersXmlController extends Controller
{
    public function __invoke(LogoErpExportService $logo): Response
    {
        Gate::authorize('viewAny', Order::class);

        $orders = Order::query()->with('customer')->orderBy('id')->get();
        $xml = $logo->buildOrdersConnectXml($orders);

        return response($xml, 200, [
            'Content-Type' => 'application/xml; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="logo-orders-export.xml"',
        ]);
    }
}
