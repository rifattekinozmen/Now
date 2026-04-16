<?php

namespace App\Services\Logistics;

/**
 * Yakıt anomali analiz sonucu (value object).
 */
final class AnomalyResult
{
    public function __construct(
        public readonly bool $isAnomaly,
        public readonly float $currentConsumption = 0.0,
        public readonly float $referenceConsumption = 0.0,
        public readonly float $deviationPercent = 0.0,
    ) {}
}
