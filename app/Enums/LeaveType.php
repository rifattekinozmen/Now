<?php

namespace App\Enums;

enum LeaveType: string
{
    case Annual        = 'annual';
    case Sick          = 'sick';
    case Unpaid        = 'unpaid';
    case Compensatory  = 'compensatory';

    public function label(): string
    {
        return match ($this) {
            self::Annual       => __('Annual Leave'),
            self::Sick         => __('Sick Leave'),
            self::Unpaid       => __('Unpaid Leave'),
            self::Compensatory => __('Compensatory Leave'),
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Annual       => 'blue',
            self::Sick         => 'red',
            self::Unpaid       => 'zinc',
            self::Compensatory => 'purple',
        };
    }
}
