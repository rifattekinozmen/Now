<?php

use App\Contracts\Finance\ScannedPdfOcrAdapter;
use App\Services\Finance\BankStatementOcrService;

test('csv extraction maps tr headers', function () {
    $csv = "Tarih,Tutar,Açıklama\n2026-01-10,\"1.234,56\",Test ödeme\n";
    $svc = new BankStatementOcrService;
    $rows = $svc->extractRowsFromCsvContent($csv);

    expect($rows)->toHaveCount(1)
        ->and($rows[0]['booked_at'])->toBe('2026-01-10')
        ->and($rows[0]['amount'])->toBe('1234.56')
        ->and($rows[0]['description'])->toContain('Test');
});

test('pdf import diagnostic messages are translated keys', function () {
    $svc = new BankStatementOcrService;
    expect($svc->pdfImportDiagnosticMessage('empty_text'))->toBeString()->not->toBe('')
        ->and($svc->unsupportedScannedPdfUserMessage())->toBe($svc->pdfImportDiagnosticMessage('empty_text'))
        ->and($svc->scannedImageOcrSupported())->toBeFalse()
        ->and($svc->pdfTextLayerExtractionSupported())->toBeTrue();
});

test('import capabilities expose csv and scanned pdf limitation', function () {
    $svc = new BankStatementOcrService;
    $caps = $svc->importCapabilities();

    expect($caps['csv'])->toBeTrue()
        ->and($caps['pdf_text_layer'])->toBeTrue()
        ->and($caps['scanned_pdf_ocr'])->toBeFalse()
        ->and($caps['scanned_pdf_user_message'])->toBe($svc->unsupportedScannedPdfUserMessage());
});

test('plain text line parsing extracts date amount description', function () {
    $svc = new BankStatementOcrService;
    $text = "2026-03-01 Alıcı firma ödemesi 1.500,00\n";
    $rows = $svc->extractRowsFromStatementPlainText($text, 50);

    expect($rows)->toHaveCount(1)
        ->and($rows[0]['booked_at'])->toBe('2026-03-01')
        ->and($rows[0]['description'])->toContain('Alıcı');
});

test('scanned pdf ocr supported when adapter reports available', function () {
    $adapter = new class implements ScannedPdfOcrAdapter
    {
        public function isAvailable(): bool
        {
            return true;
        }

        public function extractPlainText(string $absolutePathToPdf): ?string
        {
            return null;
        }
    };

    $svc = new BankStatementOcrService($adapter);

    expect($svc->scannedImageOcrSupported())->toBeTrue()
        ->and($svc->importCapabilities()['scanned_pdf_ocr'])->toBeTrue();
});
