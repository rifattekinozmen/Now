<?php

namespace App\Enums;

enum AccountType: string
{
    case Customer = 'customer';
    case Employee = 'employee';
    case Vehicle = 'vehicle';
    case Supplier = 'supplier';

    public function label(): string
    {
        return match ($this) {
            self::Customer => __('Customer'),
            self::Employee => __('Employee'),
            self::Vehicle => __('Vehicle'),
            self::Supplier => __('Supplier'),
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Customer => 'blue',
            self::Employee => 'purple',
            self::Vehicle => 'orange',
            self::Supplier => 'zinc',
        };
    }
}
