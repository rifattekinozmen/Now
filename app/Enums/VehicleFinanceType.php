<?php

namespace App\Enums;

enum VehicleFinanceType: string
{
    case Insurance = 'insurance';
    case Registration = 'registration';
    case LoanPayment = 'loan_payment';
    case Repair = 'repair';
    case Maintenance = 'maintenance';
    case RoadTax = 'road_tax';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Insurance => __('Insurance'),
            self::Registration => __('Registration'),
            self::LoanPayment => __('Loan Payment'),
            self::Repair => __('Repair'),
            self::Maintenance => __('Maintenance'),
            self::RoadTax => __('Road Tax'),
            self::Other => __('Other'),
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Insurance => 'blue',
            self::Registration => 'yellow',
            self::LoanPayment => 'red',
            self::Repair => 'orange',
            self::Maintenance => 'purple',
            self::RoadTax => 'green',
            self::Other => 'zinc',
        };
    }
}
