<?php

namespace App\Enums;

enum ShiftStatus: string
{
    case Planned = 'planned';
    case Confirmed = 'confirmed';
    case Absent = 'absent';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Planned => __('Planned'),
            self::Confirmed => __('Confirmed'),
            self::Absent => __('Absent'),
            self::Cancelled => __('Cancelled'),
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Planned => 'zinc',
            self::Confirmed => 'green',
            self::Absent => 'red',
            self::Cancelled => 'orange',
        };
    }

    public function isPlanned(): bool
    {
        return $this === self::Planned;
    }
}
