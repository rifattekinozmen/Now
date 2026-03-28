<?php

namespace App\Enums;

enum DeliveryNumberStatus: string
{
    case Available = 'available';
    case Assigned = 'assigned';
    case Used = 'used';
}
