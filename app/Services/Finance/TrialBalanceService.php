<?php

namespace App\Services\Finance;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Dönem içi yevmiye satırlarından hesap bazında borç/alacak özet (mizan hareketleri).
 *
 * Mali rapor niteliği taşımaz; hukuki/muhasebe tavsiyesi değildir.
 */
class TrialBalanceService
{
    /**
     * @return list<array{
     *     chart_account_id: int,
     *     code: string,
     *     name: string,
     *     type: string,
     *     total_debit: string,
     *     total_credit: string,
     *     net: string
     * }>
     */
    public function periodAccountTotals(int $tenantId, string $dateFrom, string $dateTo): array
    {
        $from = $this->parseDate($dateFrom);
        $to = $this->parseDate($dateTo);
        if ($from->gt($to)) {
            throw new InvalidArgumentException(__('The start date must be before or equal to the end date.'));
        }

        $rows = DB::table('journal_lines')
            ->join('journal_entries', 'journal_lines.journal_entry_id', '=', 'journal_entries.id')
            ->join('chart_accounts', 'journal_lines.chart_account_id', '=', 'chart_accounts.id')
            ->where('journal_entries.tenant_id', $tenantId)
            ->where('chart_accounts.tenant_id', $tenantId)
            ->whereBetween('journal_entries.entry_date', [$from->toDateString(), $to->toDateString()])
            ->selectRaw(
                'chart_accounts.id as chart_account_id, chart_accounts.code, chart_accounts.name, chart_accounts.type, '
                .'SUM(journal_lines.debit) as total_debit, SUM(journal_lines.credit) as total_credit',
            )
            ->groupBy('chart_accounts.id', 'chart_accounts.code', 'chart_accounts.name', 'chart_accounts.type')
            ->orderBy('chart_accounts.code')
            ->get();

        $out = [];
        foreach ($rows as $row) {
            $debit = $this->money($row->total_debit ?? '0');
            $credit = $this->money($row->total_credit ?? '0');
            $net = bcsub($debit, $credit, 2);
            $out[] = [
                'chart_account_id' => (int) $row->chart_account_id,
                'code' => (string) $row->code,
                'name' => (string) $row->name,
                'type' => (string) $row->type,
                'total_debit' => $debit,
                'total_credit' => $credit,
                'net' => $net,
            ];
        }

        return $out;
    }

    /**
     * @param  list<array{code: string, name: string, type: string, total_debit: string, total_credit: string, net: string}>  $accountRows
     * @return array<string, array{type: string, total_debit: string, total_credit: string, net: string}>
     */
    public function summarizeByAccountType(array $accountRows): array
    {
        $byType = [];
        foreach ($accountRows as $row) {
            $type = $row['type'] ?? 'unknown';
            if (! isset($byType[$type])) {
                $byType[$type] = [
                    'type' => $type,
                    'total_debit' => '0.00',
                    'total_credit' => '0.00',
                    'net' => '0.00',
                ];
            }
            $byType[$type]['total_debit'] = bcadd($byType[$type]['total_debit'], $row['total_debit'], 2);
            $byType[$type]['total_credit'] = bcadd($byType[$type]['total_credit'], $row['total_credit'], 2);
            $byType[$type]['net'] = bcadd($byType[$type]['net'], $row['net'], 2);
        }

        return $byType;
    }

    /**
     * @param  list<array{total_debit: string, total_credit: string}>  $accountRows
     * @return array{total_debit: string, total_credit: string}
     */
    public function grandTotals(array $accountRows): array
    {
        $d = '0.00';
        $c = '0.00';
        foreach ($accountRows as $row) {
            $d = bcadd($d, $row['total_debit'], 2);
            $c = bcadd($c, $row['total_credit'], 2);
        }

        return ['total_debit' => $d, 'total_credit' => $c];
    }

    private function parseDate(string $value): Carbon
    {
        try {
            return Carbon::parse($value)->startOfDay();
        } catch (\Throwable) {
            throw new InvalidArgumentException(__('Invalid date.'));
        }
    }

    private function money(mixed $value): string
    {
        $s = number_format((float) $value, 2, '.', '');

        return $s;
    }
}
