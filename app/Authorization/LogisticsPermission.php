<?php

namespace App\Authorization;

use App\Models\User;

/**
 * Lojistik admin alanı ve ilgili gate / middleware için izin adı.
 */
final class LogisticsPermission
{
    public const ADMIN = 'logistics.admin';

    /** Salt okunur panel (liste/rapor; yazma ve toplu işlemler `ADMIN` gerektirir.) */
    public const VIEW = 'logistics.view';

    /** Modül bazlı yazma; `ADMIN` tüm modülleri kapsar. */
    public const CUSTOMERS_WRITE = 'logistics.customers.write';

    public const ORDERS_WRITE = 'logistics.orders.write';

    public const SHIPMENTS_WRITE = 'logistics.shipments.write';

    public const VEHICLES_WRITE = 'logistics.vehicles.write';

    public const PINS_WRITE = 'logistics.pins.write';

    public const EMPLOYEES_WRITE = 'logistics.employees.write';

    public static function canWrite(User $user, string $writePermission): bool
    {
        return $user->can(self::ADMIN) || $user->can($writePermission);
    }
}
