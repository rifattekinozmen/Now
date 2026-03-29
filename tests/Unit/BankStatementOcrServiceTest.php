<?php

use App\Services\Finance\BankStatementOcrService;

test('extract rows from statement plain text parses dated lines with trailing amount', function () {
    $svc = app(BankStatementOcrService::class);
    $text = "2026-03-15 Ödeme REF123 1.250,50\n2026-03-16 2026-03-16 Other -99.00\n";

    $rows = $svc->extractRowsFromStatementPlainText($text);

    expect($rows)->toHaveCount(2)
        ->and($rows[0]['booked_at'])->toBe('2026-03-15')
        ->and($rows[0]['amount'])->toBe('1250.50')
        ->and($rows[0]['description'])->toContain('Ödeme')
        ->and($rows[1]['booked_at'])->toBe('2026-03-16')
        ->and($rows[1]['amount'])->toBe('-99.00');
});

test('extract rows from pdf returns empty for missing file', function () {
    $svc = app(BankStatementOcrService::class);

    expect($svc->extractRowsFromPdf('/nonexistent/path/bank.pdf'))->toBe([]);
});

test('extract rows from csv content maps turkish headers', function () {
    $svc = app(BankStatementOcrService::class);
    $csv = "Tarih,Tutar,Açıklama\n20.03.2026,\"1.000,25\",HAVALE\n";

    $rows = $svc->extractRowsFromCsvContent($csv);

    expect($rows)->toHaveCount(1)
        ->and($rows[0]['booked_at'])->toBe('2026-03-20')
        ->and($rows[0]['amount'])->toBe('1000.25')
        ->and($rows[0]['description'])->toBe('HAVALE');
});
