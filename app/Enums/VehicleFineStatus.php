<?php

namespace App\Enums;

enum VehicleFineStatus: string
{
    case Pending = 'pending';
    case Paid = 'paid';
    case Appealed = 'appealed';

    public function label(): string
    {
        return match ($this) {
            self::Pending => __('Pending'),
            self::Paid => __('Paid'),
            self::Appealed => __('Appealed'),
        };
    }
}
