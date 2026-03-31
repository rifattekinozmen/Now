<?php

namespace App\Enums;

enum MaintenanceType: string
{
    case Periodic   = 'periodic';
    case Inspection = 'inspection';
    case Repair     = 'repair';
    case Tire       = 'tire';

    public function label(): string
    {
        return match ($this) {
            self::Periodic   => __('Periodic'),
            self::Inspection => __('Inspection'),
            self::Repair     => __('Repair'),
            self::Tire       => __('Tire'),
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Periodic   => 'blue',
            self::Inspection => 'purple',
            self::Repair     => 'red',
            self::Tire       => 'zinc',
        };
    }
}
