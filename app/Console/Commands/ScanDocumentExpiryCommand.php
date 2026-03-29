<?php

namespace App\Console\Commands;

use App\Services\Logistics\DocumentExpiryScanService;
use Illuminate\Console\Command;

class ScanDocumentExpiryCommand extends Command
{
    protected $signature = 'logistics:scan-document-expiry';

    protected $description = 'Muayene ve şoför belgeleri için 30/15/7/1 gün kala operasyonel uyarı gönderir';

    public function handle(DocumentExpiryScanService $scanner): int
    {
        $count = $scanner->scan();
        $this->info("Gönderilen bildirim: {$count}");

        return self::SUCCESS;
    }
}
