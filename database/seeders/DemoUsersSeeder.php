<?php

namespace Database\Seeders;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Yerel / manuel test için sabit hesaplar (şifre: password).
 *
 * Çalıştırma: php artisan db:seed --class=Database\\Seeders\\DemoUsersSeeder
 * `DatabaseSeeder` yalnızca `APP_ENV=local` iken bu sınıfı çağırır.
 *
 * | E-posta               | Rol                    |
 * |-----------------------|------------------------|
 * | demo-super@now.test   | super-admin            |
 * | demo-admin@now.test   | tenant-user            |
 * | demo-viewer@now.test  | logistics-viewer     |
 * | demo-orders@now.test  | logistics-order-clerk |
 * | demo-hr@now.test      | logistics-hr         |
 */
class DemoUsersSeeder extends Seeder
{
    public const DEMO_TENANT_SLUG = 'demo-lojistik';

    public function run(): void
    {
        RolesAndPermissionsSeeder::ensureDefaults();

        $tenant = Tenant::query()->firstOrCreate(
            ['slug' => self::DEMO_TENANT_SLUG],
            ['name' => 'Demo Lojistik'],
        );

        $password = Hash::make('password');

        $this->seedUser(
            'demo-super@now.test',
            'Demo Super Admin',
            $tenant,
            $password,
            RolesAndPermissionsSeeder::ROLE_SUPER_ADMIN,
        );

        $this->seedUser(
            'demo-admin@now.test',
            'Demo Admin',
            $tenant,
            $password,
            RolesAndPermissionsSeeder::ROLE_TENANT_USER,
        );

        $this->seedUser(
            'demo-viewer@now.test',
            'Demo Viewer',
            $tenant,
            $password,
            RolesAndPermissionsSeeder::ROLE_LOGISTICS_VIEWER,
        );

        $this->seedUser(
            'demo-orders@now.test',
            'Demo Order Clerk',
            $tenant,
            $password,
            RolesAndPermissionsSeeder::ROLE_LOGISTICS_ORDER_CLERK,
        );

        $this->seedUser(
            'demo-hr@now.test',
            'Demo HR',
            $tenant,
            $password,
            RolesAndPermissionsSeeder::ROLE_LOGISTICS_HR,
        );

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    private function seedUser(string $email, string $name, Tenant $tenant, string $password, string $roleName): void
    {
        $user = User::query()->updateOrCreate(
            ['email' => $email],
            [
                'name' => $name,
                'password' => $password,
                'tenant_id' => $tenant->id,
                'active_tenant_id' => $tenant->id,
                'email_verified_at' => now(),
            ],
        );

        $role = Role::query()->where('name', $roleName)->where('guard_name', 'web')->firstOrFail();
        $user->syncRoles([$role]);
        $user->forgetCachedPermissions();
    }
}
