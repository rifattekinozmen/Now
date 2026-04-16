<?php

namespace App\Enums;

enum DocumentCategory: string
{
    case Contract = 'contract';
    case License = 'license';
    case Insurance = 'insurance';
    case Permit = 'permit';
    case Invoice = 'invoice';
    case Certificate = 'certificate';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Contract => __('Contract'),
            self::License => __('License'),
            self::Insurance => __('Insurance'),
            self::Permit => __('Permit'),
            self::Invoice => __('Invoice'),
            self::Certificate => __('Certificate'),
            self::Other => __('Other'),
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Contract => 'indigo',
            self::License => 'blue',
            self::Insurance => 'green',
            self::Permit => 'yellow',
            self::Invoice => 'orange',
            self::Certificate => 'purple',
            self::Other => 'zinc',
        };
    }
}
