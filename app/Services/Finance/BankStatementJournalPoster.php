<?php

namespace App\Services\Finance;

use App\Models\BankStatementCsvImport;
use App\Models\Customer;
use App\Models\JournalEntry;
use InvalidArgumentException;

/**
 * Banka ekstresi satırından basit çift taraflı kayıt (tahsilat: Dr Bank / Cr Alacaklar).
 */
class BankStatementJournalPoster
{
    public function __construct(
        private JournalPostingService $journalPosting,
    ) {}

    public function postMatchedRow(
        BankStatementCsvImport $import,
        int $rowIndex,
        int $bankChartAccountId,
        int $accountsReceivableChartAccountId,
        int $customerId,
        ?int $userId,
    ): JournalEntry {
        $rows = $import->rows;
        if (! isset($rows[$rowIndex])) {
            throw new InvalidArgumentException(__('Invalid row index.'));
        }

        $row = $rows[$rowIndex];
        $tenantId = (int) $import->tenant_id;

        if (isset($row['journal_entry_id']) && is_numeric($row['journal_entry_id'])) {
            $existing = JournalEntry::query()->where('tenant_id', $tenantId)->whereKey((int) $row['journal_entry_id'])->first();
            if ($existing !== null) {
                return $existing;
            }
        }

        $customer = Customer::query()->where('tenant_id', $tenantId)->whereKey($customerId)->first();
        if ($customer === null) {
            throw new InvalidArgumentException(__('Customer not found for this tenant.'));
        }

        ['abs' => $abs, 'inflow' => $inflow] = $this->parseAmountComponents($row['amount'] ?? '0');

        if ($inflow) {
            $lines = [
                ['chart_account_id' => $bankChartAccountId, 'debit' => $abs, 'credit' => '0.00'],
                ['chart_account_id' => $accountsReceivableChartAccountId, 'debit' => '0.00', 'credit' => $abs],
            ];
        } else {
            $lines = [
                ['chart_account_id' => $accountsReceivableChartAccountId, 'debit' => $abs, 'credit' => '0.00'],
                ['chart_account_id' => $bankChartAccountId, 'debit' => '0.00', 'credit' => $abs],
            ];
        }

        $bookedAt = $row['booked_at'] ?? null;
        $entryDate = is_string($bookedAt) && $bookedAt !== ''
            ? $bookedAt
            : now()->toDateString();

        $desc = is_string($row['description'] ?? null) ? $row['description'] : '';
        $memo = mb_substr($customer->legal_name.($desc !== '' ? ' — '.$desc : ''), 0, 500);

        $sourceKey = $import->id.':row:'.$rowIndex;

        $entry = $this->journalPosting->createBalancedEntry(
            $tenantId,
            $userId,
            $entryDate,
            'BS-'.$import->id.'-'.$rowIndex,
            $memo,
            $lines,
            JournalEntry::SOURCE_BANK_STATEMENT_ROW,
            $sourceKey,
        );

        $freshRows = $import->fresh()->rows;
        if (is_array($freshRows) && isset($freshRows[$rowIndex])) {
            $freshRows[$rowIndex]['journal_entry_id'] = $entry->id;
            $import->update(['rows' => $freshRows]);
        }

        return $entry;
    }

    /**
     * @return array{abs: string, inflow: bool}
     */
    private function parseAmountComponents(mixed $amount): array
    {
        $s = is_string($amount) ? trim($amount) : (string) $amount;
        $s = str_replace(["\xc2\xa0", ' '], '', $s);
        $s = str_replace(',', '.', $s);
        if ($s === '' || ! is_numeric($s)) {
            throw new InvalidArgumentException(__('Invalid amount.'));
        }

        $n = (float) $s;
        if (abs($n) < 0.0000001) {
            throw new InvalidArgumentException(__('Amount must be non-zero.'));
        }

        $abs = number_format(abs($n), 2, '.', '');

        return [
            'abs' => $abs,
            'inflow' => $n >= 0,
        ];
    }
}
