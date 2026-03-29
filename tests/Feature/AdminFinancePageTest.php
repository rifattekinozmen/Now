<?php

use App\Models\Customer;
use App\Models\Order;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Carbon;
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
    $response->assertSee(__('Cash flow projection'), false);
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

test('finance cash flow projection respects collection window filter', function () {
    /** @var TestCase $this */
    $user = User::factory()->create();
    $customer = Customer::factory()->create([
        'tenant_id' => $user->tenant_id,
        'payment_term_days' => 5,
    ]);
    Order::factory()->create([
        'customer_id' => $customer->id,
        'tenant_id' => $user->tenant_id,
        'ordered_at' => now()->subDays(2),
        'freight_amount' => 300,
        'currency_code' => 'TRY',
    ]);

    $this->actingAs($user);

    $due = now()->subDays(2)->addDays(5)->toDateString();

    Livewire::test('pages::admin.finance-index')
        ->set('projectionDateFrom', $due)
        ->set('projectionDateTo', $due)
        ->assertSee($due, false)
        ->tap(function ($component) {
            expect($component->instance()->cashFlowProjectionRows)->toHaveCount(1);
        })
        ->set('projectionDateFrom', now()->addYear()->toDateString())
        ->set('projectionDateTo', now()->addYear()->addDay()->toDateString())
        ->tap(function ($component) {
            expect($component->instance()->cashFlowProjectionRows)->toHaveCount(0);
        });
});

test('finance reports aging isolates data by tenant', function () {
    /** @var TestCase $this */
    $tenantA = Tenant::factory()->create();
    $tenantB = Tenant::factory()->create();
    $userA = User::factory()->create(['tenant_id' => $tenantA->id]);
    $customerA = Customer::factory()->create([
        'tenant_id' => $tenantA->id,
        'payment_term_days' => 0,
        'legal_name' => 'Tenant A Aging Customer',
    ]);
    $customerB = Customer::factory()->create([
        'tenant_id' => $tenantB->id,
        'payment_term_days' => 0,
        'legal_name' => 'Tenant B Aging Customer',
    ]);
    $asOf = '2026-03-29';
    Order::factory()->create([
        'tenant_id' => $tenantA->id,
        'customer_id' => $customerA->id,
        'order_number' => 'AGING-A',
        'ordered_at' => Carbon::parse($asOf)->subDays(15),
        'freight_amount' => 400,
        'currency_code' => 'TRY',
    ]);
    Order::factory()->create([
        'tenant_id' => $tenantB->id,
        'customer_id' => $customerB->id,
        'order_number' => 'AGING-B',
        'ordered_at' => Carbon::parse($asOf)->subDays(15),
        'freight_amount' => 800,
        'currency_code' => 'TRY',
    ]);

    $this->actingAs($userA);

    Livewire::test('pages::admin.finance-reports')
        ->set('asOfDate', $asOf)
        ->assertSee('Tenant A Aging Customer', false)
        ->assertDontSee('Tenant B Aging Customer', false);
});

test('payment due calendar page loads for logistics user', function () {
    /** @var TestCase $this */
    $user = User::factory()->create();

    $this->actingAs($user);

    $this->get(route('admin.finance.payment-due-calendar'))
        ->assertSuccessful()
        ->assertSee(__('Payment due calendar'), false);
});

