<?php

namespace App\Services\Finance;

use App\Models\ChartAccount;
use App\Models\JournalEntry;
use App\Models\JournalLine;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Çift taraflı yevmiye kaydı (borç = alacak toplamı).
 */
class JournalPostingService
{
    /**
     * @param  list<array{chart_account_id: int, debit: float|int|string, credit: float|int|string}>  $lines
     */
    public function createBalancedEntry(
        int $tenantId,
        ?int $userId,
        string $entryDate,
        ?string $reference,
        ?string $memo,
        array $lines,
    ): JournalEntry {
        if ($lines === []) {
            throw new InvalidArgumentException(__('At least one journal line is required.'));
        }

        $totalDebit = '0.00';
        $totalCredit = '0.00';
        $normalized = [];

        foreach ($lines as $line) {
            if (! isset($line['chart_account_id'])) {
                throw new InvalidArgumentException(__('Each line requires chart_account_id.'));
            }
            $accountId = (int) $line['chart_account_id'];
            $debit = $this->money($line['debit'] ?? 0);
            $credit = $this->money($line['credit'] ?? 0);
            if (bccomp($debit, '0.00', 2) > 0 && bccomp($credit, '0.00', 2) > 0) {
                throw new InvalidArgumentException(__('A line cannot have both debit and credit.'));
            }
            if (bccomp($debit, '0.00', 2) === 0 && bccomp($credit, '0.00', 2) === 0) {
                throw new InvalidArgumentException(__('Each line needs a non-zero debit or credit.'));
            }

            $account = ChartAccount::query()->where('id', $accountId)->where('tenant_id', $tenantId)->first();
            if ($account === null) {
                throw new InvalidArgumentException(__('Chart account does not belong to this tenant.'));
            }

            $totalDebit = bcadd($totalDebit, $debit, 2);
            $totalCredit = bcadd($totalCredit, $credit, 2);
            $normalized[] = ['chart_account_id' => $accountId, 'debit' => $debit, 'credit' => $credit];
        }

        if (bccomp($totalDebit, $totalCredit, 2) !== 0) {
            throw new InvalidArgumentException(__('Debit total must equal credit total.'));
        }

        return DB::transaction(function () use ($tenantId, $userId, $entryDate, $reference, $memo, $normalized): JournalEntry {
            $entry = JournalEntry::query()->create([
                'tenant_id' => $tenantId,
                'user_id' => $userId,
                'entry_date' => $entryDate,
                'reference' => $reference,
                'memo' => $memo,
            ]);

            foreach ($normalized as $row) {
                JournalLine::query()->create([
                    'journal_entry_id' => $entry->id,
                    'chart_account_id' => $row['chart_account_id'],
                    'debit' => $row['debit'],
                    'credit' => $row['credit'],
                ]);
            }

            return $entry->load('lines');
        });
    }

    private function money(float|int|string $value): string
    {
        if (is_string($value)) {
            $value = str_replace(',', '.', trim($value));
        }
        $s = number_format((float) $value, 2, '.', '');

        if (bccomp($s, '0.00', 2) < 0) {
            throw new InvalidArgumentException(__('Amounts cannot be negative.'));
        }

        return $s;
    }
}
