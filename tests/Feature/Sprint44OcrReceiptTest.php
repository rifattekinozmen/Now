<?php

use App\Services\Finance\VoucherOcrService;

it('parseText extracts date in DD.MM.YYYY format', function (): void {
    $service = new VoucherOcrService;
    $text = "Tarih: 20.04.2026\nTOPLAM: 1.234,56 TL";
    $result = $service->parseText($text);

    expect($result['date'])->toBe('2026-04-20');
});

it('parseText extracts date in YYYY-MM-DD format', function (): void {
    $service = new VoucherOcrService;
    $result = $service->parseText("Date: 2026-04-20\nAmount: 100.00 TRY");

    expect($result['date'])->toBe('2026-04-20');
});

it('parseText extracts amount from TOPLAM keyword', function (): void {
    $service = new VoucherOcrService;
    $result = $service->parseText('TOPLAM 1.234,56 TL');

    expect($result['amount'])->toBe('1234.56');
});

it('parseText extracts amount with TRY currency suffix', function (): void {
    $service = new VoucherOcrService;
    $result = $service->parseText('Ücret: 250,00 TRY');

    expect($result['amount'])->toBe('250.00');
});

it('parseText extracts KDV as vat_amount', function (): void {
    $service = new VoucherOcrService;
    $result = $service->parseText("TOPLAM 1000,00 TL\nKDV 180,00 TL");

    expect($result['vat_amount'])->toBe('180.00');
});

it('parseText returns nulls when no data matches', function (): void {
    $service = new VoucherOcrService;
    $result = $service->parseText('Bu fişte hiç veri yok.');

    expect($result['date'])->toBeNull()
        ->and($result['amount'])->toBeNull()
        ->and($result['vat_amount'])->toBeNull();
});

it('isAvailable returns false when provider is null', function (): void {
    config(['services.ocr.provider' => 'null']);
    $service = new VoucherOcrService;

    expect($service->isAvailable())->toBeFalse();
});

it('parseReceiptImage returns empty result when no provider configured', function (): void {
    config(['services.ocr.provider' => 'null']);
    $service = new VoucherOcrService;
    $result = $service->parseReceiptImage('/nonexistent/file.jpg');

    expect($result)->toBe(['date' => null, 'amount' => null, 'vat_amount' => null]);
});
