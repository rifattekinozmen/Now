<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;

/**
 * Personel toplu içe aktarma şablonu (`ExcelImportService::getEmployeeImportMapping` ile uyumlu başlıklar).
 */
final class EmployeeImportTemplateExport implements FromArray, WithHeadings
{
    /**
     * @return list<list<string>>
     */
    public function array(): array
    {
        return [
            ['Ahmet', 'Yılmaz', '10000000000', 'A+', '+905551112233'],
        ];
    }

    /**
     * @return list<string>
     */
    public function headings(): array
    {
        return ['Ad', 'Soyad', 'T.C.', 'Kan', 'Telefon'];
    }
}
