<?php

namespace App\Enums;

enum OrderStatus: string
{
    case Draft = 'draft';
    case PendingPriceApproval = 'pending_price_approval';
    case Confirmed = 'confirmed';
    case InTransit = 'in_transit';
    case Delivered = 'delivered';
    case Cancelled = 'cancelled';

    public function isPendingPriceApproval(): bool
    {
        return $this === self::PendingPriceApproval;
    }
}
