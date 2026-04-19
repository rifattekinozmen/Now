<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;

/**
 * Araç toplu içe aktarma şablonu (`ExcelImportService::getVehicleImportMapping` ile uyumlu başlıklar).
 */
final class VehicleImportTemplateExport implements FromArray, WithHeadings
{
    /**
     * @return list<list<string>>
     */
    public function array(): array
    {
        return [
            ['34 ABC 123', 'WDB96312345678901', 'Mercedes-Benz', 'Actros', 2021, '2027-06-30'],
        ];
    }

    /**
     * @return list<string>
     */
    public function headings(): array
    {
        return ['Plaka', 'Şasi', 'Marka', 'Model', 'Yıl', 'Muayene'];
    }
}
