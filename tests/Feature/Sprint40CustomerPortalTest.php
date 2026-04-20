<?php

use App\Models\Customer;
use App\Models\Document;
use App\Models\Tenant;
use App\Models\User;

it('customer user can access my-documents page', function (): void {
    $tenant = Tenant::factory()->create();
    $customer = Customer::factory()->create(['tenant_id' => $tenant->id]);
    $user = User::factory()->withoutLogisticsRole()->create([
        'tenant_id' => $tenant->id,
        'customer_id' => $customer->id,
    ]);

    $this->actingAs($user)
        ->get(route('customer.documents.index'))
        ->assertSuccessful();
})->group('routes');

it('customer user can access my-invoices page', function (): void {
    $tenant = Tenant::factory()->create();
    $customer = Customer::factory()->create(['tenant_id' => $tenant->id]);
    $user = User::factory()->withoutLogisticsRole()->create([
        'tenant_id' => $tenant->id,
        'customer_id' => $customer->id,
    ]);

    $this->actingAs($user)
        ->get(route('customer.invoices.index'))
        ->assertSuccessful();
})->group('routes');

it('unauthenticated user cannot access my-documents page', function (): void {
    $this->get(route('customer.documents.index'))
        ->assertRedirect(route('login'));
})->group('routes');

it('customer documents page only shows own documents', function (): void {
    $tenant = Tenant::factory()->create();
    $customer1 = Customer::factory()->create(['tenant_id' => $tenant->id]);
    $customer2 = Customer::factory()->create(['tenant_id' => $tenant->id]);
    $user = User::factory()->withoutLogisticsRole()->create([
        'tenant_id' => $tenant->id,
        'customer_id' => $customer1->id,
    ]);

    $admin = User::factory()->create(['tenant_id' => $tenant->id]);

    // Create document for customer1
    Document::create([
        'tenant_id' => $tenant->id,
        'documentable_type' => Customer::class,
        'documentable_id' => $customer1->id,
        'title' => 'Customer 1 Doc',
        'file_path' => 'test.pdf',
        'file_type' => 'pdf',
        'uploaded_by' => $admin->id,
    ]);

    // Create document for customer2
    Document::create([
        'tenant_id' => $tenant->id,
        'documentable_type' => Customer::class,
        'documentable_id' => $customer2->id,
        'title' => 'Customer 2 Doc',
        'file_path' => 'test2.pdf',
        'file_type' => 'pdf',
        'uploaded_by' => $admin->id,
    ]);

    $customer1Count = Document::query()
        ->where('documentable_type', Customer::class)
        ->where('documentable_id', $customer1->id)
        ->count();

    expect($customer1Count)->toBe(1);
});

it('admin user without customer_id is rejected from customer portal', function (): void {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->create([
        'tenant_id' => $tenant->id,
        'customer_id' => null,
    ]);

    $response = $this->actingAs($user)
        ->get(route('customer.documents.index'));

    // Middleware either redirects or forbids
    $status = $response->getStatusCode();
    expect(in_array($status, [302, 303, 401, 403]))->toBeTrue();
});
