<?php

namespace App\Enums;

enum OrderStatus: string
{
    case Draft = 'draft';
    case Confirmed = 'confirmed';
    case InTransit = 'in_transit';
    case Delivered = 'delivered';
    case Cancelled = 'cancelled';
}
