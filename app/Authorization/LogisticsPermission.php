<?php

namespace App\Authorization;

use App\Models\User;

/**
 * Lojistik admin alanı ve ilgili gate / middleware için izin adı.
 */
final class LogisticsPermission
{
    /** Platform sahibi — tüm tenant'ları oluşturur, arşivler, siler. */
    public const SUPER_ADMIN = 'platform.super_admin';

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

    /** Hesap planı ve yevmiye (çift taraflı kayıt) yazımı. */
    public const FINANCE_WRITE = 'logistics.finance.write';

    public const WAREHOUSE_WRITE = 'logistics.warehouse.write';

    public const CASH_REGISTERS_WRITE = 'logistics.cash-registers.write';

    public const VOUCHERS_WRITE = 'logistics.vouchers.write';

    public const LEAVES_WRITE = 'logistics.leaves.write';

    public const ADVANCES_WRITE = 'logistics.advances.write';

    public const PAYROLL_WRITE = 'logistics.payroll.write';

    public const CURRENT_ACCOUNTS_WRITE = 'logistics.current-accounts.write';

    public const PRICING_CONDITIONS_WRITE = 'logistics.pricing-conditions.write';

    public const TRIP_EXPENSES_WRITE = 'logistics.trip-expenses.write';

    public const SHIFTS_WRITE = 'logistics.shifts.write';

    public const DOCUMENTS_WRITE = 'logistics.documents.write';

    /**
     * Admin kullanıcılar view iznini de kapsar (ADMIN ⊇ VIEW).
     */
    public static function canView(User $user): bool
    {
        return $user->can(self::ADMIN) || $user->can(self::VIEW);
    }

    public static function canWrite(User $user, string $writePermission): bool
    {
        return $user->can(self::ADMIN) || $user->can($writePermission);
    }
}
