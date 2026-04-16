<?php

use App\Enums\DocumentCategory;
use App\Enums\DocumentFileType;
use App\Models\Document;
use App\Models\Tenant;
use App\Models\User;
use Spatie\Permission\Models\Permission;

beforeEach(function (): void {
    Permission::firstOrCreate(['name' => 'logistics.admin', 'guard_name' => 'web']);
    Permission::firstOrCreate(['name' => 'logistics.view', 'guard_name' => 'web']);
    Permission::firstOrCreate(['name' => 'logistics.documents.write', 'guard_name' => 'web']);
});

// ─────────────────────────────────────────────
// MODEL — factory states and scopes
// ─────────────────────────────────────────────

it('document factory creates a valid record', function (): void {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->create(['tenant_id' => $tenant->id]);

    $doc = Document::factory()->create([
        'tenant_id' => $tenant->id,
        'uploaded_by' => $user->id,
    ]);

    expect($doc->file_type)->toBeInstanceOf(DocumentFileType::class)
        ->and($doc->category)->toBeInstanceOf(DocumentCategory::class)
        ->and($doc->title)->not->toBeEmpty();
});

it('expiringSoon scope returns documents expiring within 30 days', function (): void {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->create(['tenant_id' => $tenant->id]);

    Document::factory()->expiringSoon(15)->create([
        'tenant_id' => $tenant->id,
        'uploaded_by' => $user->id,
    ]);
    Document::factory()->create([
        'tenant_id' => $tenant->id,
        'uploaded_by' => $user->id,
        'expires_at' => now()->addDays(60)->format('Y-m-d'),
    ]);

    $this->actingAs($user);
    expect(Document::query()->expiringSoon(30)->count())->toBe(1);
});

it('expired scope returns only expired documents', function (): void {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->create(['tenant_id' => $tenant->id]);

    Document::factory()->expired()->create([
        'tenant_id' => $tenant->id,
        'uploaded_by' => $user->id,
    ]);
    Document::factory()->expiringSoon(10)->create([
        'tenant_id' => $tenant->id,
        'uploaded_by' => $user->id,
    ]);

    $this->actingAs($user);
    expect(Document::query()->expired()->count())->toBe(1);
});

// ─────────────────────────────────────────────
// ROUTE — page access
// ─────────────────────────────────────────────

it('admin can access documents index page', function (): void {
    $tenant = Tenant::factory()->create();
    $admin = User::factory()->create(['tenant_id' => $tenant->id]);
    $admin->givePermissionTo('logistics.admin');

    $this->actingAs($admin)
        ->get(route('admin.documents.index'))
        ->assertSuccessful();
})->group('routes');

it('viewer can access documents index page', function (): void {
    $tenant = Tenant::factory()->create();
    $viewer = User::factory()->withoutLogisticsRole()->create(['tenant_id' => $tenant->id]);
    $viewer->givePermissionTo('logistics.view');

    $this->actingAs($viewer)
        ->get(route('admin.documents.index'))
        ->assertSuccessful();
})->group('routes');

it('unauthenticated user is redirected from documents index', function (): void {
    $this->get(route('admin.documents.index'))
        ->assertRedirect();
})->group('routes');

// ─────────────────────────────────────────────
// TENANT ISOLATION
// ─────────────────────────────────────────────

it('cannot see another tenant\'s documents via global scope', function (): void {
    $tenantA = Tenant::factory()->create();
    $tenantB = Tenant::factory()->create();

    $userA = User::factory()->create(['tenant_id' => $tenantA->id]);
    $userA->givePermissionTo('logistics.admin');

    $userB = User::factory()->create(['tenant_id' => $tenantB->id]);
    $docB = Document::factory()->create([
        'tenant_id' => $tenantB->id,
        'uploaded_by' => $userB->id,
    ]);

    $this->actingAs($userA);

    $found = Document::query()->where('id', $docB->id)->first();
    expect($found)->toBeNull();
})->group('isolation');

// ─────────────────────────────────────────────
// POLICY
// ─────────────────────────────────────────────

it('viewer cannot delete a document', function (): void {
    $tenant = Tenant::factory()->create();
    $viewer = User::factory()->withoutLogisticsRole()->create(['tenant_id' => $tenant->id]);
    $viewer->givePermissionTo('logistics.view');

    $doc = Document::factory()->create([
        'tenant_id' => $tenant->id,
        'uploaded_by' => $viewer->id,
    ]);

    $this->actingAs($viewer);

    expect($viewer->can('delete', $doc))->toBeFalse();
})->group('policy');

it('admin can delete a document', function (): void {
    $tenant = Tenant::factory()->create();
    $admin = User::factory()->create(['tenant_id' => $tenant->id]);
    $admin->givePermissionTo('logistics.admin');

    $doc = Document::factory()->create([
        'tenant_id' => $tenant->id,
        'uploaded_by' => $admin->id,
    ]);

    $this->actingAs($admin);

    expect($admin->can('delete', $doc))->toBeTrue();
})->group('policy');
