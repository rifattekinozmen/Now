<?php

namespace App\Enums;

enum BusinessPartnerType: string
{
    case Carrier = 'carrier';
    case Supplier = 'supplier';
    case Broker = 'broker';
    case CustomsAgent = 'customs_agent';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Carrier => __('Carrier'),
            self::Supplier => __('Supplier'),
            self::Broker => __('Broker'),
            self::CustomsAgent => __('Customs Agent'),
            self::Other => __('Other'),
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Carrier => 'blue',
            self::Supplier => 'green',
            self::Broker => 'purple',
            self::CustomsAgent => 'orange',
            self::Other => 'zinc',
        };
    }
}
