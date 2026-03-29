<?php

use App\Enums\OrderStatus;
use App\Models\Customer;
use App\Models\Order;
use App\Services\Integrations\Logo\LogoErpExportService;
use Illuminate\Support\Carbon;

test('builds xml with order fields', function () {
    $customer = Customer::factory()->make(['legal_name' => 'Acme Lojistik A.Ş.']);
    $orderedAt = Carbon::parse('2026-03-15 12:00:00');
    $order = Order::factory()->make([
        'order_number' => 'ORD-UNIT-1',
        'sas_no' => 'SAS-UNIT',
        'currency_code' => 'TRY',
        'freight_amount' => 2500.75,
        'status' => OrderStatus::Confirmed,
        'ordered_at' => $orderedAt,
        'loading_site' => 'Adana Fabrika',
        'unloading_site' => 'İskenderun Liman',
        'incoterms' => 'FOB',
        'distance_km' => 120.5,
        'tonnage' => 26.25,
        'exchange_rate' => 34.5678,
    ]);
    $order->setRelation('customer', $customer);

    $svc = new LogoErpExportService;
    $xml = $svc->buildOrdersConnectXml([$order]);

    expect($xml)->toContain('<LogoConnectExport')
        ->and($xml)->toContain('ORD-UNIT-1')
        ->and($xml)->toContain('SAS-UNIT')
        ->and($xml)->toContain('TRY')
        ->and($xml)->toContain('2500.75')
        ->and($xml)->toContain('Acme Lojistik A.Ş.')
        ->and($xml)->toContain('<OrderedAt>')
        ->and($xml)->toContain('2026-03-15T12:00:00')
        ->and($xml)->toContain('<OrderStatus>')
        ->and($xml)->toContain(OrderStatus::Confirmed->value)
        ->and($xml)->toContain('<LoadingSite>')
        ->and($xml)->toContain('Adana Fabrika')
        ->and($xml)->toContain('<UnloadingSite>')
        ->and($xml)->toContain('İskenderun Liman')
        ->and($xml)->toContain('<Incoterms>')
        ->and($xml)->toContain('FOB')
        ->and($xml)->toContain('<DistanceKm>')
        ->and($xml)->toContain('120.50')
        ->and($xml)->toContain('<Tonnage>')
        ->and($xml)->toContain('26.250')
        ->and($xml)->toContain('<ExchangeRate>')
        ->and($xml)->toContain('34.567800');
});
