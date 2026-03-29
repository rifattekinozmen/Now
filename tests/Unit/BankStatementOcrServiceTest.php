<?php

use App\Services\Finance\BankStatementOcrService;

test('parses csv with english headers', function () {
    $csv = "Date,Amount,Description\n2026-03-15,123.45,Wire transfer\n";
    $svc = new BankStatementOcrService;
    $rows = $svc->extractRowsFromCsvContent($csv);

    expect($rows)->toHaveCount(1)
        ->and($rows[0]['booked_at'])->toBe('2026-03-15')
        ->and($rows[0]['amount'])->toBe('123.45')
        ->and($rows[0]['description'])->toBe('Wire transfer');
});

test('parses turkish date and amount format', function () {
    $csv = "Tarih,Tutar,Açıklama\n15.03.2026,\"1.234,56\",Ödeme\n";
    $svc = new BankStatementOcrService;
    $rows = $svc->extractRowsFromCsvContent($csv);

    expect($rows)->toHaveCount(1)
        ->and($rows[0]['booked_at'])->toBe('2026-03-15')
        ->and($rows[0]['amount'])->toBe('1234.56')
        ->and($rows[0]['description'])->toBe('Ödeme');
});

test('pdf extraction returns empty list for missing file', function () {
    $svc = new BankStatementOcrService;
    expect($svc->extractRowsFromPdf('/nonexistent.pdf'))->toBe([]);
});

test('plain text statement lines parse date amount and description', function () {
    $svc = new BankStatementOcrService;
    $text = "2026-03-15  Wire transfer inbound  123.45\n15.03.2026  Ödeme örnek  1.234,56\n";
    $rows = $svc->extractRowsFromStatementPlainText($text);

    expect($rows)->toHaveCount(2)
        ->and($rows[0]['booked_at'])->toBe('2026-03-15')
        ->and($rows[0]['amount'])->toBe('123.45')
        ->and($rows[0]['description'])->toBe('Wire transfer inbound')
        ->and($rows[1]['booked_at'])->toBe('2026-03-15')
        ->and($rows[1]['amount'])->toBe('1234.56')
        ->and($rows[1]['description'])->toBe('Ödeme örnek');
});

test('plain text line with only date and amount parses', function () {
    $svc = new BankStatementOcrService;
    $rows = $svc->extractRowsFromStatementPlainText("2026-01-01 500.00\n");

    expect($rows)->toHaveCount(1)
        ->and($rows[0]['description'])->toBeNull();
});
