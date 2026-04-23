<?php

namespace App\Services\Logistics;

use App\Enums\BusinessPartnerType;
use App\Enums\DeliveryNumberStatus;
use App\Enums\OrderStatus;
use App\Models\BusinessPartner;
use App\Models\Customer;
use App\Models\DeliveryNumber;
use App\Models\Employee;
use App\Models\FuelIntake;
use App\Models\FuelPrice;
use App\Models\Order;
use App\Models\Vehicle;
use Carbon\Carbon;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
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
     * @return array<string, string>
     */
    public function getBusinessPartnerImportMapping(): array
    {
        return [
            'İsim' => 'name',
            'Name' => 'name',
            'Tip' => 'type',
            'Type' => 'type',
            'Vergi No' => 'tax_no',
            'Tax No' => 'tax_no',
            'İletişim Kişisi' => 'contact_person',
            'Contact' => 'contact_person',
            'Telefon' => 'phone',
            'Phone' => 'phone',
            'Email' => 'email',
            'Şehir' => 'city',
            'City' => 'city',
            'Ülke' => 'country',
            'Country' => 'country',
            'IBAN' => 'iban',
            'Vade Gün' => 'payment_terms_days',
            'Payment Terms' => 'payment_terms_days',
        ];
    }

    /**
     * @return array{created: int, errors: list<array{row: int, message: string}>}
     */
    public function importBusinessPartnersFromPath(string $path, int $tenantId): array
    {
        $mapping = $this->getBusinessPartnerImportMapping();
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
                $this->validateBusinessPartnerAttributes($attributes);
                BusinessPartner::query()->create($attributes);
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
     * @param  array<string, mixed>  $attributes
     */
    private function validateBusinessPartnerAttributes(array $attributes): void
    {
        Validator::make($attributes, [
            'tenant_id' => ['required', 'integer', 'exists:tenants,id'],
            'name' => ['required', 'string', 'max:255'],
            'type' => ['nullable', 'string', 'in:'.implode(',', array_column(BusinessPartnerType::cases(), 'value'))],
            'tax_no' => ['nullable', 'string', 'max:20'],
            'contact_person' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:255'],
            'city' => ['nullable', 'string', 'max:100'],
            'country' => ['nullable', 'string', 'max:100'],
            'iban' => ['nullable', 'string', 'max:34'],
            'payment_terms_days' => ['nullable', 'integer', 'min:0', 'max:3650'],
        ])->validate();
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
    private function normalizeImportDate(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        if ($value instanceof \DateTimeInterface) {
            return $value->format('Y-m-d');
        }

        if (is_string($value) && trim($value) !== '') {
            try {
                return Carbon::parse($value)->toDateString();
            } catch (\Throwable) {
                return null;
            }
        }

        return null;
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
            'payment_term_days', 'payment_terms_days' => is_numeric($value) ? (int) $value : null,
            'partner_number', 'tax_id', 'legal_name', 'trade_name', 'pin_code', 'sas_no',
            'customer_legal_name', 'currency_code', 'loading_site', 'unloading_site',
            'plate', 'vin', 'brand', 'model', 'first_name', 'last_name', 'national_id', 'blood_group', 'phone',
            'name', 'tax_no', 'contact_person', 'city', 'country', 'iban', 'type', 'email', 'address' => is_scalar($value) ? trim((string) $value) : null,
            'distance_km', 'tonnage', 'gross_weight_kg', 'tara_weight_kg', 'net_weight_kg', 'moisture_percent' => is_numeric($value) ? $value : null,
            'liters' => is_numeric($value) ? $value : null,
            'odometer_km' => is_numeric($value) ? $value : null,
            'inspection_valid_until' => $this->normalizeImportDate($value),
            'recorded_at' => $this->normalizeImportDateTime($value),
            default => $value,
        };
    }

    /**
     * @param  list<mixed>  $row
     */
    private function normalizeImportDateTime(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        if ($value instanceof \DateTimeInterface) {
            return $value->format('Y-m-d H:i:s');
        }

        if (is_string($value) && trim($value) !== '') {
            try {
                return Carbon::parse($value)->format('Y-m-d H:i:s');
            } catch (\Throwable) {
                return null;
            }
        }

        if (is_numeric($value)) {
            try {
                return Carbon::createFromTimestampMs((int) $value)->format('Y-m-d H:i:s');
            } catch (\Throwable) {
                return null;
            }
        }

        return null;
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

    /**
     * @return array<string, string>
     */
    public function getOrderImportMapping(): array
    {
        return [
            'Ünvan' => 'customer_legal_name',
            'Customer' => 'customer_legal_name',
            'Müşteri' => 'customer_legal_name',
            'Para Birimi' => 'currency_code',
            'Currency' => 'currency_code',
            'SAS' => 'sas_no',
            'SAS / PO' => 'sas_no',
            'Yükleme' => 'loading_site',
            'Boşaltma' => 'unloading_site',
            'Mesafe (km)' => 'distance_km',
            'Mesafe' => 'distance_km',
            'Tonaj' => 'tonnage',
            'Dolu' => 'gross_weight_kg',
            'Gross' => 'gross_weight_kg',
            'Boş' => 'tara_weight_kg',
            'Tara' => 'tara_weight_kg',
            'Geçerli Miktar' => 'net_weight_kg',
            'Net' => 'net_weight_kg',
            'Rutubet' => 'moisture_percent',
            'Rutubet %' => 'moisture_percent',
        ];
    }

    /**
     * @return array{created: int, errors: list<array{row: int, message: string}>}
     */
    public function importOrdersFromPath(string $path, int $tenantId): array
    {
        $mapping = $this->getOrderImportMapping();
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
                $raw = $this->normalizeRow($assoc, $mapping);
                $legalName = isset($raw['customer_legal_name']) ? trim((string) $raw['customer_legal_name']) : '';
                if ($legalName === '') {
                    throw new \InvalidArgumentException(__('Customer legal name is required.'));
                }

                $customer = Customer::query()
                    ->where('tenant_id', $tenantId)
                    ->where('legal_name', $legalName)
                    ->first();
                if ($customer === null) {
                    throw new \InvalidArgumentException(__('Unknown customer: :name', ['name' => $legalName]));
                }

                $currency = strtoupper(trim((string) ($raw['currency_code'] ?? 'TRY')));
                if (strlen($currency) !== 3) {
                    $currency = 'TRY';
                }

                $gross = isset($raw['gross_weight_kg']) && is_numeric($raw['gross_weight_kg']) ? (string) $raw['gross_weight_kg'] : null;
                $tara = isset($raw['tara_weight_kg']) && is_numeric($raw['tara_weight_kg']) ? (string) $raw['tara_weight_kg'] : null;
                $net = isset($raw['net_weight_kg']) && is_numeric($raw['net_weight_kg']) ? (string) $raw['net_weight_kg'] : null;
                if ($net === null && $gross !== null && $tara !== null) {
                    $net = (string) round((float) $gross - (float) $tara, 3);
                }

                Order::query()->create([
                    'tenant_id' => $tenantId,
                    'customer_id' => $customer->id,
                    'order_number' => $this->uniqueOrderNumber(),
                    'sas_no' => isset($raw['sas_no']) && $raw['sas_no'] !== '' ? (string) $raw['sas_no'] : null,
                    'status' => OrderStatus::Draft,
                    'ordered_at' => now(),
                    'currency_code' => $currency,
                    'distance_km' => isset($raw['distance_km']) && is_numeric($raw['distance_km']) ? $raw['distance_km'] : null,
                    'tonnage' => isset($raw['tonnage']) && is_numeric($raw['tonnage']) ? $raw['tonnage'] : null,
                    'gross_weight_kg' => $gross,
                    'tara_weight_kg' => $tara,
                    'net_weight_kg' => $net,
                    'moisture_percent' => isset($raw['moisture_percent']) && is_numeric($raw['moisture_percent']) ? $raw['moisture_percent'] : null,
                    'loading_site' => isset($raw['loading_site']) ? (string) $raw['loading_site'] : null,
                    'unloading_site' => isset($raw['unloading_site']) ? (string) $raw['unloading_site'] : null,
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

        return ['created' => $created, 'errors' => $errors];
    }

    /**
     * @return array<string, string>
     */
    public function getVehicleImportMapping(): array
    {
        return [
            'Plaka' => 'plate',
            'Plate' => 'plate',
            'Şasi' => 'vin',
            'Sasi' => 'vin',
            'VIN' => 'vin',
            'Marka' => 'brand',
            'Brand' => 'brand',
            'Model' => 'model',
            'Yıl' => 'manufacture_year',
            'Yil' => 'manufacture_year',
            'Year' => 'manufacture_year',
            'Muayene' => 'inspection_valid_until',
            'Inspection' => 'inspection_valid_until',
        ];
    }

    /**
     * @return array{created: int, errors: list<array{row: int, message: string}>}
     */
    public function importVehiclesFromPath(string $path, int $tenantId): array
    {
        $mapping = $this->getVehicleImportMapping();
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
                $raw = $this->normalizeRow($assoc, $mapping);
                $plate = strtoupper(trim((string) ($raw['plate'] ?? '')));
                if ($plate === '') {
                    throw new \InvalidArgumentException(__('Plate is required.'));
                }

                $exists = Vehicle::query()
                    ->where('tenant_id', $tenantId)
                    ->where('plate', $plate)
                    ->exists();
                if ($exists) {
                    throw new \InvalidArgumentException(__('Vehicle plate already exists: :p', ['p' => $plate]));
                }

                $vinCompact = isset($raw['vin']) ? preg_replace('/\s+/', '', (string) $raw['vin']) : '';
                $vin = $vinCompact !== '' ? strtoupper($vinCompact) : null;

                $manufactureYear = null;
                if (isset($raw['manufacture_year']) && $raw['manufacture_year'] !== '') {
                    $y = (int) $raw['manufacture_year'];
                    if ($y >= 1900 && $y <= (int) now()->addYears(2)->year) {
                        $manufactureYear = $y;
                    }
                }

                Vehicle::query()->create([
                    'tenant_id' => $tenantId,
                    'plate' => $plate,
                    'vin' => $vin,
                    'brand' => isset($raw['brand']) ? (string) $raw['brand'] : null,
                    'model' => isset($raw['model']) ? (string) $raw['model'] : null,
                    'manufacture_year' => $manufactureYear,
                    'inspection_valid_until' => $raw['inspection_valid_until'] ?? null,
                ]);
                $created++;
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
     * @return array<string, string>
     */
    public function getEmployeeImportMapping(): array
    {
        return [
            'Ad' => 'first_name',
            'Soyad' => 'last_name',
            'T.C.' => 'national_id',
            'TC' => 'national_id',
            'Kan' => 'blood_group',
            'Telefon' => 'phone',
        ];
    }

    /**
     * @return array{created: int, errors: list<array{row: int, message: string}>}
     */
    public function importEmployeesFromPath(string $path, int $tenantId): array
    {
        $mapping = $this->getEmployeeImportMapping();
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
                $raw = $this->normalizeRow($assoc, $mapping);
                $first = trim((string) ($raw['first_name'] ?? ''));
                $last = trim((string) ($raw['last_name'] ?? ''));
                if ($first === '' || $last === '') {
                    throw new \InvalidArgumentException(__('First and last name are required.'));
                }

                $nid = isset($raw['national_id']) ? preg_replace('/\D/', '', (string) $raw['national_id']) : '';
                $nid = $nid !== '' ? $nid : null;
                if ($nid !== null && strlen($nid) !== 11) {
                    throw new \InvalidArgumentException(__('National ID must be 11 digits.'));
                }

                if ($nid !== null && Employee::query()->where('tenant_id', $tenantId)->where('national_id', $nid)->exists()) {
                    throw new \InvalidArgumentException(__('Employee with this national ID already exists.'));
                }

                Employee::query()->create([
                    'tenant_id' => $tenantId,
                    'first_name' => $first,
                    'last_name' => $last,
                    'national_id' => $nid,
                    'blood_group' => isset($raw['blood_group']) ? (string) $raw['blood_group'] : null,
                    'phone' => isset($raw['phone']) ? (string) $raw['phone'] : null,
                    'is_driver' => false,
                ]);
                $created++;
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
     * Yakıt alımı CSV/XLSX; başlıklar şablon ile uyumlu (`FuelIntakeImportTemplateExport`).
     *
     * @return array<string, string>
     */
    public function getFuelIntakeImportMapping(): array
    {
        return [
            'Plaka' => 'plate',
            'Plate' => 'plate',
            'Litre' => 'liters',
            'Liters' => 'liters',
            'Litres' => 'liters',
            'Kilometre' => 'odometer_km',
            'Odometer (km)' => 'odometer_km',
            'Odometer' => 'odometer_km',
            'Kayıt Tarihi' => 'recorded_at',
            'Recorded at' => 'recorded_at',
        ];
    }

    /**
     * @return array{created: int, errors: list<array{row: int, message: string}>}
     */
    public function importFuelIntakesFromPath(string $path, int $tenantId): array
    {
        $mapping = $this->getFuelIntakeImportMapping();
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
                $raw = $this->normalizeRow($assoc, $mapping);
                $plate = strtoupper(trim((string) ($raw['plate'] ?? '')));
                if ($plate === '') {
                    throw new \InvalidArgumentException(__('Plate is required.'));
                }

                $vehicle = Vehicle::query()
                    ->where('tenant_id', $tenantId)
                    ->where('plate', $plate)
                    ->first();
                if ($vehicle === null) {
                    throw new \InvalidArgumentException(__('Unknown vehicle plate: :p', ['p' => $plate]));
                }

                $liters = $raw['liters'] ?? null;
                if ($liters === null || $liters === '' || ! is_numeric($liters)) {
                    throw new \InvalidArgumentException(__('Liters is required and must be numeric.'));
                }

                $recordedAt = $raw['recorded_at'] ?? null;
                if ($recordedAt === null || $recordedAt === '') {
                    throw new \InvalidArgumentException(__('Recorded at is required.'));
                }

                $odometer = $raw['odometer_km'] ?? null;
                $odometerVal = $odometer !== null && $odometer !== '' && is_numeric($odometer) ? $odometer : null;

                FuelIntake::query()->create([
                    'tenant_id' => $tenantId,
                    'vehicle_id' => $vehicle->id,
                    'liters' => $liters,
                    'odometer_km' => $odometerVal,
                    'recorded_at' => $recordedAt,
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

        return ['created' => $created, 'errors' => $errors];
    }

    /**
     * Yakıt fiyatı CSV/XLSX; başlıklar şablon ile uyumlu (`FuelPriceImportTemplateExport`).
     *
     * @return array<string, string>
     */
    public function getFuelPriceImportMapping(): array
    {
        return [
            // Eski satır bazlı şema
            'Yakıt Tipi' => 'fuel_type',
            'Fuel Type' => 'fuel_type',
            'Type' => 'fuel_type',
            'Fiyat' => 'price',
            'Price' => 'price',
            'Para Birimi' => 'currency',
            'Currency' => 'currency',
            'Kayıt Tarihi' => 'recorded_at',
            'Recorded at' => 'recorded_at',
            'Date' => 'recorded_at',
            'Kaynak' => 'source',
            'Source' => 'source',
            'Bölge' => 'region',
            'Region' => 'region',

            // Yeni arşiv şeması (tarih satırı + çoklu yakıt kolonları)
            'Tarih' => 'recorded_at',
            'Date' => 'recorded_at',
            'Excellium Kurşunsuz 95 TL/Lt' => 'gasoline_price',
            'Kurşunsuz 95' => 'gasoline_price',
            'Motorin TL/Lt' => 'diesel_price',
            'Motorin' => 'diesel_price',
            'Otogaz TL/Lt' => 'lpg_price',
            'Otogaz' => 'lpg_price',
            'İl' => 'province',
            'Il' => 'province',
            'Province' => 'province',
            'İlçe' => 'district',
            'Ilce' => 'district',
            'District' => 'district',
        ];
    }

    /**
     * @return array{created: int, errors: list<array{row: int, message: string}>}
     */
    public function importFuelPricesFromPath(string $path, int $tenantId): array
    {
        $mapping = $this->getFuelPriceImportMapping();
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
                $raw = $this->normalizeRow($assoc, $mapping);

                $recordedAt = $this->normalizeFuelImportDate($raw['recorded_at'] ?? null);
                if ($recordedAt === null) {
                    throw new \InvalidArgumentException(__('Recorded at is required.'));
                }

                $currency = strtoupper(trim((string) ($raw['currency'] ?? 'TRY')));
                if (! in_array($currency, ['TRY', 'EUR', 'USD'], true)) {
                    $currency = 'TRY';
                }

                $source = trim((string) ($raw['source'] ?? ''));
                $region = trim((string) ($raw['region'] ?? ''));
                $province = trim((string) ($raw['province'] ?? ''));
                $district = trim((string) ($raw['district'] ?? ''));
                if ($region === '' && $province !== '' && $district !== '') {
                    $region = $province.' / '.$district;
                }

                $dieselPrice = $this->normalizeFuelImportNumeric($raw['diesel_price'] ?? null);
                $gasolinePrice = $this->normalizeFuelImportNumeric($raw['gasoline_price'] ?? null);
                $lpgPrice = $this->normalizeFuelImportNumeric($raw['lpg_price'] ?? null);

                $isArchiveRow = array_key_exists('diesel_price', $raw)
                    || array_key_exists('gasoline_price', $raw)
                    || array_key_exists('lpg_price', $raw);

                if ($isArchiveRow) {
                    $fuelRows = [
                        'diesel' => $dieselPrice,
                        'gasoline' => $gasolinePrice,
                        'lpg' => $lpgPrice,
                    ];

                    $createdThisRow = 0;
                    foreach ($fuelRows as $fuelType => $price) {
                        if ($price === null) {
                            continue;
                        }

                        FuelPrice::query()->create([
                            'tenant_id' => $tenantId,
                            'fuel_type' => $fuelType,
                            'price' => $price,
                            'currency' => $currency,
                            'recorded_at' => $recordedAt,
                            'source' => $source !== '' ? $source : 'GüzelEnerji API',
                            'region' => $region !== '' ? $region : null,
                        ]);
                        $createdThisRow++;
                    }

                    if ($createdThisRow === 0) {
                        throw new \InvalidArgumentException(__('At least one fuel price column is required.'));
                    }

                    $created += $createdThisRow;

                    continue;
                }

                $fuelType = strtolower(trim((string) ($raw['fuel_type'] ?? '')));
                if (! in_array($fuelType, ['diesel', 'gasoline', 'lpg'], true)) {
                    throw new \InvalidArgumentException(__('Fuel type must be diesel, gasoline, or lpg.'));
                }

                $price = $this->normalizeFuelImportNumeric($raw['price'] ?? null);
                if ($price === null) {
                    throw new \InvalidArgumentException(__('Price is required and must be numeric.'));
                }

                FuelPrice::query()->create([
                    'tenant_id' => $tenantId,
                    'fuel_type' => $fuelType,
                    'price' => $price,
                    'currency' => $currency,
                    'recorded_at' => $recordedAt,
                    'source' => $source !== '' ? $source : null,
                    'region' => $region !== '' ? $region : null,
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

        return ['created' => $created, 'errors' => $errors];
    }

    private function uniqueOrderNumber(): string
    {
        do {
            $number = 'ON-'.now()->format('Ymd').'-'.strtoupper(Str::random(4));
        } while (Order::query()->where('order_number', $number)->exists());

        return $number;
    }

    private function normalizeFuelImportNumeric(mixed $value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }
        if (is_int($value) || is_float($value)) {
            return (float) $value;
        }
        if (! is_string($value)) {
            return null;
        }

        $text = trim($value);
        if ($text === '') {
            return null;
        }

        $text = str_replace([' ', "\xc2\xa0"], '', $text);
        $lastComma = strrpos($text, ',');
        $lastDot = strrpos($text, '.');
        if ($lastComma !== false && ($lastDot === false || $lastComma > $lastDot)) {
            $text = str_replace('.', '', $text);
            $text = str_replace(',', '.', $text);
        } else {
            $text = str_replace(',', '', $text);
        }

        return is_numeric($text) ? (float) $text : null;
    }

    private function normalizeFuelImportDate(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        if ($value instanceof \DateTimeInterface) {
            return $value->format('Y-m-d');
        }

        if (is_string($value)) {
            $text = trim($value);
            if ($text === '') {
                return null;
            }

            foreach (['d.m.Y', 'Y-m-d', 'd/m/Y'] as $format) {
                try {
                    return Carbon::createFromFormat($format, $text)->toDateString();
                } catch (\Throwable) {
                    // try next format
                }
            }

            try {
                return Carbon::parse($text)->toDateString();
            } catch (\Throwable) {
                return null;
            }
        }

        if (is_numeric($value)) {
            try {
                return Carbon::createFromTimestampMs((int) $value)->toDateString();
            } catch (\Throwable) {
                return null;
            }
        }

        return null;
    }
}