test('finance audit freight outliers are isolated by tenant', function () {
    /** @var TestCase $this */
    $tenantA = Tenant::factory()->create();
    $tenantB = Tenant::factory()->create();
    $userA = User::factory()->create(['tenant_id' => $tenantA->id]);
    $customerA = Customer::factory()->create(['tenant_id' => $tenantA->id]);
    $customerB = Customer::factory()->create(['tenant_id' => $tenantB->id]);

    Order::factory()->create([
        'customer_id' => $customerA->id,
        'tenant_id' => $tenantA->id,
        'freight_amount' => 100,
        'currency_code' => 'TRY',
        'order_number' => 'ORD-A-1',
    ]);
    Order::factory()->create([
        'customer_id' => $customerA->id,
        'tenant_id' => $tenantA->id,
        'freight_amount' => 100,
        'currency_code' => 'TRY',
        'order_number' => 'ORD-A-2',
    ]);
    Order::factory()->create([
        'customer_id' => $customerA->id,
        'tenant_id' => $tenantA->id,
        'freight_amount' => 900,
        'currency_code' => 'TRY',
        'order_number' => 'OUTLIER-TENANT-A',
    ]);
    Order::factory()->create([
        'customer_id' => $customerB->id,
        'tenant_id' => $tenantB->id,
        'freight_amount' => 9999,
        'currency_code' => 'TRY',
        'order_number' => 'OUTLIER-TENANT-B-ONLY',
    ]);

    $this->actingAs($userA);

    Livewire::test('pages::admin.finance-index')
        ->assertSee('OUTLIER-TENANT-A', false)
        ->assertDontSee('OUTLIER-TENANT-B-ONLY', false);
});

test('finance summary surfaces freight outlier order from audit rule', function () {
    /** @var TestCase $this */
    $user = User::factory()->create();
    $customer = Customer::factory()->create(['tenant_id' => $user->tenant_id]);
    Order::factory()->create([
        'customer_id' => $customer->id,
        'tenant_id' => $user->tenant_id,
        'freight_amount' => 100,
        'currency_code' => 'TRY',
    ]);
    Order::factory()->create([
        'customer_id' => $customer->id,
        'tenant_id' => $user->tenant_id,
        'freight_amount' => 100,
        'currency_code' => 'TRY',
    ]);
    Order::factory()->create([
        'customer_id' => $customer->id,
        'tenant_id' => $user->tenant_id,
        'freight_amount' => 900,
        'currency_code' => 'TRY',
        'order_number' => 'FLAGGED-OUTLIER',
    ]);

    $this->actingAs($user);

    $this->get(route('admin.finance.index'))
        ->assertSuccessful()
        ->assertSee('FLAGGED-OUTLIER', false)
        ->assertSee(__('Operational audit (freight vs median)'), false);
});

test('payment due calendar isolates projected dues by tenant', function () {
    /** @var TestCase $this */
    $tenantA = Tenant::factory()->create();
    $tenantB = Tenant::factory()->create();
    $userA = User::factory()->create(['tenant_id' => $tenantA->id]);
    $userB = User::factory()->create(['tenant_id' => $tenantB->id]);

    $customerA = Customer::factory()->create([
        'tenant_id' => $tenantA->id,
        'payment_term_days' => 0,
    ]);
    $customerB = Customer::factory()->create([
        'tenant_id' => $tenantB->id,
        'payment_term_days' => 0,
    ]);

    $orderedAt = now()->startOfMonth()->startOfDay();
    Order::factory()->create([
        'tenant_id' => $tenantA->id,
        'customer_id' => $customerA->id,
        'order_number' => 'TENANT-A-ONLY',
        'ordered_at' => $orderedAt,
    ]);
    Order::factory()->create([
        'tenant_id' => $tenantB->id,
        'customer_id' => $customerB->id,
        'order_number' => 'TENANT-B-ONLY',
        'ordered_at' => $orderedAt,
    ]);

    $month = $orderedAt->format('Y-m');
    $dueDate = $orderedAt->toDateString();

    $this->actingAs($userA);
    Livewire::test('pages::admin.finance-payment-due-calendar')
        ->set('month', $month)
        ->set('selectedDate', $dueDate)
        ->assertSee('TENANT-A-ONLY', false)
        ->assertDontSee('TENANT-B-ONLY', false);

    $this->actingAs($userB);
    Livewire::test('pages::admin.finance-payment-due-calendar')
        ->set('month', $month)
        ->set('selectedDate', $dueDate)
        ->assertSee('TENANT-B-ONLY', false)
        ->assertDontSee('TENANT-A-ONLY', false);
});
