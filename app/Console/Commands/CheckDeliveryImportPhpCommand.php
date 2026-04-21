<?php

namespace App\Console\Commands;

use App\Support\DeliveryImportPhp;
use Illuminate\Console\Command;

class CheckDeliveryImportPhpCommand extends Command
{
    protected $signature = 'delivery:check-php';

    protected $description = 'Teslimat Excel importu için PHP zip (ZipArchive) ve yüklü php.ini bilgisini gösterir';

    public function handle(): int
    {
        $this->components->info('Teslimat import — PHP ortamı');
        $this->line('PHP sürümü: '.PHP_VERSION);
        $this->line('PHP binary: '.PHP_BINARY);
        $ini = php_ini_loaded_file();
        $this->line('Yüklü php.ini: '.($ini !== false ? $ini : '(yok)'));
        $this->line('extension_loaded(zip): '.(extension_loaded('zip') ? 'evet' : 'HAYIR'));
        $this->line('class_exists(ZipArchive): '.(class_exists(\ZipArchive::class) ? 'evet' : 'HAYIR'));
        $this->line('isZipAvailableForXlsx (uygulama): '.(DeliveryImportPhp::isZipAvailableForXlsx() ? 'evet' : 'HAYIR'));

        if (! DeliveryImportPhp::isZipAvailableForXlsx()) {
            $this->newLine();
            $this->components->warn('Bu PHP ile .xlsx okunamaz. Laragon: PHP → php.ini → extension=zip açın; Apache’yi yeniden başlatın.');
            $this->line('Web tarayıcısı farklı bir PHP kullanıyorsa, o sürümün php.ini dosyasında da zip açık olmalı.');

            return self::FAILURE;
        }

        $this->newLine();
        $this->components->info('zip eklentisi tamam.');

        return self::SUCCESS;
    }
}
