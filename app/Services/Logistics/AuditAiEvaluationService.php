<?php

namespace App\Services\Logistics;

use App\Models\Order;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Operasyonel uyarı kuralları (yakıt hacmi / navlun teklifi sapması). Hukuki veya muhasebesel tavsiye değildir.
 */
final class AuditAiEvaluationService
{
    /**
     * @param  array<string, mixed>  $context
     * @return array{status: string, flagged: bool, reasons: list<string>}
     */
    public function evaluateFreightQuote(array $context): array
    {
        $quoted = isset($context['quoted_freight']) ? (float) $context['quoted_freight'] : null;
        $reference = isset($context['reference_freight']) ? (float) $context['reference_freight'] : null;

        if ($quoted === null || $reference === null || $reference <= 0.0) {
            return ['status' => 'skipped', 'flagged' => false, 'reasons' => []];
        }

        $deviationPercent = abs($quoted - $reference) / $reference * 100.0;
        if ($deviationPercent > 20.0) {
            return [
                'status' => 'flagged',
                'flagged' => true,
                'reasons' => [__('Freight quote deviates more than :pct% from reference.', ['pct' => 20])],
            ];
        }

        return ['status' => 'ok', 'flagged' => false, 'reasons' => []];
    }

    /**
     * Fiili yakıt litresi ile beklenen litresi karşılaştırır (varsayılan %15 eşik).
     *
     * @return array{status: string, flagged: bool, reasons: list<string>}
     */
    public function evaluateFuelVolumeAgainstExpected(
        ?float $litersActual,
        ?float $litersExpected,
        float $thresholdPercent = 15.0
    ): array {
        if ($litersActual === null || $litersExpected === null || $litersExpected <= 0.0) {
            return ['status' => 'skipped', 'flagged' => false, 'reasons' => []];
        }

        $deviationPercent = abs($litersActual - $litersExpected) / $litersExpected * 100.0;
        if ($deviationPercent > $thresholdPercent) {
            return [
                'status' => 'flagged',
                'flagged' => true,
                'reasons' => [__('Fuel volume deviates more than :pct% from expected.', ['pct' => $thresholdPercent])],
            ];
        }

        return ['status' => 'ok', 'flagged' => false, 'reasons' => []];
    }

    /**
     * Kiracı siparişlerinde, para birimi grubu içinde medyan navluna kıyasla %20 üzeri sapmaları listeler.
     *
     * @return array{evaluated_orders: int, flagged: list<array{order_id: int, order_number: string, currency_code: string|null, reasons: list<string>}>}
     */
    public function summarizeFreightOutliersAgainstMedian(
        int $tenantId,
        ?CarbonInterface $orderedFrom = null,
        ?CarbonInterface $orderedTo = null,
    ): array {
        $q = Order::query()
            ->withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->whereNotNull('freight_amount')
            ->where('freight_amount', '>', 0);

        $this->applyOrderedAtRange($q, $orderedFrom, $orderedTo);

        $orders = $q->get(['id', 'order_number', 'freight_amount', 'currency_code']);
        if ($orders->isEmpty()) {
            return ['evaluated_orders' => 0, 'flagged' => []];
        }

        $flagged = [];
        foreach ($orders->groupBy('currency_code') as $currency => $group) {
            /** @var Collection<int, Order> $group */
            $amounts = $group->map(fn (Order $o): float => (float) $o->freight_amount)->all();
            $median = $this->medianOfNumericList($amounts);
            if ($median <= 0.0) {
                continue;
            }

            foreach ($group as $order) {
                $eval = $this->evaluateFreightQuote([
                    'quoted_freight' => (float) $order->freight_amount,
                    'reference_freight' => $median,
                ]);
                if ($eval['flagged']) {
                    $flagged[] = [
                        'order_id' => (int) $order->id,
                        'order_number' => (string) $order->order_number,
                        'currency_code' => $order->currency_code,
                        'reasons' => $eval['reasons'],
                    ];
                }
            }
        }

        return [
            'evaluated_orders' => $orders->count(),
            'flagged' => $flagged,
        ];
    }

    /**
     * @param  Builder<Order>  $q
     */
    private function applyOrderedAtRange(Builder $q, ?CarbonInterface $from, ?CarbonInterface $to): void
    {
        if ($from !== null) {
            $q->where('ordered_at', '>=', $from->copy()->startOfDay());
        }
        if ($to !== null) {
            $q->where('ordered_at', '<=', $to->copy()->endOfDay());
        }
    }

    /**
     * @param  list<float|int>  $values
     */
    private function medianOfNumericList(array $values): float
    {
        $nums = array_values(array_filter(
            array_map(static fn (mixed $v): float => (float) $v, $values),
            static fn (float $v): bool => $v > 0.0
        ));
        $n = count($nums);
        if ($n === 0) {
            return 0.0;
        }
        sort($nums);
        $m = intdiv($n - 1, 2);
        if ($n % 2 === 1) {
            return $nums[$m];
        }

        return ($nums[$m] + $nums[$m + 1]) / 2.0;
    }
}
