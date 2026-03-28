<?php

namespace App\Enums;

enum ShipmentStatus: string
{
    case Planned = 'planned';
    case Dispatched = 'dispatched';
    case Delivered = 'delivered';
    case Cancelled = 'cancelled';
}
