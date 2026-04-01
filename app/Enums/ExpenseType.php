<?php

namespace App\Enums;

enum ExpenseType: string
{
    case Fuel = 'fuel';
    case Toll = 'toll';
    case Highway = 'highway';
    case Parking = 'parking';
    case Repair = 'repair';
    case Wash = 'wash';
    case Fine = 'fine';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Fuel => __('Fuel'),
            self::Toll => __('Toll'),
            self::Highway => __('Highway'),
            self::Parking => __('Parking'),
            self::Repair => __('Repair'),
            self::Wash => __('Wash'),
            self::Fine => __('Fine / Penalty'),
            self::Other => __('Other'),
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Fuel => 'amber',
            self::Toll => 'blue',
            self::Highway => 'sky',
            self::Parking => 'zinc',
            self::Repair => 'red',
            self::Wash => 'cyan',
            self::Fine => 'rose',
            self::Other => 'zinc',
        };
    }
}
