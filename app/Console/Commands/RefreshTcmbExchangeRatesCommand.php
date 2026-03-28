<?php

namespace App\Console\Commands;

use App\Services\Logistics\TcmbExchangeRateService;
use Illuminate\Console\Command;

class RefreshTcmbExchangeRatesCommand extends Command
{
    protected $signature = 'logistics:refresh-tcmb-rates';

    protected $description = 'Fetch TCMB daily FX (today.xml) into application cache (operational reference only).';

    public function handle(TcmbExchangeRateService $tcmb): int
    {
        if ($tcmb->tryRefreshFromRemote()) {
            $this->info('TCMB rates cached.');

            return self::SUCCESS;
        }

        $this->warn('TCMB fetch failed; cache unchanged.');

        return self::FAILURE;
    }
}
