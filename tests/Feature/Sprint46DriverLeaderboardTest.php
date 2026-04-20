<?php

use App\Models\Employee;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Logistics\DriverScorecardService;
use Illuminate\Support\Carbon;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    Permission::firstOrCreate(['name' => 'logistics.admin', 'guard_name' => 'web']);
});

it('admin can access driver leaderboard page', function (): void {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->create(['tenant_id' => $tenant->id]);
    $role = Role::firstOrCreate(['name' => 'logistics.admin', 'guard_name' => 'web']);
    $user->assignRole($role);

    $this->actingAs($user)
        ->get(route('admin.driver-leaderboard'))
        ->assertSuccessful();
})->group('routes');

it('driver leaderboard route is protected', function (): void {
    $this->get(route('admin.driver-leaderboard'))
        ->assertRedirect(route('login'));
})->group('routes');

it('scorecard service returns empty collection when no drivers', function (): void {
    $tenant = Tenant::factory()->create();
    $service = new DriverScorecardService;
    $result = $service->monthlyLeaderboard($tenant->id, Carbon::now());

    expect($result)->toBeEmpty();
});

it('scorecard service returns driver scores for period', function (): void {
    $tenant = Tenant::factory()->create();
    $service = new DriverScorecardService;

    Employee::factory()->create([
        'tenant_id' => $tenant->id,
        'is_driver' => true,
        'first_name' => 'Ali',
        'last_name' => 'Driver',
    ]);

    $result = $service->monthlyLeaderboard($tenant->id, Carbon::now());

    expect($result)->toHaveCount(1)
        ->and($result->first()['name'])->toBe('Ali Driver')
        ->and($result->first()['score'])->toBeInt()
        ->and($result->first()['badge'])->toBeIn(['gold', 'silver', 'bronze', 'none']);
});

it('badge is gold for score 90 or above', function (): void {
    $service = new DriverScorecardService;

    // A driver with no deliveries still gets 60 pts baseline (fineScore 20 + fuelScore 20 + onTimeScore 20)
    $tenant = Tenant::factory()->create();
    Employee::factory()->create([
        'tenant_id' => $tenant->id,
        'is_driver' => true,
    ]);

    $result = $service->monthlyLeaderboard($tenant->id, Carbon::now());

    expect($result->first()['score'])->toBe(60)
        ->and($result->first()['badge'])->toBe('bronze');
});
