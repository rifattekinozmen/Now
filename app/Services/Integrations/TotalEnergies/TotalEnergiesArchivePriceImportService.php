<?php

namespace App\Services\Integrations\TotalEnergies;

use App\Models\AppNotification;
use App\Models\FuelPrice;
use App\Models\User;
use GuzzleHttp\Cookie\CookieJar;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * TotalEnergies web paneli (giriş + fiyat arşivi) üzerinden fiyat içe aktarımı.
 */
final class TotalEnergiesArchivePriceImportService
{
    public function __construct(
        private readonly bool $enabled,
        private readonly string $loginUrl,
        private readonly string $archiveUrl,
        private readonly string $archiveApiBaseUrl,
        private readonly string $username,
        private readonly string $password,
        private readonly string $usernameField,
        private readonly string $passwordField,
        private readonly string $csrfField,
        private readonly string $defaultCurrency,
        private readonly array $tableColumnMap,
        private readonly array $baseArchiveQuery,
    ) {}

    public static function fromConfig(): self
    {
        $columnMap = config('totalenergies.archive_column_map', []);
        $archiveQuery = config('totalenergies.archive_query', []);

        return new self(
            (bool) config('totalenergies.enabled', false),
            (string) config('totalenergies.archive_login_url', ''),
            (string) config('totalenergies.archive_url', ''),
            (string) config('totalenergies.archive_api_base_url', 'https://apimobile.guzelenerji.com.tr'),
            (string) config('totalenergies.archive_username', ''),
            (string) config('totalenergies.archive_password', ''),
            (string) config('totalenergies.archive_username_field', 'Username'),
            (string) config('totalenergies.archive_password_field', 'Password'),
            (string) config('totalenergies.archive_csrf_field', '__RequestVerificationToken'),
            strtoupper((string) config('totalenergies.default_currency', 'TRY')),
            is_array($columnMap) ? $columnMap : [],
            is_array($archiveQuery) ? $archiveQuery : [],
        );
    }

