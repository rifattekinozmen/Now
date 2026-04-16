<?php

namespace App\Enums;

enum BankTransactionType: string
{
    case Credit = 'credit'; // incoming / alacak
    case Debit = 'debit';  // outgoing / borç

    public function label(): string
    {
        return match ($this) {
            self::Credit => __('Credit'),
            self::Debit => __('Debit'),
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Credit => 'green',
            self::Debit => 'red',
        };
    }
}
