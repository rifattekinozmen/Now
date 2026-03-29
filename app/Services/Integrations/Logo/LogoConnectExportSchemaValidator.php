<?php

namespace App\Services\Integrations\Logo;

use DOMDocument;
use DOMElement;

/**
 * Logo Connect XML çıktısı için yapı doğrulama (operasyonel şema; resmi Logo dokümanı ayrıdır).
 */
final class LogoConnectExportSchemaValidator
{
    public const EXPECTED_ROOT = 'LogoConnectExport';

    public const EXPECTED_SCHEMA_VERSION = '1';

    /**
     * @return array{valid: bool, errors: list<string>}
     */
    public function validate(string $xml): array
    {
        $errors = [];
        $previous = libxml_use_internal_errors(true);
        $doc = new DOMDocument;
        $loaded = $doc->loadXML($xml);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        if (! $loaded) {
            return ['valid' => false, 'errors' => ['invalid_xml']];
        }

        $root = $doc->documentElement;
        if (! $root instanceof DOMElement) {
            return ['valid' => false, 'errors' => ['missing_root']];
        }

        if ($root->tagName !== self::EXPECTED_ROOT) {
            $errors[] = 'unexpected_root_element';

            return ['valid' => false, 'errors' => $errors];
        }

        $sv = $root->getAttribute('schemaVersion');
        if ($sv !== self::EXPECTED_SCHEMA_VERSION) {
            $errors[] = 'schema_version_mismatch';
        }

        $orders = $doc->getElementsByTagName('Order');
        for ($i = 0; $i < $orders->length; $i++) {
            $order = $orders->item($i);
            if (! $order instanceof DOMElement) {
                continue;
            }
            if ($this->hasChildText($order, 'OrderRecordId') === '') {
                $errors[] = 'order_missing_OrderRecordId';
            }
            if ($this->hasChildText($order, 'OrderNumber') === '') {
                $errors[] = 'order_missing_OrderNumber';
            }
        }

        return [
            'valid' => $errors === [],
            'errors' => $errors,
        ];
    }

    private function hasChildText(DOMElement $parent, string $name): string
    {
        foreach ($parent->childNodes as $child) {
            if ($child instanceof DOMElement && $child->tagName === $name) {
                return trim($child->textContent);
            }
        }

        return '';
    }
}
