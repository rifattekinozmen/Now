<?php

namespace App\Enums;

enum MaintenanceStatus: string
{
    case Scheduled  = 'scheduled';
    case InProgress = 'in_progress';
    case Done       = 'done';
    case Cancelled  = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Scheduled  => __('Scheduled'),
            self::InProgress => __('In Progress'),
            self::Done       => __('Done'),
            self::Cancelled  => __('Cancelled'),
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Scheduled  => 'blue',
            self::InProgress => 'yellow',
            self::Done       => 'green',
            self::Cancelled  => 'zinc',
        };
    }

    public function isScheduled(): bool { return $this === self::Scheduled; }
    public function isDone(): bool { return $this === self::Done; }
}
