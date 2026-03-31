<?php

use App\Models\ActivityLog;
use App\Models\Order;
use App\Models\Tenant;
use App\Models\User;
use App\Traits\LogsActivity;

// ─────────────────────────────────────────────
// TRAIT AUTO-LOGGING
// ─────────────────────────────────────────────

it('creates an activity log entry when an order is created', function (): void {
    $tenant = Tenant::factory()->create();
    $admin = User::factory()->create(['tenant_id' => $tenant->id]);
    $admin->givePermissionTo('logistics.admin');
    $this->actingAs($admin);

    $order = Order::factory()->create(['tenant_id' => $tenant->id]);

    $log = ActivityLog::query()
        ->where('subject_type', Order::class)
        ->where('subject_id', $order->id)
        ->where('event', 'created')
        ->first();

    expect($log)->not->toBeNull()
        ->and($log->user_id)->toBe($admin->id)
        ->and($log->tenant_id)->toBe($tenant->id);
})->group('activity-log');

it('creates an activity log entry when an order is updated', function (): void {
    $tenant = Tenant::factory()->create();
    $admin = User::factory()->create(['tenant_id' => $tenant->id]);
    $admin->givePermissionTo('logistics.admin');
    $this->actingAs($admin);

    $order = Order::factory()->create(['tenant_id' => $tenant->id]);

    $order->update(['incoterms' => 'FOB']);

    $log = ActivityLog::query()
        ->where('subject_type', Order::class)
        ->where('subject_id', $order->id)
        ->where('event', 'updated')
        ->first();

    expect($log)->not->toBeNull()
        ->and($log->properties)->toHaveKey('changed');
})->group('activity-log');

it('creates an activity log entry when an order is deleted', function (): void {
    $tenant = Tenant::factory()->create();
    $admin = User::factory()->create(['tenant_id' => $tenant->id]);
    $admin->givePermissionTo('logistics.admin');
    $this->actingAs($admin);

    $order = Order::factory()->create(['tenant_id' => $tenant->id]);
    $orderId = $order->id;

    $order->delete();

    $log = ActivityLog::query()
        ->where('subject_type', Order::class)
        ->where('subject_id', $orderId)
        ->where('event', 'deleted')
        ->first();

    expect($log)->not->toBeNull();
})->group('activity-log');

// ─────────────────────────────────────────────
// ActivityLog::log() static helper
// ─────────────────────────────────────────────

it('ActivityLog::log() records tenant_id from authenticated user', function (): void {
    $tenant = Tenant::factory()->create();
    $admin = User::factory()->create(['tenant_id' => $tenant->id]);
    $this->actingAs($admin);

    $order = Order::factory()->create(['tenant_id' => $tenant->id]);

    // Disable the automatic trait logging by using ::withoutEvents to avoid duplicates
    $log = ActivityLog::log($order, 'custom_event', 'manual log', ['foo' => 'bar']);

    expect($log->tenant_id)->toBe($tenant->id)
        ->and($log->user_id)->toBe($admin->id)
        ->and($log->event)->toBe('custom_event')
        ->and($log->description)->toBe('manual log')
        ->and($log->properties)->toBe(['foo' => 'bar']);
})->group('activity-log');

it('ActivityLog::log() sets tenant_id to null when unauthenticated', function (): void {
    $tenant = Tenant::factory()->create();
    $admin = User::factory()->create(['tenant_id' => $tenant->id]);

    // Must act as someone to create the order with tenant scope
    $this->actingAs($admin);
    $order = Order::factory()->create(['tenant_id' => $tenant->id]);

    // Now logout
    auth()->logout();

    $log = ActivityLog::log($order, 'system_event');

    expect($log->tenant_id)->toBeNull()
        ->and($log->user_id)->toBeNull();
})->group('activity-log');

// ─────────────────────────────────────────────
// ORDER model uses LogsActivity trait
// ─────────────────────────────────────────────

it('Order model uses the LogsActivity trait', function (): void {
    expect(in_array(LogsActivity::class, class_uses_recursive(Order::class)))->toBeTrue();
})->group('activity-log');
