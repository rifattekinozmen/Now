<?php

namespace App\Enums;

enum ShiftType: string
{
    case Regular = 'regular';
    case Morning = 'morning';
    case Evening = 'evening';
    case Night = 'night';
    case Weekend = 'weekend';
    case OnCall = 'on_call';

    public function label(): string
    {
        return match ($this) {
            self::Regular => __('Regular'),
            self::Morning => __('Morning'),
            self::Evening => __('Evening'),
            self::Night => __('Night'),
            self::Weekend => __('Weekend'),
            self::OnCall => __('On Call'),
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Regular => 'zinc',
            self::Morning => 'yellow',
            self::Evening => 'orange',
            self::Night => 'indigo',
            self::Weekend => 'purple',
            self::OnCall => 'red',
        };
    }
}
