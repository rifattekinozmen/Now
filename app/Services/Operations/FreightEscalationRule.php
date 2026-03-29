<?php

namespace App\Services\Operations;

use App\Contracts\Operations\OperationalNotifier;
use App\Models\Order;

/**
 * Navlun eşik kuralı: sapma anlamlıysa operasyonel bildirim üretir.
 */
final class FreightEscalationRule
{
    public function __construct(
        private FreightEscalationEvaluator $evaluator,
        private OperationalNotifier $notifier,
    ) {}

    /**
     * Sapma yoksa veya navlun tanımsızsa false; bildirim gittiyse true.
     */
    public function checkAndNotify(
        Order $order,
        float $referenceFreight,
        float $thresholdRatio = 0.05,
    ): bool {
        if (! $this->evaluator->orderFreightExceedsReference($order, $referenceFreight, $thresholdRatio)) {
            return false;
        }

        $amount = $order->freight_amount;

        $this->notifier->notify('logistics.freight.threshold_exceeded', [
            'order_id' => $order->id,
            'tenant_id' => $order->tenant_id,
            'reference_freight' => $referenceFreight,
            'actual_freight' => $amount !== null ? (float) $amount : null,
            'threshold_ratio' => $thresholdRatio,
        ]);

        return true;
    }
}
