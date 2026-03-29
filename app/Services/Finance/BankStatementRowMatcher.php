<?php

namespace App\Services\Finance;

use App\Models\Customer;
use Illuminate\Support\Collection;

/**
 * Banka ekstresi satırlarında VKN, IBAN (meta) ve ünvan eşlemesi ile müşteri adayları.
 *
 * @phpstan-type MatchCandidate array{customer_id: int, label: string, reason: string, score: int}
 */
class BankStatementRowMatcher
{
    /**
     * @param  list<array{booked_at: string|null, amount: string|null, description: string|null}>  $rows
     * @return list<array{booked_at: string|null, amount: string|null, description: string|null, match_candidates?: list<MatchCandidate>}>
     */
    public function enrichRowsForTenant(int $tenantId, array $rows): array
    {
        $customers = Customer::query()
            ->where('tenant_id', $tenantId)
            ->get(['id', 'legal_name', 'trade_name', 'tax_id', 'meta']);

        $out = [];
        foreach ($rows as $row) {
            $candidates = $this->matchRow($row, $customers);
            if ($candidates === []) {
                $out[] = $row;

                continue;
            }
            $out[] = array_merge($row, ['match_candidates' => $candidates]);
        }

        return $out;
    }

    /**
     * @param  array{booked_at: string|null, amount: string|null, description: string|null}  $row
     * @param  Collection<int, Customer>  $customers
     * @return list<MatchCandidate>
     */
    private function matchRow(array $row, $customers): array
    {
        $desc = $row['description'] ?? '';
        if (! is_string($desc) || $desc === '') {
            return [];
        }

        $normDesc = mb_strtolower($desc, 'UTF-8');
        $vkns = $this->extractTaxIdsFromText($desc);
        $ibans = $this->extractIbansFromText($desc);

        /** @var list<MatchCandidate> $hits */
        $hits = [];

        foreach ($customers as $customer) {
            $reason = null;
            $score = 0;

            $taxId = $customer->tax_id;
            if (is_string($taxId) && $taxId !== '') {
                $taxDigits = preg_replace('/\D+/', '', $taxId) ?? '';
                foreach ($vkns as $v) {
                    if ($v !== '' && $v === $taxDigits) {
                        $reason = 'tax_id';
                        $score = 100;

                        break;
                    }
                }
            }

            if ($score === 0 && $ibans !== []) {
                $metaIban = $this->normalizeIban((string) (data_get($customer->meta, 'iban') ?? ''));
                if ($metaIban !== '') {
                    foreach ($ibans as $iban) {
                        if ($iban === $metaIban) {
                            $reason = 'iban';
                            $score = 95;

                            break;
                        }
                    }
                }
            }

            if ($score === 0) {
                $legal = mb_strtolower((string) $customer->legal_name, 'UTF-8');
                $trade = mb_strtolower((string) ($customer->trade_name ?? ''), 'UTF-8');
                if ($legal !== '' && mb_strlen($legal, 'UTF-8') >= 4 && str_contains($normDesc, $legal)) {
                    $reason = 'legal_name';
                    $score = 60;
                } elseif ($trade !== '' && mb_strlen($trade, 'UTF-8') >= 4 && str_contains($normDesc, $trade)) {
                    $reason = 'trade_name';
                    $score = 55;
                }
            }

            if ($reason !== null && $score > 0) {
                $hits[] = [
                    'customer_id' => (int) $customer->id,
                    'label' => $customer->legal_name,
                    'reason' => $reason,
                    'score' => $score,
                ];
            }
        }

        usort($hits, fn (array $a, array $b): int => $b['score'] <=> $a['score']);

        return array_slice($hits, 0, 3);
    }

    /**
     * @return list<string> digit-only candidates (10–11 chars typical for TR)
     */
    private function extractTaxIdsFromText(string $text): array
    {
        preg_match_all('/\b(\d{10,11})\b/', $text, $m);
        if (empty($m[1])) {
            return [];
        }

        return array_values(array_unique($m[1]));
    }

    /**
     * @return list<string> normalized IBAN (upper, no spaces)
     */
    private function extractIbansFromText(string $text): array
    {
        $compacted = strtoupper(preg_replace('/\s+/u', '', $text) ?? '');
        preg_match_all('/TR\d{24}/', $compacted, $m);
        if (empty($m[0])) {
            return [];
        }

        return array_values(array_unique($m[0]));
    }

    private function normalizeIban(string $iban): string
    {
        $s = strtoupper(preg_replace('/\s+/', '', $iban) ?? '');

        return preg_match('/^TR\d{24}$/', $s) ? $s : '';
    }
}
