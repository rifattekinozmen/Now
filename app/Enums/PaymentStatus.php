<?php

namespace App\Enums;

enum PaymentStatus: string
{
    case Pending = 'pending';
    case Completed = 'completed';
    case Cancelled = 'cancelled';
    case Returned = 'returned';

    public function label(): string
    {
        return match ($this) {
            self::Pending => __('Pending'),
            self::Completed => __('Completed'),
            self::Cancelled => __('Cancelled'),
            self::Returned => __('Returned'),
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Pending => 'yellow',
            self::Completed => 'green',
            self::Cancelled => 'red',
            self::Returned => 'orange',
        };
    }

    public function isPending(): bool
    {
        return $this === self::Pending;
    }

    public function isCompleted(): bool
    {
        return $this === self::Completed;
    }
}
