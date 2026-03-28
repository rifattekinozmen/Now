<?php

namespace App\Services\Logistics;

/**
 * Motorin / baz fiyat değişiminde navlun revizyonu tetikleme (Logistics: ~%5 eşik).
 */
final class NavlunEskalasyonService
{
    /**
     * Önceki fiyata göre mutlak göreli değişim oranı (0–1+).
     */
    public function relativeChangeAbs(float $previousPrice, float $newPrice): float
    {
        if ($previousPrice <= 0) {
            return $newPrice > 0 ? 1.0 : 0.0;
        }

        return abs($newPrice - $previousPrice) / $previousPrice;
    }

    /**
     * Eşik aşıldı mı? (varsayılan %5 — strictly greater than threshold).
     */
    public function exceedsThreshold(float $previousPrice, float $newPrice, float $thresholdRatio = 0.05): bool
    {
        return $this->relativeChangeAbs($previousPrice, $newPrice) > $thresholdRatio;
    }
}
