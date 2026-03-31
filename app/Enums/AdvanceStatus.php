<?php

namespace App\Enums;

enum AdvanceStatus: string
{
    case Pending  = 'pending';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case Repaid   = 'repaid';

    public function label(): string
    {
        return match ($this) {
            self::Pending  => __('Pending'),
            self::Approved => __('Approved'),
            self::Rejected => __('Rejected'),
            self::Repaid   => __('Repaid'),
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Pending  => 'yellow',
            self::Approved => 'green',
            self::Rejected => 'red',
            self::Repaid   => 'blue',
        };
    }

    public function isPending(): bool { return $this === self::Pending; }
    public function isApproved(): bool { return $this === self::Approved; }
}
