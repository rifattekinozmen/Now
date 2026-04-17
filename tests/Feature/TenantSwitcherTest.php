<?php

use App\Models\Tenant;
use App\Models\User;
use App\Support\TenantContext;

// ─────────────────────────────────────────────
// BEHAVIOUR — multi-tenant switcher
// ─────────────────────────────────────────────

it('user is auto-added to user_tenants and active_tenant_id set on creation', function (): void {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->create(['tenant_id' => $tenant->id]);

    expect($user->tenants()->where('tenant_id', $tenant->id)->exists())->toBeTrue();
    expect($user->fresh()->active_tenant_id)->toBe($tenant->id);
})->group('behaviour');

it('user can switch to a tenant they belong to', function (): void {
    $tenantA = Tenant::factory()->create();
    $tenantB = Tenant::factory()->create();

    $user = User::factory()->create(['tenant_id' => $tenantA->id]);

    // Add user to tenantB as well
    $user->tenants()->syncWithoutDetaching([$tenantB->id]);

    $this->actingAs($user)
        ->post(route('tenant.switch', $tenantB))
        ->assertRedirect(route('dashboard'));

    expect($user->fresh()->active_tenant_id)->toBe($tenantB->id);
})->group('behaviour');

it('user cannot switch to a tenant they do not belong to', function (): void {
    $tenantA = Tenant::factory()->create();
    $tenantB = Tenant::factory()->create(); // user is NOT in this tenant

    $user = User::factory()->create(['tenant_id' => $tenantA->id]);

    $this->actingAs($user)
        ->post(route('tenant.switch', $tenantB))
        ->assertForbidden();

    expect($user->fresh()->active_tenant_id)->toBe($tenantA->id);
})->group('behaviour');

it('TenantContext returns active_tenant_id when set', function (): void {
    $tenantA = Tenant::factory()->create();
    $tenantB = Tenant::factory()->create();

    $user = User::factory()->create(['tenant_id' => $tenantA->id]);
    $user->tenants()->syncWithoutDetaching([$tenantB->id]);
    $user->update(['active_tenant_id' => $tenantB->id]);

    $this->actingAs($user);

    expect(TenantContext::id())->toBe($tenantB->id);
})->group('behaviour');

it('TenantContext falls back to tenant_id when active_tenant_id is null', function (): void {
    $tenant = Tenant::factory()->create();

    // Force active_tenant_id to null bypassing boot observer
    $user = User::factory()->create(['tenant_id' => $tenant->id]);
    $user->updateQuietly(['active_tenant_id' => null]);

    $this->actingAs($user);

    expect(TenantContext::id())->toBe($tenant->id);
})->group('behaviour');

it('guest returns null from TenantContext', function (): void {
    expect(TenantContext::id())->toBeNull();
})->group('behaviour');
