<?php

namespace App\Enums;

enum DeliveryImportStatus: string
{
    case Pending = 'pending';
    case Processed = 'processed';
    case Error = 'error';

    public function label(): string
    {
        return match ($this) {
            self::Pending => __('Pending'),
            self::Processed => __('Processed'),
            self::Error => __('Error'),
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Pending => 'yellow',
            self::Processed => 'green',
            self::Error => 'red',
        };
    }
}
