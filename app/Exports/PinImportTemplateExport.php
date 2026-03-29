<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;

/**
 * PIN toplu içe aktarma için örnek satırlı XLSX şablonu (`ExcelImportService::getDeliveryPinImportMapping` ile uyumlu başlıklar).
 */
final class PinImportTemplateExport implements FromArray, WithHeadings
{
    /**
     * @return list<list<string>>
     */
    public function array(): array
    {
        return [
            ['864450789', 'SAS-ÖRNEK-001'],
        ];
    }

    /**
     * @return list<string>
     */
    public function headings(): array
    {
        return ['pin_code', 'sas_no'];
    }
}
