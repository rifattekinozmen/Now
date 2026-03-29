<?php

namespace App\Services\Logistics;

/**
 * Yakıt anomalisi / fiyat denetimi vb. (Faz 3 — iskelet).
 */
final class AuditAiEvaluationService
{
    /**
     * @param  array<string, mixed>  $context
     * @return array{flagged: bool, reasons: list<string>}
     */
    public function evaluateFreightQuote(array $context): array
    {
        return ['flagged' => false, 'reasons' => []];
    }
}
