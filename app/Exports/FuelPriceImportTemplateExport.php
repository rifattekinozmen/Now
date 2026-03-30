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
            ['diesel', '45.5000', 'TRY', '2026-03-30', 'TotalEnergies', 'İstanbul'],
        ];
    }

    /**
     * @return list<string>
     */
    public function headings(): array
    {
        return ['Yakıt Tipi', 'Fiyat', 'Para Birimi', 'Kayıt Tarihi', 'Kaynak', 'Bölge'];
    }
}
