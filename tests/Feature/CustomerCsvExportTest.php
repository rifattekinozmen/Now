<?php

use App\Models\Customer;
use App\Models\Tenant;
use App\Models\User;
use Tests\TestCase;

test('guests cannot export customers csv', function () {
    /** @var TestCase $this */
    $this->get(route('admin.customers.export.csv'))
        ->assertRedirect(route('login'));
});

test('authenticated users download tenant scoped customers csv', function () {
    /** @var TestCase $this */
    $tenant = Tenant::factory()->create();
    /** @var User $user */
    $user = User::factory()->create(['tenant_id' => $tenant->id]);
    $otherTenant = Tenant::factory()->create();

    Customer::factory()->create([
        'tenant_id' => $tenant->id,
        'legal_name' => 'ScopedCorpExport',
        'partner_number' => 'EXP001',
    ]);
    Customer::factory()->create([
        'tenant_id' => $otherTenant->id,
        'legal_name' => 'OtherTenantLeak',
    ]);

    $response = $this->actingAs($user)->get(route('admin.customers.export.csv'));

    $response->assertSuccessful();
    $response->assertDownload('customers.csv');

    $content = $response->streamedContent();
    expect($content)->toContain('Ünvan')
        ->and($content)->toContain('ScopedCorpExport')
        ->and($content)->toContain('EXP001')
        ->and($content)->not->toContain('OtherTenantLeak');
});

test('logistics viewer can export tenant scoped customers csv', function () {
    /** @var TestCase $this */
    $tenant = Tenant::factory()->create();
    /** @var User $user */
    $user = User::factory()->logisticsViewer()->create(['tenant_id' => $tenant->id]);

    Customer::factory()->create([
        'tenant_id' => $tenant->id,
        'legal_name' => 'ViewerExportCo',
    ]);

    $response = $this->actingAs($user)->get(route('admin.customers.export.csv'));

    $response->assertSuccessful();
    expect($response->streamedContent())->toContain('ViewerExportCo');
});
