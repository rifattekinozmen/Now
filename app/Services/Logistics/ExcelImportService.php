<?php

namespace App\Services\Logistics;

use App\Enums\DeliveryNumberStatus;
use App\Models\Customer;
use App\Models\DeliveryNumber;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use PhpOffice\PhpSpreadsheet\IOFactory;

class ExcelImportService
{
    /**
     * Excel başlık etiketi (satır 1) => iç model alanı.
     * Başlıklar UI etiketi ile birebir eşleşmeli (architecture.md).
     *
     * @return array<string, string>
     */
    public function getCustomerImportMapping(): array
    {
        return [
            'İş Ortağı No' => 'partner_number',
            'Vergi No' => 'tax_id',
            'Ünvan' => 'legal_name',
            'Ticari Unvan' => 'trade_name',
            'Vade Gün' => 'payment_term_days',
        ];
    }

    /**
     * Ham satır (başlık => değer) ve eşleme ile normalize edilmiş öznitelikler.
     *
     * @param  array<string, mixed>  $rowByLabel
     * @param  array<string, string>  $labelToField
     * @return array<string, mixed>
     */
    public function normalizeRow(array $rowByLabel, array $labelToField): array
    {
        $attributes = [];
        foreach ($labelToField as $label => $field) {
            if (! array_key_exists($label, $rowByLabel)) {
                continue;
            }
            $attributes[$field] = $this->normalizeCustomerValue($field, $rowByLabel[$label]);
        }

        return $attributes;
    }

    /**
     * @return array{created: int, errors: list<array{row: int, message: string}>}
     */
    public function importCustomersFromPath(string $path, int $tenantId): array
    {
        $mapping = $this->getCustomerImportMapping();
        $matrix = $this->loadMatrixFromFile($path);
        if ($matrix === []) {
            return ['created' => 0, 'errors' => []];
        }

        $headerRow = array_shift($matrix);
        if (! is_array($headerRow)) {
            return ['created' => 0, 'errors' => []];
        }

        /** @var list<string> $headers */
        $headers = array_map(function (mixed $cell): string {
            if ($cell === null) {
                return '';
            }

            return is_string($cell) ? trim($cell) : trim((string) $cell);
        }, $headerRow);

        $created = 0;
        /** @var list<array{row: int, message: string}> $errors */
        $errors = [];

        foreach ($matrix as $offset => $row) {
            if (! is_array($row)) {
                continue;
            }

            $rowNumber = $offset + 2;
            if ($this->rowIsEmpty($row)) {
                continue;
            }

            $assoc = [];
            foreach ($headers as $index => $header) {
                if ($header === '') {
                    continue;
                }
                $assoc[$header] = $row[$index] ?? null;
            }

            try {
                $attributes = $this->normalizeRow($assoc, $mapping);
                $attributes['tenant_id'] = $tenantId;
                $this->validateCustomerAttributes($attributes);
                Customer::query()->create($attributes);
                $created++;
            } catch (ValidationException $e) {
                $errors[] = [
                    'row' => $rowNumber,
                    'message' => $e->validator->errors()->first() ?? $e->getMessage(),
                ];
            } catch (\Throwable $e) {
                $errors[] = [
                    'row' => $rowNumber,
                    'message' => $e->getMessage(),
                ];
            }
        }

        return ['created' => $created, 'errors' => $errors];
    }

    /**
     * CSV / XLSX ilk satır başlıkları (Türkçe veya İngilizce) → `pin_code` / `sas_no`.
     *
     * @return array<string, string>
     */
    public function getDeliveryPinImportMapping(): array
    {
        return [
            'pin_code' => 'pin_code',
            'PIN' => 'pin_code',
            'PIN Kodu' => 'pin_code',
            'SAS' => 'sas_no',
            'SAS No' => 'sas_no',
            'sas_no' => 'sas_no',
        ];
    }

