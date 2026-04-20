<?php

use App\Models\User;
use Database\Seeders\DemoUsersSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;

it('seeds demo users with expected roles', function (): void {
    (new RolesAndPermissionsSeeder)->run();
    (new DemoUsersSeeder)->run();

    $super = User::query()->where('email', 'demo-super@now.test')->firstOrFail();
    expect($super->hasRole(RolesAndPermissionsSeeder::ROLE_SUPER_ADMIN))->toBeTrue();
    expect($super->hasRole(RolesAndPermissionsSeeder::ROLE_TENANT_USER))->toBeFalse();

    $admin = User::query()->where('email', 'demo-admin@now.test')->firstOrFail();
    expect($admin->hasRole(RolesAndPermissionsSeeder::ROLE_TENANT_USER))->toBeTrue();

    $viewer = User::query()->where('email', 'demo-viewer@now.test')->firstOrFail();
    expect($viewer->hasRole(RolesAndPermissionsSeeder::ROLE_LOGISTICS_VIEWER))->toBeTrue();

    $orders = User::query()->where('email', 'demo-orders@now.test')->firstOrFail();
    expect($orders->hasRole(RolesAndPermissionsSeeder::ROLE_LOGISTICS_ORDER_CLERK))->toBeTrue();

    $hr = User::query()->where('email', 'demo-hr@now.test')->firstOrFail();
    expect($hr->hasRole(RolesAndPermissionsSeeder::ROLE_LOGISTICS_HR))->toBeTrue();

    expect($super->tenant_id)->toBe($admin->tenant_id)
        ->and($admin->tenant_id)->toBe($viewer->tenant_id)
        ->and($viewer->tenant_id)->toBe($orders->tenant_id)
        ->and($orders->tenant_id)->toBe($hr->tenant_id);

    expect($super->tenant?->slug)->toBe(DemoUsersSeeder::DEMO_TENANT_SLUG);
});
