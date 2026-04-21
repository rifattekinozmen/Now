<?php

namespace App\Support;

/**
 * Teslimat Excel içe aktarma için PHP ortamı (.xlsx / .xlsm → ZipArchive + zip eklentisi).
 */
final class DeliveryImportPhp
{
    public static function isZipAvailableForXlsx(): bool
    {
        return extension_loaded('zip') && class_exists(\ZipArchive::class);
    }
}
