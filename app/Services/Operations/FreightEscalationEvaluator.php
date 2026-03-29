<?php

namespace App\Services\Operations;

use App\Models\Order;
use App\Services\Logistics\NavlunEskalasyonService;

/**
 * Sipariş navlunu ile referans tutar arasındaki göreli sapmayı Navlun eşik mantığıyla değerlendirir.
 */
final class FreightEscalationEvaluator
{
    public function __construct(
        private NavlunEskalasyonService $navlunEskalasyon,
    ) {}

    /**
     * Referans tutara göre navlun değişimi eşiği aşıyor mu? (siparişte navlun yoksa false.)
     */
    public function orderFreightExceedsReference(
        Order $order,
        float $referenceFreight,
        float $thresholdRatio = 0.05,
    ): bool {
        $amount = $order->freight_amount;

        if ($amount === null) {
            return false;
        }

        return $this->navlunEskalasyon->exceedsThreshold(
            $referenceFreight,
            (float) $amount,
            $thresholdRatio,
        );
    }
}
