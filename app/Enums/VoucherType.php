<?php

namespace App\Enums;

enum VoucherType: string
{
    case Expense  = 'expense';
    case Income   = 'income';
    case Transfer = 'transfer';

    public function label(): string
    {
        return match ($this) {
            self::Expense  => __('Expense'),
            self::Income   => __('Income'),
            self::Transfer => __('Transfer'),
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Expense  => 'red',
            self::Income   => 'green',
            self::Transfer => 'blue',
        };
    }
}
