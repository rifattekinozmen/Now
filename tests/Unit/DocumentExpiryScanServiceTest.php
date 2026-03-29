<?php

use App\Contracts\Operations\OperationalNotifier;
use App\Models\Employee;
use App\Models\Tenant;
use App\Models\Vehicle;
use App\Services\Logistics\DocumentExpiryScanService;
use Carbon\CarbonImmutable;

test('sends notification when vehicle inspection expires on threshold day', function () {
    CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-03-01'));

    $tenant = Tenant::factory()->create();
    Vehicle::factory()->create([
        'tenant_id' => $tenant->id,
        'plate' => '34TEST99',
        'inspection_valid_until' => '2026-03-31',
    ]);

    $calls = [];
    $notifier = new class($calls) implements OperationalNotifier
    {
        /**
         * @param  list<array{event: string, context: array<string, mixed>}>  $calls
         */
        public function __construct(private array &$calls) {}

        public function notify(string $event, array $context = []): void
        {
            $this->calls[] = ['event' => $event, 'context' => $context];
        }
    };

    $scanner = new DocumentExpiryScanService($notifier);
    expect($scanner->scan())->toBe(1);
    expect($calls)->toHaveCount(1)
        ->and($calls[0]['event'])->toBe('logistics.document_expiry_due')
        ->and($calls[0]['context']['entity'])->toBe('vehicle_inspection')
        ->and($calls[0]['context']['days_remaining'])->toBe(30);

    CarbonImmutable::setTestNow(null);
});

test('sends notification for employee license on threshold day', function () {
    CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-06-01'));

    $tenant = Tenant::factory()->create();
    Employee::factory()->create([
        'tenant_id' => $tenant->id,
        'first_name' => 'Ali',
        'last_name' => 'Veli',
        'license_valid_until' => '2026-06-08',
    ]);

    $calls = [];
    $notifier = new class($calls) implements OperationalNotifier
    {
        /**
         * @param  list<array{event: string, context: array<string, mixed>}>  $calls
         */
        public function __construct(private array &$calls) {}

        public function notify(string $event, array $context = []): void
        {
            $this->calls[] = ['event' => $event, 'context' => $context];
        }
    };

    $scanner = new DocumentExpiryScanService($notifier);
    expect($scanner->scan())->toBe(1);
    expect($calls[0]['context']['entity'])->toBe('employee_license')
        ->and($calls[0]['context']['days_remaining'])->toBe(7);

    CarbonImmutable::setTestNow(null);
});