    /**
     * @return array{
     *   ok: bool,
     *   message: string,
     *   imported: int,
     *   updated: int,
     *   skipped: int,
     *   rows: int
     * }
     */
    public function importArchivePrices(
        int $tenantId,
        string $province,
        string $district,
        ?string $startDate = null,
        ?string $endDate = null,
        bool $dryRun = false,
    ): array {
        if (! $this->enabled) {
            return $this->fail('TOTALENERGIES_ENABLED=false olduğu için entegrasyon devre dışı.');
        }

        if ($this->loginUrl === '' || $this->archiveUrl === '') {
            return $this->fail('TOTALENERGIES_ARCHIVE_LOGIN_URL ve TOTALENERGIES_ARCHIVE_URL tanımlanmalı.');
        }

        $cookieJar = new CookieJar;
        $client = $this->buildHttpClient($cookieJar);

        try {
            $rows = $this->fetchRowsFromApi($client, $province, $district, $startDate, $endDate);

            if ($rows === null) {
                $query = $this->buildArchiveQuery($province, $district, $startDate, $endDate);
                $archiveResponse = $client->get($this->archiveUrl, $query);
                if (! $archiveResponse->successful()) {
                    return $this->fail('Fiyat arşivi alınamadı: '.$archiveResponse->status());
                }

                $parser = new TotalEnergiesArchiveHtmlParser;
                $rows = $parser->parse($archiveResponse->body(), $this->tableColumnMap);
            }

            if ($rows === [] && $this->username !== '' && $this->password !== '') {
                $query = $this->buildArchiveQuery($province, $district, $startDate, $endDate);
                $loginPage = $client->get($this->loginUrl);
                if (! $loginPage->successful()) {
                    return $this->fail('Giriş sayfası açılamadı: '.$loginPage->status());
                }

                $loginPayload = $this->buildLoginPayload($loginPage->body());
                $loginResponse = $client->asForm()->post($this->loginUrl, $loginPayload);
                if (! $loginResponse->successful() && ! $loginResponse->redirect()) {
                    return $this->fail('Giriş isteği başarısız: '.$loginResponse->status());
                }

                $archiveResponse = $client->get($this->archiveUrl, $query);
                if (! $archiveResponse->successful()) {
                    return $this->fail('Fiyat arşivi alınamadı: '.$archiveResponse->status());
                }

                $parser = new TotalEnergiesArchiveHtmlParser;
                $rows = $parser->parse($archiveResponse->body(), $this->tableColumnMap);
            }

            if ($rows === []) {
                return $this->fail('Fiyat arşivi tablosundan satır okunamadı. Sayfa public ise query alan adlarını, login gerekiyorsa kullanıcı/şifre ve form alan adlarını kontrol edin.');
            }

            $imported = 0;
            $updated = 0;
            $skipped = 0;
            $source = 'TotalEnergies Archive';
            $region = trim($province.' / '.$district);
            /** @var list<array{date: string, previous: float, current: float, change_pct: float}> $dieselAlerts */
            $dieselAlerts = [];

            foreach ($rows as $row) {
                foreach ($row['prices'] as $fuelType => $price) {
                    if (! is_string($fuelType) || ! is_float($price)) {
                        $skipped++;

                        continue;
                    }

                    if ($dryRun) {
                        $imported++;

                        continue;
                    }

                    $previousDieselPrice = null;
                    if ($fuelType === 'diesel') {
                        $previousDieselPrice = FuelPrice::query()
                            ->withoutGlobalScopes()
                            ->where('tenant_id', $tenantId)
                            ->where('fuel_type', 'diesel')
                            ->where('source', $source)
                            ->where('region', $region)
                            ->whereDate('recorded_at', '<', $row['recorded_at'])
                            ->orderByDesc('recorded_at')
                            ->value('price');
                    }

                    $model = FuelPrice::query()->withoutGlobalScopes()->firstOrNew([
                        'tenant_id' => $tenantId,
                        'fuel_type' => $fuelType,
                        'recorded_at' => $row['recorded_at'],
                        'source' => $source,
                        'region' => $region,
                    ]);

                    $alreadyExists = $model->exists;
                    $model->fill([
                        'price' => $price,
                        'currency' => $this->defaultCurrency,
                    ]);
                    $model->save();

                    if ($alreadyExists) {
                        $updated++;
                    } else {
                        $imported++;

                        if ($fuelType === 'diesel' && $previousDieselPrice !== null && (float) $previousDieselPrice > 0) {
                            $changePct = (((float) $price - (float) $previousDieselPrice) / (float) $previousDieselPrice) * 100;
                            if (abs($changePct) >= 5.0) {
                                $dieselAlerts[] = [
                                    'date' => (string) $row['recorded_at'],
                                    'previous' => (float) $previousDieselPrice,
                                    'current' => (float) $price,
                                    'change_pct' => (float) $changePct,
                                ];
                            }
                        }
                    }
                }
            }

            if (! $dryRun) {
                $this->notifyPriceEvents($tenantId, $imported, $dieselAlerts, $region);
            }

            return [
                'ok' => true,
                'message' => 'Fiyat arşivi başarıyla işlendi.',
                'imported' => $imported,
                'updated' => $updated,
                'skipped' => $skipped,
                'rows' => count($rows),
            ];
        } catch (\Throwable $e) {
            return $this->fail($e->getMessage());
        }
    }

    private function buildHttpClient(CookieJar $jar): PendingRequest
    {
        $timeout = (int) config('totalenergies.timeout_seconds', 15);
        $retry = config('totalenergies.retry');
        $times = is_array($retry) && isset($retry['times']) ? (int) $retry['times'] : 2;
        $sleepMs = is_array($retry) && isset($retry['sleep_ms']) ? (int) $retry['sleep_ms'] : 100;

        return Http::timeout($timeout)
            ->retry(max(1, $times), max(0, $sleepMs))
            ->withOptions(['cookies' => $jar])
            ->withHeaders([
                'Accept' => 'application/json, text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
            ]);
    }

