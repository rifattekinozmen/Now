<?php

namespace App\Services\Integrations\Logo;

use App\Enums\OrderStatus;
use App\Models\Order;
use BackedEnum;
use DOMDocument;
use DOMElement;

/**
 * Logo ERP Connect tarzı basit XML çıktısı (Faz 3 — operasyonel ara dosya).
 */
final class LogoErpExportService
{
    /**
     * @param  iterable<int, Order>  $orders
     */
    public function buildOrdersConnectXml(iterable $orders): string
    {
        $dom = new DOMDocument('1.0', 'UTF-8');
        $dom->formatOutput = true;

        $root = $dom->createElement('LogoConnectExport');
        $root->setAttribute('generatedAt', now()->toIso8601String());
        $root->setAttribute('schemaVersion', '1');
        $dom->appendChild($root);

        foreach ($orders as $order) {
            $root->appendChild($this->orderElement($dom, $order));
        }

        return $dom->saveXML() ?: '';
    }

    private function orderElement(DOMDocument $dom, Order $order): DOMElement
    {
        $el = $dom->createElement('Order');
        $this->appendTextChild($dom, $el, 'OrderRecordId', (string) $order->getKey());
        $this->appendTextChild($dom, $el, 'OrderNumber', (string) $order->order_number);
        $this->appendTextChild($dom, $el, 'SasNo', $order->sas_no !== null ? (string) $order->sas_no : '');
        $this->appendTextChild($dom, $el, 'Currency', $order->currency_code !== null ? (string) $order->currency_code : '');
        $freight = $order->freight_amount !== null ? (string) $order->freight_amount : '';
        $this->appendTextChild($dom, $el, 'FreightAmount', $freight);
        $customerName = $order->relationLoaded('customer') && $order->customer !== null
            ? (string) $order->customer->legal_name
            : '';
        $this->appendTextChild($dom, $el, 'CustomerLegalName', $customerName);
        $partnerNo = '';
        if ($order->relationLoaded('customer') && $order->customer !== null && $order->customer->partner_number !== null) {
            $partnerNo = (string) $order->customer->partner_number;
        }
        $this->appendTextChild($dom, $el, 'CustomerPartnerNo', $partnerNo);
        $taxId = '';
        if ($order->relationLoaded('customer') && $order->customer !== null && $order->customer->tax_id !== null) {
            $taxId = (string) $order->customer->tax_id;
        }
        $this->appendTextChild($dom, $el, 'CustomerTaxId', $taxId);
        $tradeName = '';
        if ($order->relationLoaded('customer') && $order->customer !== null && $order->customer->trade_name !== null) {
            $tradeName = (string) $order->customer->trade_name;
        }
        $this->appendTextChild($dom, $el, 'CustomerTradeName', $tradeName);
        $paymentTerm = '';
        if ($order->relationLoaded('customer') && $order->customer !== null && $order->customer->payment_term_days !== null) {
            $paymentTerm = (string) $order->customer->payment_term_days;
        }
        $this->appendTextChild($dom, $el, 'CustomerPaymentTermDays', $paymentTerm);
        $this->appendConfiguredOrderFields($dom, $el, $order);

        return $el;
    }

    private function appendConfiguredOrderFields(DOMDocument $dom, DOMElement $el, Order $order): void
    {
        $map = config('logo_export.order_fields');
        if (! is_array($map)) {
            return;
        }

        foreach ($map as $xmlName => $attribute) {
            if (! is_string($xmlName) || ! is_string($attribute)) {
                continue;
            }
            $this->appendTextChild($dom, $el, $xmlName, $this->orderFieldValue($order, $attribute));
        }
    }

    private function orderFieldValue(Order $order, string $attribute): string
    {
        if ($attribute === 'ordered_at') {
            return $order->ordered_at !== null ? $order->ordered_at->toIso8601String() : '';
        }

        if ($attribute === 'status') {
            $s = $order->status;
            if ($s instanceof OrderStatus) {
                return $s->value;
            }
            if ($s instanceof BackedEnum) {
                return (string) $s->value;
            }

            return '';
        }

        if (str_starts_with($attribute, 'meta.')) {
            $path = substr($attribute, 5);
            $meta = $order->getAttribute('meta');
            if (! is_array($meta)) {
                return '';
            }
            $v = data_get($meta, $path);
            if ($v === null) {
                return '';
            }
            if (is_scalar($v) || $v instanceof \Stringable) {
                return (string) $v;
            }

            return '';
        }

        $v = $order->getAttribute($attribute);
        if ($v === null) {
            return '';
        }
        if (is_scalar($v) || $v instanceof \Stringable) {
            return (string) $v;
        }

        return '';
    }

    private function appendTextChild(DOMDocument $dom, DOMElement $parent, string $name, string $value): void
    {
        $child = $dom->createElement($name);
        $child->appendChild($dom->createTextNode($value));
        $parent->appendChild($child);
    }
}
