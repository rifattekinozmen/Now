<?php

use App\Models\BankStatementCsvImport;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Finance\BankStatementOcrService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Http\UploadedFile;
use Livewire\Livewire;
use Tests\TestCase;

test('logistics admin can upload bank csv and persist tenant scoped import', function () {
    /** @var TestCase $this */
    $user = User::factory()->create();
    $csv = "Date,Amount,Description\n2026-03-20,99.00,Test row\n";

    $this->actingAs($user);

    Livewire::test('pages::admin.bank-statement-csv-import')
        ->set('csvFile', UploadedFile::fake()->createWithContent('stmt.csv', $csv))
        ->call('importCsv')
        ->assertHasNoErrors();

    $import = BankStatementCsvImport::query()->first();
    expect($import)->not->toBeNull()
        ->and($import->tenant_id)->toBe($user->tenant_id)
        ->and($import->user_id)->toBe($user->id)
        ->and($import->row_count)->toBe(1)
        ->and($import->rows[0]['amount'])->toBe('99.00');
});

test('logistics viewer cannot access bank csv import page', function () {
    /** @var TestCase $this */
    RolesAndPermissionsSeeder::ensureDefaults();
    $user = User::factory()->logisticsViewer()->create();

    $this->actingAs($user);

    $this->get(route('admin.finance.bank-statement-csv'))->assertForbidden();
});

test('other tenant cannot see bank import records', function () {
    /** @var TestCase $this */
    $tenantA = Tenant::factory()->create();
    $tenantB = Tenant::factory()->create();
    $userA = User::factory()->create(['tenant_id' => $tenantA->id]);
    $userB = User::factory()->create(['tenant_id' => $tenantB->id]);

    BankStatementCsvImport::withoutEvents(function () use ($tenantA, $userA): void {
        BankStatementCsvImport::query()->create([
            'tenant_id' => $tenantA->id,
            'user_id' => $userA->id,
            'original_filename' => 'a.csv',
            'row_count' => 0,
            'rows' => [],
        ]);
    });

    $this->actingAs($userB);

    expect(BankStatementCsvImport::query()->count())->toBe(0);
});

test('logistics admin can upload bank pdf and persist import when parser returns rows', function () {
    /** @var TestCase $this */
    $user = User::factory()->create();

    $this->mock(BankStatementOcrService::class, function ($mock): void {
        $mock->shouldReceive('extractRowsFromPdfWithDiagnostics')
            ->once()
            ->andReturn([
                'rows' => [
                    ['booked_at' => '2026-04-01', 'amount' => '50.00', 'description' => 'PDF row'],
                ],
                'diagnostic' => 'ok',
            ]);
    });

    $this->actingAs($user);

    Livewire::test('pages::admin.bank-statement-csv-import')
        ->set('pdfFile', UploadedFile::fake()->create('stmt.pdf', 100))
        ->call('importPdf')
        ->assertHasNoErrors();

    $import = BankStatementCsvImport::query()->first();
    expect($import)->not->toBeNull()
        ->and($import->row_count)->toBe(1)
        ->and($import->rows[0]['description'])->toBe('PDF row');
});

test('logistics admin sees distinct message when pdf has no text layer', function () {
    /** @var TestCase $this */
    $user = User::factory()->create();

    $this->mock(BankStatementOcrService::class, function ($mock): void {
        $mock->shouldReceive('extractRowsFromPdfWithDiagnostics')
            ->once()
            ->andReturn(['rows' => [], 'diagnostic' => 'empty_text']);
    });

    $this->actingAs($user);

    Livewire::test('pages::admin.bank-statement-csv-import')
        ->set('pdfFile', UploadedFile::fake()->create('empty.pdf', 50))
        ->call('importPdf')
        ->assertHasErrors(['pdfFile'])
        ->assertSee('likely a scanned image', false);

    expect(BankStatementCsvImport::query()->count())->toBe(0);
});