    /**
     * @return array{created: int, skipped: int, errors: list<array{row: int, message: string}>}
     */
    public function importDeliveryPinsFromPath(string $path, int $tenantId): array
    {
        $mapping = $this->getDeliveryPinImportMapping();
        $matrix = $this->loadMatrixFromFile($path);
        if ($matrix === []) {
            return ['created' => 0, 'skipped' => 0, 'errors' => []];
        }

        $headerRow = array_shift($matrix);
        if (! is_array($headerRow)) {
            return ['created' => 0, 'skipped' => 0, 'errors' => []];
        }

        /** @var list<string> $headers */
        $headers = array_map(function (mixed $cell): string {
            if ($cell === null) {
                return '';
            }

            return is_string($cell) ? trim($cell) : trim((string) $cell);
        }, $headerRow);

        $created = 0;
        $skipped = 0;
        /** @var list<array{row: int, message: string}> $errors */
        $errors = [];
        /** @var array<string, true> $seenInFile */
        $seenInFile = [];

        foreach ($matrix as $offset => $row) {
            if (! is_array($row)) {
                continue;
            }

            $rowNumber = $offset + 2;
            if ($this->rowIsEmpty($row)) {
                continue;
            }

            $assoc = [];
            foreach ($headers as $index => $header) {
                if ($header === '') {
                    continue;
                }
                $assoc[$header] = $row[$index] ?? null;
            }

            try {
                $attributes = $this->normalizeRow($assoc, $mapping);
                $attributes['tenant_id'] = $tenantId;
                $this->validateDeliveryPinAttributes($attributes);

                $pin = (string) $attributes['pin_code'];
                if (isset($seenInFile[$pin])) {
                    $errors[] = [
                        'row' => $rowNumber,
                        'message' => __('Duplicate PIN in file.'),
                    ];

                    continue;
                }
                $seenInFile[$pin] = true;

                $exists = DeliveryNumber::withoutGlobalScopes()
                    ->where('tenant_id', $tenantId)
                    ->where('pin_code', $pin)
                    ->exists();

                if ($exists) {
                    $skipped++;

                    continue;
                }

                DeliveryNumber::withoutGlobalScopes()->create([
                    'tenant_id' => $tenantId,
                    'pin_code' => $pin,
                    'sas_no' => $attributes['sas_no'] ?? null,
                    'status' => DeliveryNumberStatus::Available,
                    'order_id' => null,
                    'shipment_id' => null,
                    'assigned_at' => null,
                    'used_at' => null,
                    'meta' => null,
                ]);
                $created++;
            } catch (ValidationException $e) {
                $errors[] = [
                    'row' => $rowNumber,
                    'message' => $e->validator->errors()->first() ?? $e->getMessage(),
                ];
            } catch (\Throwable $e) {
                $errors[] = [
                    'row' => $rowNumber,
                    'message' => $e->getMessage(),
                ];
            }
        }

        return ['created' => $created, 'skipped' => $skipped, 'errors' => $errors];
    }

    /**
     * @return list<list<mixed>>
     */
    private function loadMatrixFromFile(string $path): array
    {
        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        if ($ext === 'csv') {
            return $this->parseCsvToMatrix($path);
        }

        if (! class_exists(IOFactory::class)) {
            throw new \RuntimeException(
                'PhpSpreadsheet yüklü değil. `.xlsx` için `composer require maatwebsite/excel` çalıştırın veya `.csv` kullanın.'
            );
        }

        $spreadsheet = IOFactory::load($path);

        return $spreadsheet->getActiveSheet()->toArray();
    }

    /**
     * @return list<list<mixed>>
     */
    private function parseCsvToMatrix(string $path): array
    {
        $matrix = [];
        $handle = fopen($path, 'rb');
        if ($handle === false) {
            throw new \RuntimeException('CSV dosyası açılamadı.');
        }

        try {
            while (($row = fgetcsv($handle, 0, ',')) !== false) {
                $matrix[] = $row;
            }
        } finally {
            fclose($handle);
        }

        if ($matrix !== [] && isset($matrix[0][0]) && is_string($matrix[0][0]) && str_starts_with($matrix[0][0], "\xEF\xBB\xBF")) {
            $matrix[0][0] = substr($matrix[0][0], 3);
        }

        return $matrix;
    }

    /**
     * @param  list<mixed>  $row
     */
    private function rowIsEmpty(array $row): bool
    {
        foreach ($row as $value) {
            if ($value !== null && $value !== '') {
                return false;
            }
        }

        return true;
    }

    private function normalizeCustomerValue(string $field, mixed $value): mixed
    {
        if ($value === null) {
            return null;
        }

        if (is_string($value)) {
            $value = trim($value);
        }

        if ($value === '') {
            return null;
        }

        return match ($field) {
            'payment_term_days' => is_numeric($value) ? (int) $value : null,
            'partner_number', 'tax_id', 'legal_name', 'trade_name', 'pin_code', 'sas_no' => is_scalar($value) ? (string) $value : null,
            default => $value,
        };
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function validateCustomerAttributes(array $attributes): void
    {
        Validator::make($attributes, [
            'tenant_id' => ['required', 'integer', 'exists:tenants,id'],
            'legal_name' => ['required', 'string', 'max:255'],
            'partner_number' => ['nullable', 'string', 'max:100'],
            'tax_id' => ['nullable', 'string', 'max:32'],
            'trade_name' => ['nullable', 'string', 'max:255'],
            'payment_term_days' => ['nullable', 'integer', 'min:0', 'max:365'],
        ])->validate();
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function validateDeliveryPinAttributes(array $attributes): void
    {
        Validator::make($attributes, [
            'tenant_id' => ['required', 'integer', 'exists:tenants,id'],
            'pin_code' => ['required', 'string', 'max:64'],
            'sas_no' => ['nullable', 'string', 'max:64'],
        ])->validate();
    }
}
