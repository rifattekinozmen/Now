<?php

namespace App\Services\Finance;

use App\Models\Order;
use Carbon\CarbonInterface;

/**
 * Vade ve nakit akışı projeksiyonu: sipariş tarihi + müşteri vade günü ile tahmini tahsilat penceresi.
 *
 * Operasyonel özetdir; muhasebesel veya hukuki tavsiye değildir.
 */
final class CashFlowProjectionService
{
    /**
     * @return list<array{order_id: int, order_number: string, due_date: string, amount: string|null, currency_code: string|null, customer_name: string|null}>
     */
    public function projectForTenant(int $tenantId, CarbonInterface $from, CarbonInterface $to): array
    {
        $fromDay = $from->copy()->startOfDay();
        $toDay = $to->copy()->endOfDay();

        $orders = Order::query()
            ->withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->whereNotNull('ordered_at')
            ->with(['customer:id,legal_name,payment_term_days'])
            ->orderBy('ordered_at')
            ->get();

        $out = [];
        foreach ($orders as $order) {
            $orderedAt = $order->ordered_at;
            if ($orderedAt === null) {
                continue;
            }

            $days = (int) ($order->customer?->payment_term_days ?? 30);
            $due = $orderedAt->copy()->addDays($days);

            if ($due->lt($fromDay) || $due->gt($toDay)) {
                continue;
            }

            $out[] = [
                'order_id' => (int) $order->id,
                'order_number' => (string) $order->order_number,
                'due_date' => $due->toDateString(),
                'amount' => $order->freight_amount !== null ? (string) $order->freight_amount : null,
                'currency_code' => $order->currency_code,
                'customer_name' => $order->customer?->legal_name,
            ];
        }

        usort($out, fn (array $a, array $b): int => strcmp($a['due_date'], $b['due_date']));

        return $out;
    }
}