    /**
     * @return list<array{recorded_at: string, prices: array{diesel?: float, gasoline?: float, lpg?: float}>|null
     */
    private function fetchRowsFromApi(
        PendingRequest $client,
        string $province,
        string $district,
        ?string $startDate,
        ?string $endDate,
    ): ?array {
        $base = rtrim($this->archiveApiBaseUrl, '/');
        if ($base === '') {
            return null;
        }

        $cities = $client->get($base.'/exapi/fuel_price_cities');
        if (! $cities->successful()) {
            return null;
        }
        $city = $this->matchByName($cities, $province, ['city_name', 'city_excel_name']);
        if ($city === null || ! isset($city['city_id'])) {
            return null;
        }

        $counties = $client->get($base.'/exapi/fuel_price_counties/'.$city['city_id'], ['is_active' => 'true']);
        if (! $counties->successful()) {
            return null;
        }
        $county = $this->matchByName($counties, $district, ['county_name', 'county_excel_name']);
        if ($county === null || ! isset($county['county_id'])) {
            return null;
        }

        $dateStart = is_string($startDate) && trim($startDate) !== '' ? trim($startDate) : now()->subDays(30)->toDateString();
        $dateEnd = is_string($endDate) && trim($endDate) !== '' ? trim($endDate) : now()->toDateString();

        $prices = $client->asJson()->post($base.'/exapi/fuel_prices_by_date', [
            'county_id' => (int) $county['county_id'],
            'start_date' => $dateStart.'T00:00:00Z',
            'end_date' => $dateEnd.'T23:59:59Z',
        ]);
        if (! $prices->successful()) {
            return null;
        }

        $payload = $prices->json();
        if (! is_array($payload)) {
            return null;
        }

        $rows = [];
        foreach ($payload as $item) {
            if (! is_array($item) || ! is_string($item['pricedate'] ?? null)) {
                continue;
            }

            $rowPrices = [];
            $diesel = $this->normalizeFloatValue($item['motorin'] ?? null);
            $gasoline = $this->normalizeFloatValue($item['kursunsuz_95_excellium_95'] ?? null);
            $lpg = $this->normalizeFloatValue($item['otogaz'] ?? null);

            if ($diesel !== null) {
                $rowPrices['diesel'] = $diesel;
            }
            if ($gasoline !== null) {
                $rowPrices['gasoline'] = $gasoline;
            }
            if ($lpg !== null) {
                $rowPrices['lpg'] = $lpg;
            }

            if ($rowPrices === []) {
                continue;
            }

            $rows[] = [
                'recorded_at' => $item['pricedate'],
                'prices' => $rowPrices,
            ];
        }

        return $rows;
    }

    /**
     * @param  list<string>  $nameFields
     * @return array<string, mixed>|null
     */
    private function matchByName(Response $response, string $needle, array $nameFields): ?array
    {
        $items = $response->json();
        if (! is_array($items)) {
            return null;
        }

        $normalizedNeedle = $this->normalizeName($needle);
        foreach ($items as $item) {
            if (! is_array($item)) {
                continue;
            }

            foreach ($nameFields as $field) {
                $value = $item[$field] ?? null;
                if (is_string($value) && $this->normalizeName($value) === $normalizedNeedle) {
                    return $item;
                }
            }
        }

        return null;
    }

    private function normalizeName(string $value): string
    {
        return Str::upper(Str::ascii(trim($value)));
    }

    private function normalizeFloatValue(mixed $value): ?float
    {
        if (is_int($value) || is_float($value)) {
            return (float) $value;
        }
        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        $normalized = str_replace(',', '.', str_replace(' ', '', trim($value)));

        return is_numeric($normalized) ? (float) $normalized : null;
    }

