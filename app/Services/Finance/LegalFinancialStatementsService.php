<?php

namespace App\Services\Finance;

use Illuminate\Support\Facades\DB;

/**
 * Mali rapor / yasal uyumluk iddiası taşımaz. Açılış bakiyeleri bilanço kalemlerine (aktif/pasif/özkaynak)
 * eklenerek dönem hareketleriyle birleştirilmiş operasyonel özet üretir; denetim ve TFRS çıktısı değildir.
 */
class LegalFinancialStatementsService
{
    public function __construct(
        private BalanceSheetService $balanceSheet,
        private TrialBalanceService $trialBalance,
    ) {}

    /**
     * @return array{
     *     balance_sheet: array<string, mixed>,
     *     income_statement: array<string, mixed>,
     *     totals: array<string, mixed>,
     *     includes_fiscal_openings: bool,
     *     fiscal_year: int
     * }
     */
    public function periodStructuredSummaryWithFiscalOpenings(int $tenantId, string $dateFrom, string $dateTo, int $fiscalYear): array
    {
        $base = $this->balanceSheet->periodStructuredSummary($tenantId, $dateFrom, $dateTo);
        $openingByType = $this->openingBalancesByAccountType($tenantId, $fiscalYear);

        if ($openingByType === []) {
            return array_merge($base, [
                'includes_fiscal_openings' => false,
                'fiscal_year' => $fiscalYear,
            ]);
        }

        $map = [
            'assets' => 'asset',
            'liabilities' => 'liability',
            'equity' => 'equity',
        ];

        foreach ($map as $sectionKey => $typeKey) {
            $periodSection = $base['balance_sheet'][$sectionKey];
            $openingSection = $openingByType[$typeKey] ?? $this->emptyTypeAggregate($typeKey);
            $base['balance_sheet'][$sectionKey] = $this->mergeSections($periodSection, $openingSection);
        }

        $base['totals']['assets_net'] = $base['balance_sheet']['assets']['net'];
        $base['totals']['liabilities_net'] = $base['balance_sheet']['liabilities']['net'];
        $base['totals']['equity_net'] = $base['balance_sheet']['equity']['net'];
        $base['totals']['liabilities_plus_equity_net'] = bcadd(
            $base['balance_sheet']['liabilities']['net'],
            $base['balance_sheet']['equity']['net'],
            2,
        );

        $base['includes_fiscal_openings'] = true;
        $base['fiscal_year'] = $fiscalYear;

        return $base;
    }

    /**
     * @return array<string, array{type: string, total_debit: string, total_credit: string, net: string}>
     */
    private function openingBalancesByAccountType(int $tenantId, int $fiscalYear): array
    {
        $rows = DB::table('fiscal_opening_balances as fob')
            ->join('chart_accounts as ca', 'fob.chart_account_id', '=', 'ca.id')
            ->where('fob.tenant_id', $tenantId)
            ->where('ca.tenant_id', $tenantId)
            ->where('fob.fiscal_year', $fiscalYear)
            ->selectRaw('ca.type, SUM(fob.opening_debit) as total_debit, SUM(fob.opening_credit) as total_credit')
            ->groupBy('ca.type')
            ->get();

        if ($rows->isEmpty()) {
            return [];
        }

        $fake = [];
        foreach ($rows as $row) {
            $d = $this->money($row->total_debit ?? '0');
            $c = $this->money($row->total_credit ?? '0');
            $net = bcsub($d, $c, 2);
            $fake[] = [
                'chart_account_id' => 0,
                'code' => '',
                'name' => '',
                'type' => (string) $row->type,
                'total_debit' => $d,
                'total_credit' => $c,
                'net' => $net,
            ];
        }

        return $this->trialBalance->summarizeByAccountType($fake);
    }

    /**
     * @param  array{type: string, total_debit: string, total_credit: string, net: string}  $period
     * @param  array{type: string, total_debit: string, total_credit: string, net: string}  $opening
     * @return array{type: string, total_debit: string, total_credit: string, net: string}
     */
    private function mergeSections(array $period, array $opening): array
    {
        return [
            'type' => $period['type'],
            'total_debit' => bcadd($period['total_debit'], $opening['total_debit'], 2),
            'total_credit' => bcadd($period['total_credit'], $opening['total_credit'], 2),
            'net' => bcadd($period['net'], $opening['net'], 2),
        ];
    }

    /**
     * @return array{type: string, total_debit: string, total_credit: string, net: string}
     */
    private function emptyTypeAggregate(string $type): array
    {
        return [
            'type' => $type,
            'total_debit' => '0.00',
            'total_credit' => '0.00',
            'net' => '0.00',
        ];
    }

    private function money(mixed $value): string
    {
        return number_format((float) $value, 2, '.', '');
    }
}
