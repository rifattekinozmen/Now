<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;

/**
 * Yakıt fiyatı toplu içe aktarma şablonu (`ExcelImportService::getFuelPriceImportMapping` ile uyumlu başlıklar).
 */
final class FuelPriceImportTemplateExport implements FromArray, WithHeadings
{
    /**
     * @return list<list<string>>
     */
    public function array(): array
    {
        return [
            ['01.04.2026', '64.36', '79.38', '0.00'],
        ];
    }

    /**
     * @return list<string>
     */
    public function headings(): array
    {
        return ['Tarih', 'Excellium Kurşunsuz 95 TL/Lt', 'Motorin TL/Lt', 'Otogaz TL/Lt'];
    }
}
