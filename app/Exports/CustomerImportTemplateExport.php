<?php

namespace App\Exports;

use App\Services\Logistics\ExcelImportService;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;

/**
 * Müşteri toplu içe aktarma için örnek satırlı XLSX (`ExcelImportService::getCustomerImportMapping` başlıklarıyla uyumlu).
 */
final class CustomerImportTemplateExport implements FromArray, WithHeadings
{
    /**
     * @return list<list<string|int>>
     */
    public function array(): array
    {
        return [
            ['BP-ÖRNEK-001', '1234567890', 'Örnek Lojistik A.Ş.', 'Örnek Ticaret Unvanı', 30],
        ];
    }

    /**
     * @return list<string>
     */
    public function headings(): array
    {
        return array_keys(app(ExcelImportService::class)->getCustomerImportMapping());
    }
}
