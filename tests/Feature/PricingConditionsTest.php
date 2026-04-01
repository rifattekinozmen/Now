<?php

use App\Models\Customer;
use App\Models\PricingCondition;
use App\Models\Tenant;
use App\Models\User;
use App\Policies\PricingConditionPolicy;
use Spatie\Permission\Models\Permission;

beforeEach(function (): void {
    Permission::firstOrCreate(['name' => 'logistics.admin', 'guard_name' => 'web']);
    Permission::firstOrCreate(['name' => 'logistics.pricing-conditions.write', 'guard_name' => 'web']);
});

// ─────────────────────────────────────────────
// TENANT ISOLATION
// ─────────────────────────────────────────────

it('cannot read another tenant\'s pricing conditions', function (): void {
    $tenantA = Tenant::factory()->create();
    $tenantB = Tenant::factory()->create();

    $userA = User::factory()->create(['tenant_id' => $tenantA->id]);
    $userA->givePermissionTo('logistics.admin');

    $customerA = Customer::factory()->create(['tenant_id' => $tenantA->id]);
    $customerB = Customer::factory()->create(['tenant_id' => $tenantB->id]);

    $condA = PricingCondition::factory()->create(['tenant_id' => $tenantA->id, 'customer_id' => $customerA->id]);
    $condB = PricingCondition::factory()->create(['tenant_id' => $tenantB->id, 'customer_id' => $customerB->id]);

    $this->actingAs($userA);

    $conditions = PricingCondition::query()->get();
    expect($conditions->pluck('id'))->not->toContain($condB->id)
        ->and($conditions->pluck('id'))->toContain($condA->id);
})->group('isolation');

// ─────────────────────────────────────────────
// ROUTE ACCESS
// ─────────────────────────────────────────────

it('admin can access the pricing conditions page', function (): void {
    $tenant = Tenant::factory()->create();
    $admin = User::factory()->create(['tenant_id' => $tenant->id]);
    $admin->givePermissionTo('logistics.admin');

    $this->actingAs($admin)
        ->get(route('admin.pricing-conditions.index'))
        ->assertSuccessful();
})->group('route');

it('unauthenticated user is redirected from pricing conditions page', function (): void {
    $this->get(route('admin.pricing-conditions.index'))
        ->assertRedirect();
})->group('route');

// ─────────────────────────────────────────────
// POLICY
// ─────────────────────────────────────────────

it('user with write permission can create pricing conditions via policy', function (): void {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->withoutLogisticsRole()->create(['tenant_id' => $tenant->id]);
    $user->givePermissionTo('logistics.pricing-conditions.write');

    $policy = new PricingConditionPolicy;
    expect($policy->create($user->fresh()))->toBeTrue();
})->group('policy');

it('user without write permission cannot create pricing conditions', function (): void {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->withoutLogisticsRole()->create(['tenant_id' => $tenant->id]);

    $policy = new PricingConditionPolicy;
    expect($policy->create($user->fresh()))->toBeFalse();
})->group('policy');

// ─────────────────────────────────────────────
// SCOPES & FILTERING
// ─────────────────────────────────────────────

it('active scope returns only active pricing conditions', function (): void {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->create(['tenant_id' => $tenant->id]);
    $customer = Customer::factory()->create(['tenant_id' => $tenant->id]);
    $this->actingAs($user);

    PricingCondition::factory()->create(['tenant_id' => $tenant->id, 'customer_id' => $customer->id, 'is_active' => true]);
    PricingCondition::factory()->inactive()->create(['tenant_id' => $tenant->id, 'customer_id' => $customer->id]);

    $active = PricingCondition::query()->active()->get();
    expect($active)->toHaveCount(1)
        ->and((bool) $active->first()->is_active)->toBeTrue();
})->group('scope');

it('expiringSoon scope returns conditions expiring within given days', function (): void {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->create(['tenant_id' => $tenant->id]);
    $customer = Customer::factory()->create(['tenant_id' => $tenant->id]);
    $this->actingAs($user);

    PricingCondition::factory()->expiringSoon(15)->create(['tenant_id' => $tenant->id, 'customer_id' => $customer->id]);
    PricingCondition::factory()->create([
        'tenant_id' => $tenant->id,
        'customer_id' => $customer->id,
        'valid_until' => now()->addDays(90)->toDateString(),
    ]);

    $expiring = PricingCondition::query()->expiringSoon(30)->get();
    expect($expiring)->toHaveCount(1);
})->group('scope');

it('can filter pricing conditions by customer', function (): void {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->create(['tenant_id' => $tenant->id]);
    $customerA = Customer::factory()->create(['tenant_id' => $tenant->id]);
    $customerB = Customer::factory()->create(['tenant_id' => $tenant->id]);
    $this->actingAs($user);

    PricingCondition::factory()->create(['tenant_id' => $tenant->id, 'customer_id' => $customerA->id]);
    PricingCondition::factory()->create(['tenant_id' => $tenant->id, 'customer_id' => $customerB->id]);

    $filtered = PricingCondition::query()->where('customer_id', $customerA->id)->get();
    expect($filtered)->toHaveCount(1)
        ->and($filtered->first()->customer_id)->toBe($customerA->id);
})->group('filter');
