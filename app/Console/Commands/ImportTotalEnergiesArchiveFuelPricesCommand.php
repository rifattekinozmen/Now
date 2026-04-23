<?php

namespace App\Console\Commands;

use App\Models\Tenant;
use App\Services\Integrations\TotalEnergies\TotalEnergiesArchivePriceImportService;
use Illuminate\Console\Command;

class ImportTotalEnergiesArchiveFuelPricesCommand extends Command
{
    protected $signature = 'logistics:import-totalenergies-archive
        {--tenant-id= : Kayıtların yazılacağı tenant ID}
        {--province=Adana : İl adı}
        {--district=Merkez : İlçe adı}
        {--start-date= : Başlangıç tarihi (örn: 2026-04-01)}
        {--end-date= : Bitiş tarihi (örn: 2026-04-23)}
        {--dry-run : Veriyi parse eder, DB yazmaz}
        {--json : Sonucu tek satır JSON basar}';

    protected $description = 'TotalEnergies fiyat arşivi ekranından il/ilçe bazlı fiyatları çekip fuel_prices tablosuna yazar.';

    public function handle(): int
    {
        $tenantId = $this->option('tenant-id');
        if (! is_numeric($tenantId)) {
            $this->error('--tenant-id zorunludur ve sayısal olmalıdır.');

            return self::FAILURE;
        }

        if (! Tenant::query()->withoutGlobalScopes()->whereKey((int) $tenantId)->exists()) {
            $this->error('Belirtilen tenant bulunamadı.');

            return self::FAILURE;
        }

        $service = TotalEnergiesArchivePriceImportService::fromConfig();
        $result = $service->importArchivePrices(
            tenantId: (int) $tenantId,
            province: (string) $this->option('province'),
            district: (string) $this->option('district'),
            startDate: $this->option('start-date') !== null ? (string) $this->option('start-date') : null,
            endDate: $this->option('end-date') !== null ? (string) $this->option('end-date') : null,
            dryRun: (bool) $this->option('dry-run'),
        );

        if ((bool) $this->option('json')) {
            $this->line(json_encode($result, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE));

            return ($result['ok'] ?? false) ? self::SUCCESS : self::FAILURE;
        }

        $this->line('ok: '.(($result['ok'] ?? false) ? 'yes' : 'no'));
        $this->line('message: '.($result['message'] ?? ''));
        $this->line('rows: '.($result['rows'] ?? 0));
        $this->line('imported: '.($result['imported'] ?? 0));
        $this->line('updated: '.($result['updated'] ?? 0));
        $this->line('skipped: '.($result['skipped'] ?? 0));

        return ($result['ok'] ?? false) ? self::SUCCESS : self::FAILURE;
    }
}
