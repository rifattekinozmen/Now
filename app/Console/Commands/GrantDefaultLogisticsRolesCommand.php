<?php

namespace App\Console\Commands;

use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Console\Command;

class GrantDefaultLogisticsRolesCommand extends Command
{
    protected $signature = 'logistics:grant-default-roles';

    protected $description = 'tenant_id dolu kullanıcılara varsayılan tenant-user rolünü (logistics.admin) atar; izinler seeder ile güncellenir';

    public function handle(): int
    {
        RolesAndPermissionsSeeder::assignDefaultRoleToTenantUsers();
        $this->components->info('Varsayılan lojistik rolleri güncellendi.');

        return self::SUCCESS;
    }
}
