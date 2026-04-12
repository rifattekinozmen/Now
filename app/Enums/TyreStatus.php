<?php

namespace App\Enums;

enum TyreStatus: string
{
    case Active = 'active';
    case Worn = 'worn';
    case Damaged = 'damaged';
    case Removed = 'removed';

    public function label(): string
    {
        return match ($this) {
            self::Active => __('Active'),
            self::Worn => __('Worn'),
            self::Damaged => __('Damaged'),
            self::Removed => __('Removed'),
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Active => 'green',
            self::Worn => 'yellow',
            self::Damaged => 'red',
            self::Removed => 'zinc',
        };
    }

    public function isActive(): bool
    {
        return $this === self::Active;
    }
}
