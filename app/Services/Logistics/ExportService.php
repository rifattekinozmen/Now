<?php

namespace App\Services\Logistics;

use App\Models\Customer;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Voucher;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ExportService
{
    public function __construct(
        private ExcelImportService $excelImport,
    ) {}

    /**
     * Kiracı kapsamlı müşteri listesini, içe aktarma ile aynı başlık sırasında UTF-8 CSV olarak indirir.
     *
     * @param  iterable<int, Customer>  $customers
     */
    public function streamCustomersCsv(iterable $customers, string $filename = 'customers.csv'): StreamedResponse
    {
        $mapping = $this->excelImport->getCustomerImportMapping();

        return response()->streamDownload(function () use ($customers, $mapping): void {
            $out = fopen('php://output', 'w');
            if ($out === false) {
                return;
            }

            fprintf($out, "\xEF\xBB\xBF");
            fputcsv($out, array_keys($mapping));

            foreach ($customers as $customer) {
                $row = [];
                foreach ($mapping as $field) {
                    $value = $customer->getAttribute($field);
                    $row[] = $value === null ? '' : (string) $value;
                }
                fputcsv($out, $row);
            }

            fclose($out);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    /**
     * Test ve doğrulama için tam CSV gövdesi (UTF-8 BOM dahil).
     *
     * @param  iterable<int, Customer>  $customers
     */
    public function customersCsvContent(iterable $customers): string
    {
        $mapping = $this->excelImport->getCustomerImportMapping();
        $buffer = fopen('php://memory', 'r+');
        if ($buffer === false) {
            return '';
        }

        fprintf($buffer, "\xEF\xBB\xBF");
        fputcsv($buffer, array_keys($mapping));

        foreach ($customers as $customer) {
            $row = [];
            foreach ($mapping as $field) {
                $value = $customer->getAttribute($field);
                $row[] = $value === null ? '' : (string) $value;
            }
            fputcsv($buffer, $row);
        }

        rewind($buffer);
        $content = stream_get_contents($buffer) ?: '';
        fclose($buffer);

        return $content;
    }

    /**
     * Finans özeti / operasyon raporu için kiracı kapsamlı sipariş CSV (UTF-8 BOM).
     *
     * @param  iterable<int, Order>  $orders
     */
    public function streamOrdersFinanceCsv(iterable $orders, string $filename = 'orders-finance.csv'): StreamedResponse
    {
        $headers = [
            'order_number',
            'status',
            'customer_legal_name',
            'currency_code',
            'freight_amount',
            'exchange_rate',
            'ordered_at',
            'sas_no',
        ];

        return response()->streamDownload(function () use ($orders, $headers): void {
            $out = fopen('php://output', 'w');
            if ($out === false) {
                return;
            }

            fprintf($out, "\xEF\xBB\xBF");
            fputcsv($out, $headers);

            foreach ($orders as $order) {
                if (! $order instanceof Order) {
                    continue;
                }

                fputcsv($out, [
                    $order->order_number,
                    $order->status->value,
                    $order->customer?->legal_name ?? '',
                    $order->currency_code,
                    $order->freight_amount ?? '',
                    $order->exchange_rate ?? '',
                    $order->ordered_at?->toIso8601String() ?? '',
                    $order->sas_no ?? '',
                ]);
            }

            fclose($out);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    /**
     * Kiracı kapsamlı ödeme listesini CSV olarak indirir.
     *
     * @param  iterable<int, Payment>  $payments
     */
    public function streamPaymentsCsv(iterable $payments, string $filename = 'payments.csv'): StreamedResponse
    {
        $headers = [
            'id',
            'payment_date',
            'due_date',
            'amount',
            'currency_code',
            'payment_method',
            'status',
            'reference_no',
            'notes',
            'approved_at',
        ];

        return response()->streamDownload(function () use ($payments, $headers): void {
            $out = fopen('php://output', 'w');
            if ($out === false) {
                return;
            }

            fprintf($out, "\xEF\xBB\xBF");
            fputcsv($out, $headers);

            foreach ($payments as $payment) {
                if (! $payment instanceof Payment) {
                    continue;
                }

                fputcsv($out, [
                    $payment->id,
                    $payment->payment_date?->toDateString() ?? '',
                    $payment->due_date?->toDateString() ?? '',
                    $payment->amount ?? '',
                    $payment->currency_code,
                    $payment->payment_method?->value ?? '',
                    $payment->status?->value ?? '',
                    $payment->reference_no ?? '',
                    $payment->notes ?? '',
                    $payment->approved_at?->toIso8601String() ?? '',
                ]);
            }

            fclose($out);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    /**
     * Kiracı kapsamlı fiş/makbuz listesini CSV olarak indirir.
     *
     * @param  iterable<int, Voucher>  $vouchers
     */
    public function streamVouchersCsv(iterable $vouchers, string $filename = 'vouchers.csv'): StreamedResponse
    {
        $headers = [
            'id',
            'voucher_date',
            'type',
            'status',
            'amount',
            'currency_code',
            'reference_no',
            'description',
            'approved_at',
        ];

        return response()->streamDownload(function () use ($vouchers, $headers): void {
            $out = fopen('php://output', 'w');
            if ($out === false) {
                return;
            }

            fprintf($out, "\xEF\xBB\xBF");
            fputcsv($out, $headers);

            foreach ($vouchers as $voucher) {
                if (! $voucher instanceof Voucher) {
                    continue;
                }

                fputcsv($out, [
                    $voucher->id,
                    $voucher->voucher_date?->toDateString() ?? '',
                    $voucher->type?->value ?? '',
                    $voucher->status?->value ?? '',
                    $voucher->amount ?? '',
                    $voucher->currency_code,
                    $voucher->reference_no ?? '',
                    $voucher->description ?? '',
                    $voucher->approved_at?->toIso8601String() ?? '',
                ]);
            }

            fclose($out);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }
}
