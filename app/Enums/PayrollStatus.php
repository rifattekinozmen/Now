<?php

namespace App\Enums;

enum PayrollStatus: string
{
    case Draft    = 'draft';
    case Approved = 'approved';
    case Paid     = 'paid';

    public function label(): string
    {
        return match ($this) {
            self::Draft    => __('Draft'),
            self::Approved => __('Approved'),
            self::Paid     => __('Paid'),
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Draft    => 'zinc',
            self::Approved => 'blue',
            self::Paid     => 'green',
        };
    }

    public function isDraft(): bool { return $this === self::Draft; }
    public function isApproved(): bool { return $this === self::Approved; }
    public function isPaid(): bool { return $this === self::Paid; }
}
