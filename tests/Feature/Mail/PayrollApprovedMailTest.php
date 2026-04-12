<?php

use App\Enums\PayrollStatus;
use App\Mail\PayrollApprovedMail;
use App\Models\Employee;
use App\Models\Payroll;
use App\Models\Tenant;
use Illuminate\Support\Facades\Mail;
use Spatie\Permission\Models\Permission;

beforeEach(function (): void {
    Permission::firstOrCreate(['name' => 'logistics.admin', 'guard_name' => 'web']);
    Mail::fake();
});

it('payroll approved mailable can be instantiated with payroll model', function (): void {
    $tenant = Tenant::factory()->create();
    $employee = Employee::factory()->create(['tenant_id' => $tenant->id]);
    $payroll = Payroll::factory()->create([
        'tenant_id' => $tenant->id,
        'employee_id' => $employee->id,
        'status' => PayrollStatus::Draft->value,
    ]);

    $mailable = new PayrollApprovedMail($payroll);

    expect($mailable)->toBeInstanceOf(PayrollApprovedMail::class);
    expect($mailable->payroll->id)->toBe($payroll->id);
});

it('payroll approved mailable uses correct view', function (): void {
    $tenant = Tenant::factory()->create();
    $employee = Employee::factory()->create(['tenant_id' => $tenant->id]);
    $payroll = Payroll::factory()->create([
        'tenant_id' => $tenant->id,
        'employee_id' => $employee->id,
        'status' => PayrollStatus::Approved->value,
        'approved_at' => now(),
    ]);

    $mailable = new PayrollApprovedMail($payroll);

    expect($mailable->content()->view)->toBe('emails.payroll-approved');
    expect($mailable->envelope()->subject)->toContain(__('Your payroll has been approved'));
});
