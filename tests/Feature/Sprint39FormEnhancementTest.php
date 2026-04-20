<?php

use App\Models\Customer;
use App\Models\Employee;
use App\Models\Tenant;

test('employee can store extended detail fields', function () {
    $tenant = Tenant::factory()->create();

    $employee = Employee::factory()->create([
        'tenant_id' => $tenant->id,
        'sgk_sicil_no' => '12345678',
        'military_status' => 'completed',
        'marital_status' => 'married',
        'passport_no' => 'A1234567',
        'passport_expiry_date' => '2030-01-15',
        'emergency_contact_name' => 'Fatma Yılmaz',
        'emergency_contact_relation' => 'Eş',
        'emergency_contact_phone' => '+905551234567',
    ]);

    $fresh = $employee->fresh();

    expect($fresh->sgk_sicil_no)->toBe('12345678')
        ->and($fresh->military_status)->toBe('completed')
        ->and($fresh->marital_status)->toBe('married')
        ->and($fresh->passport_no)->toBe('A1234567')
        ->and($fresh->passport_expiry_date->format('Y-m-d'))->toBe('2030-01-15')
        ->and($fresh->emergency_contact_name)->toBe('Fatma Yılmaz')
        ->and($fresh->emergency_contact_phone)->toBe('+905551234567');
});

test('employee extended fields can be null without error', function () {
    $tenant = Tenant::factory()->create();

    $employee = Employee::factory()->create([
        'tenant_id' => $tenant->id,
        'sgk_sicil_no' => null,
        'passport_no' => null,
    ]);

    expect($employee->sgk_sicil_no)->toBeNull()
        ->and($employee->passport_no)->toBeNull();
});

test('customer can store mersis no and kep address', function () {
    $tenant = Tenant::factory()->create();

    $customer = Customer::factory()->create([
        'tenant_id' => $tenant->id,
        'mersis_no' => '0123456789000001',
        'kep_address' => 'firma@hs01.kep.tr',
        'credit_limit' => 250000.00,
        'credit_currency_code' => 'TRY',
        'is_blacklisted' => false,
    ]);

    $fresh = $customer->fresh();

    expect($fresh->mersis_no)->toBe('0123456789000001')
        ->and($fresh->kep_address)->toBe('firma@hs01.kep.tr')
        ->and((float) $fresh->credit_limit)->toBe(250000.0)
        ->and($fresh->is_blacklisted)->toBeFalse();
});

test('customer blacklisted flag can be set to true', function () {
    $tenant = Tenant::factory()->create();

    $customer = Customer::factory()->create([
        'tenant_id' => $tenant->id,
        'is_blacklisted' => true,
    ]);

    expect($customer->fresh()->is_blacklisted)->toBeTrue();
});

test('customer credit limit cast is decimal', function () {
    $tenant = Tenant::factory()->create();

    $customer = Customer::factory()->create([
        'tenant_id' => $tenant->id,
        'credit_limit' => 100000,
        'credit_currency_code' => 'USD',
    ]);

    expect($customer->fresh()->credit_currency_code)->toBe('USD')
        ->and($customer->fresh()->credit_limit)->not->toBeNull();
});
