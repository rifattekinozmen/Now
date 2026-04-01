<?php

namespace App\Enums;

enum TransactionType: string
{
    case Debit = 'debit';
    case Credit = 'credit';
    case Payment = 'payment';
    case Return = 'return';
    case Advance = 'advance';

    public function label(): string
    {
        return match ($this) {
            self::Debit => __('Debit'),
            self::Credit => __('Credit'),
            self::Payment => __('Payment'),
            self::Return => __('Return'),
            self::Advance => __('Advance'),
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Debit => 'red',
            self::Credit => 'green',
            self::Payment => 'blue',
            self::Return => 'amber',
            self::Advance => 'purple',
        };
    }

    /** Bakiyeyi azaltır mı? */
    public function isDecrease(): bool
    {
        return match ($this) {
            self::Credit, self::Payment, self::Return => true,
            default => false,
        };
    }
}
