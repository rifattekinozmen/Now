<?php

namespace App\Services\Logistics;

/**
 * Faz B — navlun tahmin iskeleti (Logistics dokümanındaki km × oran × tonaj fikrinin sade modeli).
 * Motorin endeksi / sözleşme baz fiyatı ve çoklu para birimi kur çarpanı sonraki iterasyonlarda eklenebilir.
 */
final class FreightCalculationService
{
    /**
     * @return non-empty-string ondalık gösterim (örn. "1234.56")
     */
    public function estimate(float $distanceKm, float $tonnage, float $ratePerKmPerTon = 0.025): string
    {
        $distanceKm = max(0, $distanceKm);
        $ton = max(0.1, $tonnage);
        $amount = round($distanceKm * $ratePerKmPerTon * $ton, 2);

        return number_format($amount, 2, '.', '');
    }
}
