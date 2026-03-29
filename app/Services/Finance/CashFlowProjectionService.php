<?php

namespace App\Services\Finance;

use Carbon\CarbonInterface;

/**
 * Vade ve nakit akışı projeksiyonu (Faz 3 — iskelet).
 */
final class CashFlowProjectionService
{
    /**
     * @return list<array<string, mixed>>
     */
    public function projectForTenant(int $tenantId, CarbonInterface $from, CarbonInterface $to): array
    {
        return [];
    }
}
