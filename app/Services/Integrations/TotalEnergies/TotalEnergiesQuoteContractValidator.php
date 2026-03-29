<?php

namespace App\Services\Integrations\TotalEnergies;

/**
 * TotalEnergies JSON yanıtının yapılandırılmış sözleşme (schema_version) ile uyumu.
 */
final class TotalEnergiesQuoteContractValidator
{
    /**
     * @return array{
     *     contract_valid: bool,
     *     schema_match: bool|null,
     *     issues: list<string>
     * }
     */
    public static function validate(array $json, ?float $parsedPrice): array
    {
        $issues = [];
        if ($parsedPrice === null) {
            $issues[] = 'missing_price';
        }

        $sv = data_get($json, 'schema_version')
            ?? data_get($json, 'meta.schema_version')
            ?? data_get($json, 'schemaVersion');
        $expected = TotalEnergiesResponseParser::configuredSchemaVersion();
        $schemaMatch = null;
        if ($sv !== null && (is_numeric($sv) || (is_string($sv) && trim($sv) !== '' && is_numeric(trim($sv))))) {
            $schemaMatch = (int) $sv === $expected;
            if ($schemaMatch === false) {
                $issues[] = 'schema_version_mismatch';
            }
        }

        $contractValid = $parsedPrice !== null && $schemaMatch !== false;

        return [
            'contract_valid' => $contractValid,
            'schema_match' => $schemaMatch,
            'issues' => $issues,
        ];
    }
}
