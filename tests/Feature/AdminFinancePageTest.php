<?php

use App\Models\Customer;
use App\Models\Order;
use App\Models\User;
use Livewire\Livewire;
use Tests\TestCase;

test('finance summary shows freight total for tenant scoped orders', function () {
    /** @var TestCase $this */
    $user = User::factory()->create();
    $customer = Customer::factory()->create(['tenant_id' => $user->tenant_id]);
    Order::factory()->create([
        'customer_id' => $customer->id,
        'tenant_id' => $user->tenant_id,
        'freight_amount' => 1500.5,
        'currency_code' => 'TRY',
    ]);

    $this->actingAs($user);

    $response = $this->get(route('admin.finance.index'));
    $response->assertSuccessful();
    $response->assertSee(__('Freight totals by currency'), false);
    $response->assertSee('TRY', false);
    $response->assertSee('1,500.50', false);
});

test('finance summary date filter limits order counts on page', function () {
    /** @var TestCase $this */
    $user = User::factory()->create();
    $customer = Customer::factory()->create(['tenant_id' => $user->tenant_id]);
    Order::factory()->create([
        'customer_id' => $customer->id,
        'tenant_id' => $user->tenant_id,
        'ordered_at' => now()->subDays(60),
        'freight_amount' => 100,
        'currency_code' => 'TRY',
    ]);
    Order::factory()->create([
        'customer_id' => $customer->id,
        'tenant_id' => $user->tenant_id,
        'ordered_at' => now()->subDay(),
        'freight_amount' => 200,
        'currency_code' => 'TRY',
    ]);

    $this->actingAs($user);

    $from = now()->subDays(7)->toDateString();
    $to = now()->toDateString();

    Livewire::test('pages::admin.finance-index')
        ->set('filterDateFrom', $from)
        ->set('filterDateTo', $to)
        ->assertSet('filterDateFrom', $from)
        ->tap(function ($component) {
            expect($component->instance()->financeIndexKpis['total_orders'])->toBe(1);
        });
});