    /**
     * @param  list<array{date: string, previous: float, current: float, change_pct: float}>  $dieselAlerts
     */
    private function notifyPriceEvents(int $tenantId, int $importedCount, array $dieselAlerts, string $region): void
    {
        if ($importedCount <= 0 && $dieselAlerts === []) {
            return;
        }

        $users = User::query()
            ->withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->get();

        foreach ($users as $user) {
            if ($importedCount > 0) {
                AppNotification::query()->create([
                    'tenant_id' => $tenantId,
                    'user_id' => $user->id,
                    'type' => 'fuel_price_new',
                    'title' => __('Yeni yakıt fiyatı kaydı eklendi'),
                    'body' => __(':count yeni fiyat satırı eklendi (:region).', [
                        'count' => $importedCount,
                        'region' => $region,
                    ]),
                    'is_read' => false,
                    'data' => [
                        'imported_count' => $importedCount,
                        'region' => $region,
                    ],
                    'url' => route('admin.fuel-prices.index'),
                ]);
            }

            foreach ($dieselAlerts as $alert) {
                AppNotification::query()->create([
                    'tenant_id' => $tenantId,
                    'user_id' => $user->id,
                    'type' => 'fuel_price_change',
                    'title' => __('Motorin fiyatında %5+ değişim'),
                    'body' => __(':date tarihinde motorin :prev TL/Lt -> :cur TL/Lt (:pct%).', [
                        'date' => $alert['date'],
                        'prev' => number_format($alert['previous'], 2),
                        'cur' => number_format($alert['current'], 2),
                        'pct' => ($alert['change_pct'] > 0 ? '+' : '').number_format($alert['change_pct'], 2),
                    ]),
                    'is_read' => false,
                    'data' => [
                        'region' => $region,
                        'recorded_at' => $alert['date'],
                        'previous' => $alert['previous'],
                        'current' => $alert['current'],
                        'change_pct' => $alert['change_pct'],
                    ],
                    'url' => route('admin.fuel-prices.index'),
                ]);
            }
        }
    }

    /**
     * @return array<string, string>
     */
    private function buildLoginPayload(string $html): array
    {
        $payload = [
            $this->usernameField => $this->username,
            $this->passwordField => $this->password,
        ];

        $token = $this->extractHiddenInputValue($html, $this->csrfField);
        if ($token !== null) {
            $payload[$this->csrfField] = $token;
        }

        $extra = config('totalenergies.archive_login_payload');
        if (is_array($extra)) {
            foreach ($extra as $key => $value) {
                if (is_string($key) && is_scalar($value)) {
                    $payload[$key] = (string) $value;
                }
            }
        }

        return $payload;
    }

    /**
     * @return array<string, scalar>
     */
    private function buildArchiveQuery(
        string $province,
        string $district,
        ?string $startDate,
        ?string $endDate,
    ): array {
        $query = [];
        foreach ($this->baseArchiveQuery as $key => $value) {
            if (is_string($key) && is_scalar($value)) {
                $query[$key] = $value;
            }
        }

        $query['province'] = $province;
        $query['district'] = $district;

        if (is_string($startDate) && trim($startDate) !== '') {
            $query['start_date'] = trim($startDate);
        }
        if (is_string($endDate) && trim($endDate) !== '') {
            $query['end_date'] = trim($endDate);
        }

        return $query;
    }

    private function extractHiddenInputValue(string $html, string $fieldName): ?string
    {
        if ($fieldName === '' || $html === '') {
            return null;
        }

        $pattern = '/<input[^>]*name=["\']'.preg_quote($fieldName, '/').'["\'][^>]*value=["\']([^"\']+)["\']/i';
        if (preg_match($pattern, $html, $matches) !== 1) {
            return null;
        }

        return html_entity_decode($matches[1], ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }

    /**
     * @return array{ok: bool, message: string, imported: int, updated: int, skipped: int, rows: int}
     */
    private function fail(string $message): array
    {
        return [
            'ok' => false,
            'message' => $message,
            'imported' => 0,
            'updated' => 0,
            'skipped' => 0,
            'rows' => 0,
        ];
    }
}
