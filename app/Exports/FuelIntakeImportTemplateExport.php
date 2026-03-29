<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;

/**
 * Yakıt alımı toplu içe aktarma şablonu (`ExcelImportService::getFuelIntakeImportMapping` ile uyumlu başlıklar).
 */
final class FuelIntakeImportTemplateExport implements FromArray, WithHeadings
{
    /**
     * @return list<list<string>>
     */
    public function array(): array
    {
        return [
            ['34 ABC 123', '250.5', '125000', '2026-03-29 14:30:00'],
        ];
    }

    /**
     * @return list<string>
     */
    public function headings(): array
    {
        return ['Plaka', 'Litre', 'Kilometre', 'Kayıt Tarihi'];
    }
}
