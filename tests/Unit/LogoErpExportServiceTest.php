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

test('builds xml with customer tax id when present', function () {
    $customer = Customer::factory()->make([
        'legal_name' => 'Vergi A.Ş.',
        'tax_id' => '1234567890',
        'partner_number' => null,
    ]);
    $order = Order::factory()->make([
        'order_number' => 'ORD-TAX-1',
        'sas_no' => null,
        'currency_code' => 'TRY',
        'freight_amount' => 50,
        'status' => OrderStatus::Draft,
        'ordered_at' => now(),
    ]);
    $order->setRelation('customer', $customer);

    $svc = new LogoErpExportService;
    $xml = $svc->buildOrdersConnectXml([$order]);

    expect($xml)->toContain('<CustomerTaxId>')
        ->and($xml)->toContain('1234567890');
});

test('builds xml with order meta fields from config mapping', function () {
    $customer = Customer::factory()->make(['legal_name' => 'Meta A.Ş.']);
    $order = Order::factory()->make([
        'order_number' => 'ORD-META-1',
        'sas_no' => null,
        'currency_code' => 'TRY',
        'freight_amount' => 10,
        'status' => OrderStatus::Draft,
        'ordered_at' => now(),
        'meta' => [
            'delivery_order_no' => '864450789',
            'notes' => 'Fabrika giriş öncesi arayın.',
            'internal_reference' => 'INT-2026-03',
        ],
    ]);
    $order->setRelation('customer', $customer);

    $svc = new LogoErpExportService;
    $xml = $svc->buildOrdersConnectXml([$order]);

    expect($xml)->toContain('<DeliveryOrderNo>')
        ->and($xml)->toContain('864450789')
        ->and($xml)->toContain('<OrderNotes>')
        ->and($xml)->toContain('Fabrika giriş öncesi arayın.')
        ->and($xml)->toContain('<InternalReference>')
        ->and($xml)->toContain('INT-2026-03');
});

test('builds xml with customer partner number when present', function () {
    $customer = Customer::factory()->make([
        'legal_name' => 'Partner A.Ş.',
        'partner_number' => 'BP-000556677',
    ]);
    $order = Order::factory()->make([
        'order_number' => 'ORD-PN-1',
        'sas_no' => null,
        'currency_code' => 'TRY',
        'freight_amount' => 100,
        'status' => OrderStatus::Draft,
        'ordered_at' => now(),
    ]);
    $order->setRelation('customer', $customer);

    $svc = new LogoErpExportService;
    $xml = $svc->buildOrdersConnectXml([$order]);

    expect($xml)->toContain('<CustomerPartnerNo>')
        ->and($xml)->toContain('BP-000556677');
});
