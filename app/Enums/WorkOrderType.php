<?php

namespace App\Enums;

enum WorkOrderType: string
{
    case Preventive = 'preventive';
    case Corrective = 'corrective';
    case Inspection = 'inspection';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Preventive => __('Preventive'),
            self::Corrective => __('Corrective'),
            self::Inspection => __('Inspection'),
            self::Other => __('Other'),
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Preventive => 'blue',
            self::Corrective => 'red',
            self::Inspection => 'yellow',
            self::Other => 'zinc',
        };
    }
}
