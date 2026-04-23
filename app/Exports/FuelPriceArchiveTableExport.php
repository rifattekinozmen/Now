<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

final class FuelPriceArchiveTableExport implements FromArray, ShouldAutoSize, WithHeadings, WithStyles
{
    /**
     * @param  list<array<int, string|float|int|null>>  $rows
     */
    public function __construct(
        private readonly array $rows
    ) {}

    /**
     * @return list<list<string|float|int|null>>
     */
    public function array(): array
    {
        return $this->rows;
    }

    /**
     * @return list<string>
     */
    public function headings(): array
    {
        return [
            'Tarih',
            'Excellium Kurşunsuz 95 TL/Lt',
            'Motorin TL/Lt',
            'Motorin Günlük Değişim (%)',
            'Excellium Motorin TL/Lt',
            'Kalorifer Yakıtı TL/Kg',
            'Fuel Oil TL/Kg',
            'Yüksek Kükürtlü Fuel Oil TL/Kg',
            'Otogaz TL/Lt',
            'Gazyağı TL/Lt',
        ];
    }

    public function styles(Worksheet $sheet): array
    {
        $lastRow = count($this->rows) + 1;

        $sheet->getStyle('A1:J1')->applyFromArray([
            'font' => [
                'bold' => true,
                'color' => ['rgb' => 'FFFFFF'],
            ],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['rgb' => 'B91C1C'],
            ],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
                'vertical' => Alignment::VERTICAL_CENTER,
            ],
        ]);

        if ($lastRow >= 2) {
            $sheet->getStyle('A2:J'.$lastRow)->applyFromArray([
                'borders' => [
                    'allBorders' => [
                        'borderStyle' => Border::BORDER_THIN,
                        'color' => ['rgb' => 'E5E7EB'],
                    ],
                ],
                'alignment' => [
                    'vertical' => Alignment::VERTICAL_CENTER,
                ],
            ]);

            $sheet->getStyle('B2:C'.$lastRow)->getNumberFormat()->setFormatCode('0.00');
            $sheet->getStyle('D2:D'.$lastRow)->getNumberFormat()->setFormatCode('0.00"%"');
            $sheet->getStyle('E2:J'.$lastRow)->getNumberFormat()->setFormatCode('0.00');
        }

        foreach ($this->rows as $index => $row) {
            $excelRow = $index + 2;
            $delta = isset($row[3]) && is_numeric($row[3]) ? (float) $row[3] : null;
            if ($delta === null || abs($delta) <= 5) {
                continue;
            }

            if ($delta > 5) {
                $sheet->getStyle('D'.$excelRow)->applyFromArray([
                    'font' => ['bold' => true, 'color' => ['rgb' => '065F46']],
                    'fill' => [
                        'fillType' => Fill::FILL_SOLID,
                        'startColor' => ['rgb' => 'D1FAE5'],
                    ],
                ]);
            } elseif ($delta < -5) {
                $sheet->getStyle('D'.$excelRow)->applyFromArray([
                    'font' => ['bold' => true, 'color' => ['rgb' => '9F1239']],
                    'fill' => [
                        'fillType' => Fill::FILL_SOLID,
                        'startColor' => ['rgb' => 'FFE4E6'],
                    ],
                ]);
            }
        }

        return [];
    }
}
