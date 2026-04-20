<?php

namespace App\Enums;

enum VehicleFineType: string
{
    case Speeding = 'speeding';
    case Overload = 'overload';
    case Document = 'document';
    case Parking = 'parking';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Speeding => __('Speeding'),
            self::Overload => __('Overload'),
            self::Document => __('Document / License'),
            self::Parking => __('Parking'),
            self::Other => __('Other'),
        };
    }
}
