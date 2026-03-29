<?php

namespace App\Services\Finance;

use App\Enums\OrderStatus;
use App\Models\Order;
use Carbon\CarbonInterface;

/**
 * Sipariş navlunu üzerinden operasyonel alacak yaşlandırması (tahsilat vadesi = sipariş + vade günü).
 *
 * Ödeme kaydı olmadığı için fiili tahsilat değildir; operasyonel özetdir.
 */
final class ReceivablesAgingService
{
    private const BUCKET_CURRENT = 'current';

    private const BUCKET_1_30 = 'days_1_30';

    private const BUCKET_31_60 = 'days_31_60';

    private const BUCKET_61_90 = 'days_61_90';

    private const BUCKET_OVER_90 = 'days_over_90';

    /**
     * @return array{
     *     as_of: string,
     *     by_currency: array<string, array<string, array{count: int, amount: float}>>,
     *     customer_overdue: list<array{customer_id: int, customer_name: string, overdue_amount: float, currency_code: string, max_overdue_days: int}>
     * }
     */
    public function summarizeForTenant(int $tenantId, CarbonInterface $asOf): array
    {
        $asOfDay = $asOf->copy()->startOfDay();

        $orders = Order::query()
            ->withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('status', '!=', OrderStatus::Cancelled)
            ->whereNotNull('ordered_at')
            ->whereNotNull('freight_amount')
            ->where('freight_amount', '>', 0)
            ->with(['customer:id,legal_name,payment_term_days'])
            ->get();

        /** @var array<string, array<string, array{count: int, amount: float}>> $byCurrency */
        $byCurrency = [];
        /** @var array<string, array{amount: float, max_days: int}> $customerKeyed */
        $customerOverdueKeyed = [];

        foreach ($orders as $order) {
            $orderedAt = $order->ordered_at;
            if ($orderedAt === null) {
                continue;
            }

            $termDays = (int) ($order->customer?->payment_term_days ?? 30);
            $due = $orderedAt->copy()->addDays($termDays)->startOfDay();
            $bucket = $this->resolveBucket($due, $asOfDay);

            $currency = (string) ($order->currency_code ?? '—');
            $amount = (float) $order->freight_amount;

            if (! isset($byCurrency[$currency])) {
                $byCurrency[$currency] = $this->emptyBuckets();
            }
            $byCurrency[$currency][$bucket]['count']++;
            $byCurrency[$currency][$bucket]['amount'] += $amount;

            if ($bucket !== self::BUCKET_CURRENT && $order->customer_id !== null) {
                $overdueDays = (int) $due->diffInDays($asOfDay);
                $key = $order->customer_id.'|'.$currency;
                if (! isset($customerOverdueKeyed[$key])) {
                    $customerOverdueKeyed[$key] = [
                        'customer_id' => (int) $order->customer_id,
                        'customer_name' => (string) ($order->customer?->legal_name ?? ''),
                        'currency_code' => $currency,
                        'overdue_amount' => 0.0,
                        'max_overdue_days' => 0,
                    ];
                }
                $customerOverdueKeyed[$key]['overdue_amount'] += $amount;
                $customerOverdueKeyed[$key]['max_overdue_days'] = max(
                    $customerOverdueKeyed[$key]['max_overdue_days'],
                    $overdueDays
                );
            }
        }

        $customerOverdue = array_values($customerOverdueKeyed);
        usort(
            $customerOverdue,
            static fn (array $a, array $b): int => $b['overdue_amount'] <=> $a['overdue_amount']
        );

        ksort($byCurrency);

        return [
            'as_of' => $asOfDay->toDateString(),
            'by_currency' => $byCurrency,
            'customer_overdue' => $customerOverdue,
        ];
    }

    /**
     * @return array<string, array{count: int, amount: float}>
     */
    private function emptyBuckets(): array
    {
        $mk = static fn (): array => ['count' => 0, 'amount' => 0.0];

        return [
            self::BUCKET_CURRENT => $mk(),
            self::BUCKET_1_30 => $mk(),
            self::BUCKET_31_60 => $mk(),
            self::BUCKET_61_90 => $mk(),
            self::BUCKET_OVER_90 => $mk(),
        ];
    }

    private function resolveBucket(CarbonInterface $due, CarbonInterface $asOfDay): string
    {
        if ($due->gte($asOfDay)) {
            return self::BUCKET_CURRENT;
        }

        $overdueDays = (int) $due->diffInDays($asOfDay);
        if ($overdueDays <= 30) {
            return self::BUCKET_1_30;
        }
        if ($overdueDays <= 60) {
            return self::BUCKET_31_60;
        }
        if ($overdueDays <= 90) {
            return self::BUCKET_61_90;
        }

        return self::BUCKET_OVER_90;
    }
}
