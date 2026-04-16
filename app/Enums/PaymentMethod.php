<?php

namespace App\Enums;

enum PaymentMethod: string
{
    case Cash = 'cash';
    case BankTransfer = 'bank_transfer';
    case Cheque = 'cheque';
    case PromissoryNote = 'promissory_note';
    case CreditCard = 'credit_card';

    public function label(): string
    {
        return match ($this) {
            self::Cash => __('Cash'),
            self::BankTransfer => __('Bank Transfer'),
            self::Cheque => __('Cheque'),
            self::PromissoryNote => __('Promissory Note'),
            self::CreditCard => __('Credit Card'),
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Cash => 'green',
            self::BankTransfer => 'blue',
            self::Cheque => 'yellow',
            self::PromissoryNote => 'orange',
            self::CreditCard => 'purple',
        };
    }
}
