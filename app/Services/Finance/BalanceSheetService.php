<?php

namespace App\Services\Finance;

/**
 * Dönem içi yevmiye hareketlerinden hesap tipine göre özet (bilanço / gelir tablosu görünümü).
 *
 * Açılış bakiyesi ve kapanış kayıtları olmadan yasal bilanço üretmez; operasyonel özet niteliğindedir.
 */
class BalanceSheetService
{
    public function __construct(
        private TrialBalanceService $trialBalance,
    ) {}

    /**
     * @return array{
     *     balance_sheet: array{
     *         assets: array{type: string, total_debit: string, total_credit: string, net: string},
     *         liabilities: array{type: string, total_debit: string, total_credit: string, net: string},
     *         equity: array{type: string, total_debit: string, total_credit: string, net: string}
     *     },
     *     income_statement: array{
     *         revenue: array{type: string, total_debit: string, total_credit: string, net: string},
     *         expense: array{type: string, total_debit: string, total_credit: string, net: string}
     *     },
     *     totals: array{
     *         assets_net: string,
     *         liabilities_net: string,
     *         equity_net: string,
     *         liabilities_plus_equity_net: string,
     *         revenue_net: string,
     *         expense_net: string,
     *         period_result_net: string
     *     }
     * }
     */
    public function periodStructuredSummary(int $tenantId, string $dateFrom, string $dateTo): array
    {
        $rows = $this->trialBalance->periodAccountTotals($tenantId, $dateFrom, $dateTo);
        $byType = $this->trialBalance->summarizeByAccountType($rows);

        $assets = $this->pickType($byType, 'asset');
        $liabilities = $this->pickType($byType, 'liability');
        $equity = $this->pickType($byType, 'equity');
        $revenue = $this->pickType($byType, 'revenue');
        $expense = $this->pickType($byType, 'expense');

        $liabilitiesPlusEquity = bcadd($liabilities['net'], $equity['net'], 2);
        $periodResult = bcadd($revenue['net'], $expense['net'], 2);

        return [
            'balance_sheet' => [
                'assets' => $assets,
                'liabilities' => $liabilities,
                'equity' => $equity,
            ],
            'income_statement' => [
                'revenue' => $revenue,
                'expense' => $expense,
            ],
            'totals' => [
                'assets_net' => $assets['net'],
                'liabilities_net' => $liabilities['net'],
                'equity_net' => $equity['net'],
                'liabilities_plus_equity_net' => $liabilitiesPlusEquity,
                'revenue_net' => $revenue['net'],
                'expense_net' => $expense['net'],
                'period_result_net' => $periodResult,
            ],
        ];
    }

    /**
     * @param  array<string, array{type: string, total_debit: string, total_credit: string, net: string}>  $byType
     * @return array{type: string, total_debit: string, total_credit: string, net: string}
     */
    private function pickType(array $byType, string $type): array
    {
        if (isset($byType[$type])) {
            return $byType[$type];
        }

        return [
            'type' => $type,
            'total_debit' => '0.00',
            'total_credit' => '0.00',
            'net' => '0.00',
        ];
    }
}
