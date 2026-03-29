<?php

namespace App\Console\Commands;

use App\Services\Integrations\TotalEnergies\TotalEnergiesFuelQuoteService;
use Illuminate\Console\Command;

class TestTotalEnergiesFuelQuoteCommand extends Command
{
    protected $signature = 'logistics:test-totalenergies {--json : Çıktıyı tek satır JSON olarak yazdır}';

    protected $description = 'Staging/smoke: TotalEnergies yapılandırması ile bir motorin teklifi çeker (gerçek API çağrısı).';

    public function handle(): int
    {
        $result = TotalEnergiesFuelQuoteService::fromConfig()->fetchSampleDieselQuote();

        if ($this->option('json')) {
            $this->line(json_encode($result, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE));

            return $result['ok'] === true ? self::SUCCESS : self::FAILURE;
        }

        $this->line('schema_version: '.($result['schema_version'] ?? '?'));
        $this->line('ok: '.($result['ok'] ? 'yes' : 'no'));
        $this->line('message: '.($result['message'] ?? ''));
        if (($result['ok'] ?? false) === true) {
            $this->line('price (unit): '.($result['price_eur_per_liter'] ?? 'null'));
            $this->line('currency: '.($result['currency'] ?? 'null'));
            $this->line('location: '.($result['location_label'] ?? 'null'));
        }

        return $result['ok'] === true ? self::SUCCESS : self::FAILURE;
    }
}
