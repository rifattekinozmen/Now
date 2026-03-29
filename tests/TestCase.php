<?php

namespace Tests;

use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Laravel\Fortify\Features;
use Spatie\Permission\PermissionRegistrar;

abstract class TestCase extends BaseTestCase
{
    /**
     * Önbelleğe alınmış config (php artisan config:cache) PHPUnit ortamını ve sqlite test
     * veritabanını yok sayar; doğrudan mysql/docker host kullanılır. Testlerde önbelleği kaldır.
     */
    public function createApplication(): Application
    {
        $cacheDir = dirname(__DIR__).'/bootstrap/cache';
        $cachedConfig = $cacheDir.'/config.php';

        if (is_file($cachedConfig)) {
            @unlink($cachedConfig);
        }

        foreach (glob($cacheDir.'/routes-*.php') ?: [] as $cachedRoutes) {
            if (is_file($cachedRoutes)) {
                @unlink($cachedRoutes);
            }
        }

        return parent::createApplication();
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
        $this->app->make(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    protected function skipUnlessFortifyHas(string $feature, ?string $message = null): void
    {
        if (! Features::enabled($feature)) {
            $this->markTestSkipped($message ?? "Fortify feature [{$feature}] is not enabled.");
        }
    }
}
