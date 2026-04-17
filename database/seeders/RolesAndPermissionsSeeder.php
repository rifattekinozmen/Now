<?php

namespace Database\Seeders;

use App\Authorization\LogisticsPermission;
use App\Models\User;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RolesAndPermissionsSeeder extends Seeder
{
    /** Platform sahibi — tüm şirketleri yönetir. */
    public const ROLE_SUPER_ADMIN = 'super-admin';

    public const ROLE_TENANT_USER = 'tenant-user';

    /** Sadece `logistics.view` — operasyon yazamaz. */
    public const ROLE_LOGISTICS_VIEWER = 'logistics-viewer';

    /** Örnek kısıtlı rol: sadece sipariş yazımı + görüntüleme (ince izin testleri). */
    public const ROLE_LOGISTICS_ORDER_CLERK = 'logistics-order-clerk';

    /** İK / personel kayıtları: görüntüleme + çalışan yazımı. */
    public const ROLE_LOGISTICS_HR = 'logistics-hr';

    public function run(): void
    {
        self::ensureDefaults();
    }

    /**
     * Rol ve izinleri idempotent oluşturur (kayıt, seeder, test).
     */
    public static function ensureDefaults(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $permSuperAdmin = Permission::findOrCreate(LogisticsPermission::SUPER_ADMIN, 'web');
        $roleSuperAdmin = Role::findOrCreate(self::ROLE_SUPER_ADMIN, 'web');
        $roleSuperAdmin->syncPermissions([$permSuperAdmin]);

        $permAdmin = Permission::findOrCreate(LogisticsPermission::ADMIN, 'web');
        $permView = Permission::findOrCreate(LogisticsPermission::VIEW, 'web');
        foreach ([
            LogisticsPermission::CUSTOMERS_WRITE,
            LogisticsPermission::ORDERS_WRITE,
            LogisticsPermission::SHIPMENTS_WRITE,
            LogisticsPermission::VEHICLES_WRITE,
            LogisticsPermission::PINS_WRITE,
            LogisticsPermission::EMPLOYEES_WRITE,
            LogisticsPermission::FINANCE_WRITE,
            LogisticsPermission::WAREHOUSE_WRITE,
            LogisticsPermission::CASH_REGISTERS_WRITE,
            LogisticsPermission::VOUCHERS_WRITE,
            LogisticsPermission::LEAVES_WRITE,
            LogisticsPermission::ADVANCES_WRITE,
            LogisticsPermission::PAYROLL_WRITE,
            LogisticsPermission::CURRENT_ACCOUNTS_WRITE,
            LogisticsPermission::PRICING_CONDITIONS_WRITE,
            LogisticsPermission::TRIP_EXPENSES_WRITE,
        ] as $writePermission) {
            Permission::findOrCreate($writePermission, 'web');
        }

        $permOrdersWrite = Permission::findOrCreate(LogisticsPermission::ORDERS_WRITE, 'web');
        $permEmployeesWrite = Permission::findOrCreate(LogisticsPermission::EMPLOYEES_WRITE, 'web');

        $roleTenant = Role::findOrCreate(self::ROLE_TENANT_USER, 'web');
        // ADMIN kullanıcılar VIEW erişimini de kapsar (ADMIN ⊇ VIEW).
        $roleTenant->syncPermissions([$permAdmin, $permView]);

        $roleViewer = Role::findOrCreate(self::ROLE_LOGISTICS_VIEWER, 'web');
        $roleViewer->syncPermissions([$permView]);

        $roleOrderClerk = Role::findOrCreate(self::ROLE_LOGISTICS_ORDER_CLERK, 'web');
        $roleOrderClerk->syncPermissions([$permView, $permOrdersWrite]);

        $roleHr = Role::findOrCreate(self::ROLE_LOGISTICS_HR, 'web');
        $roleHr->syncPermissions([$permView, $permEmployeesWrite]);
    }

    /**
     * Kiracılı kullanıcılara varsayılan lojistik rolünü atar (mevcut veritabanları için).
     */
    public static function assignDefaultRoleToTenantUsers(): void
    {
        self::ensureDefaults();

        foreach (User::query()->whereNotNull('tenant_id')->cursor() as $user) {
            if (! $user->hasRole(self::ROLE_TENANT_USER)) {
                $user->assignRole(self::ROLE_TENANT_USER);
            }
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
