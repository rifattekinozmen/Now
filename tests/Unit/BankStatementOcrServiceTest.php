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

test('extract rows from pdf with diagnostics reports unreadable file', function () {
    $svc = app(BankStatementOcrService::class);

    $result = $svc->extractRowsFromPdfWithDiagnostics('/nonexistent/path/bank.pdf');

    expect($result['rows'])->toBe([])
        ->and($result['diagnostic'])->toBe('unreadable_file');
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

test('pdf import diagnostic messages are non-empty for known codes', function () {
    $svc = app(BankStatementOcrService::class);

    foreach (['empty_text', 'no_matching_lines', 'unreadable_file', 'parse_error'] as $code) {
        expect($svc->pdfImportDiagnosticMessage($code))->not->toBe('');
    }
});

test('garbage file yields non-ok diagnostic from pdf extraction', function () {
    $path = tempnam(sys_get_temp_dir(), 'stmt');
    $path = $path !== false ? $path.'.pdf' : sys_get_temp_dir().'/stmt-test.pdf';
    file_put_contents($path, '%PDF-1.4 invalid binary junk');

    $svc = app(BankStatementOcrService::class);
    $r = $svc->extractRowsFromPdfWithDiagnostics($path);
    @unlink($path);

    expect($r['rows'])->toBe([])
        ->and($r['diagnostic'])->not->toBe('ok');
});
