<?php

use App\Models\Customer;
use App\Models\Order;
use App\Models\Tenant;
use App\Models\User;
use Tests\TestCase;

test('tenant user downloads customer import xlsx template', function () {
    /** @var TestCase $this */
    /** @var User $user */
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('admin.customers.template.xlsx'))
        ->assertSuccessful();
});

test('tenant user downloads pin import xlsx template', function () {
    /** @var TestCase $this */
    /** @var User $user */
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('admin.delivery-numbers.template.xlsx'))
        ->assertSuccessful();
});

test('tenant user downloads finance orders csv with scoped rows', function () {
    /** @var TestCase $this */
    $tenant = Tenant::factory()->create();
    /** @var User $user */
    $user = User::factory()->create(['tenant_id' => $tenant->id]);
    $customer = Customer::factory()->create(['tenant_id' => $tenant->id]);
    Order::factory()->create([
        'customer_id' => $customer->id,
        'order_number' => 'CSV-FIN-EXPORT-1',
    ]);

    $response = $this->actingAs($user)->get(route('admin.orders.export.finance.csv'));

    $response->assertSuccessful();
    $content = $response->streamedContent();
    expect($content)->toContain('order_number')
        ->and($content)->toContain('CSV-FIN-EXPORT-1');
});

test('tenant user downloads logo orders xml scoped to tenant', function () {
    /** @var TestCase $this */
    $tenant = Tenant::factory()->create();
    /** @var User $user */
    $user = User::factory()->create(['tenant_id' => $tenant->id]);
    $customer = Customer::factory()->create(['tenant_id' => $tenant->id]);
    Order::factory()->create([
        'tenant_id' => $tenant->id,
        'customer_id' => $customer->id,
        'order_number' => 'XML-LOGO-1',
        'sas_no' => 'SAS-99',
    ]);

    $response = $this->actingAs($user)->get(route('admin.orders.export.logo.xml'));

    $response->assertSuccessful();
    $content = $response->getContent();
    expect($content)->toContain('XML-LOGO-1')
        ->and($content)->toContain('SAS-99')
        ->and($content)->toContain('LogoConnectExport');
});
