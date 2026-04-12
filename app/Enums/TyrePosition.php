<?php

namespace App\Enums;

enum TyrePosition: string
{
    case FrontLeft = 'front_left';
    case FrontRight = 'front_right';
    case RearLeft = 'rear_left';
    case RearRight = 'rear_right';
    case RearLeftInner = 'rear_left_inner';
    case RearRightInner = 'rear_right_inner';
    case Spare = 'spare';

    public function label(): string
    {
        return match ($this) {
            self::FrontLeft => __('Front Left'),
            self::FrontRight => __('Front Right'),
            self::RearLeft => __('Rear Left'),
            self::RearRight => __('Rear Right'),
            self::RearLeftInner => __('Rear Left Inner'),
            self::RearRightInner => __('Rear Right Inner'),
            self::Spare => __('Spare'),
        };
    }
}
