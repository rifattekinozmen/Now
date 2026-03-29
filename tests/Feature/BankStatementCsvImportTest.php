<?php

use App\Models\BankStatementCsvImport;
use App\Models\ChartAccount;
use App\Models\Customer;
use App\Models\JournalEntry;
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

test('bank csv livewire can post journal row when chart accounts exist', function () {
    /** @var TestCase $this */
    $user = User::factory()->create();
    ChartAccount::factory()->create([
        'tenant_id' => $user->tenant_id,
        'code' => '102',
        'name' => 'Bank',
        'type' => 'asset',
    ]);
    ChartAccount::factory()->create([
        'tenant_id' => $user->tenant_id,
        'code' => '120',
        'name' => 'AR',
        'type' => 'asset',
    ]);
    Customer::factory()->create([
        'tenant_id' => $user->tenant_id,
        'tax_id' => '1234567890',
        'legal_name' => 'Acme Lojistik A.Ş.',
    ]);

    $csv = "Date,Amount,Description\n2026-03-20,99.00,Havale 1234567890 ödeme\n";

    $this->actingAs($user);

    Livewire::test('pages::admin.bank-statement-csv-import')
        ->set('csvFile', UploadedFile::fake()->createWithContent('stmt.csv', $csv))
        ->call('importCsv')
        ->assertHasNoErrors()
        ->set('bankChartAccountId', ChartAccount::query()->where('code', '102')->first()->id)
        ->set('accountsReceivableChartAccountId', ChartAccount::query()->where('code', '120')->first()->id)
        ->call('postJournalRow', 0)
        ->assertHasNoErrors();

    expect(JournalEntry::query()->count())->toBe(1);
    $import = BankStatementCsvImport::query()->first();
    expect($import)->not->toBeNull()
        ->and($import->rows[0])->toHaveKey('journal_entry_id');
});

test('bank csv import enriches rows with customer match candidates by tax id', function () {
    /** @var TestCase $this */
    $user = User::factory()->create();
    Customer::factory()->create([
        'tenant_id' => $user->tenant_id,
        'tax_id' => '1234567890',
        'legal_name' => 'Acme Lojistik A.Ş.',
    ]);

    $csv = "Date,Amount,Description\n2026-03-20,99.00,Havale 1234567890 ödeme\n";

    $this->actingAs($user);

    Livewire::test('pages::admin.bank-statement-csv-import')
        ->set('csvFile', UploadedFile::fake()->createWithContent('stmt.csv', $csv))
        ->call('importCsv')
        ->assertHasNoErrors();

    $import = BankStatementCsvImport::query()->first();
    expect($import)->not->toBeNull()
        ->and($import->rows[0])->toHaveKey('match_candidates')
        ->and($import->rows[0]['match_candidates'])->toHaveCount(1)
        ->and($import->rows[0]['match_candidates'][0]['reason'])->toBe('tax_id')
        ->and($import->rows[0]['match_candidates'][0]['score'])->toBe(100);
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
        $mock->shouldReceive('importCapabilities')
            ->zeroOrMoreTimes()
            ->andReturn((new BankStatementOcrService)->importCapabilities());
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
        $mock->shouldReceive('importCapabilities')
            ->zeroOrMoreTimes()
            ->andReturn((new BankStatementOcrService)->importCapabilities());
        $mock->shouldReceive('extractRowsFromPdfWithDiagnostics')
            ->once()
            ->andReturn(['rows' => [], 'diagnostic' => 'empty_text']);
        $mock->shouldReceive('pdfImportDiagnosticMessage')
            ->once()
            ->with('empty_text')
            ->andReturn((new BankStatementOcrService)->pdfImportDiagnosticMessage('empty_text'));
    });

    $this->actingAs($user);

    Livewire::test('pages::admin.bank-statement-csv-import')
        ->set('pdfFile', UploadedFile::fake()->create('empty.pdf', 50))
        ->call('importPdf')
        ->assertHasErrors(['pdfFile'])
        ->assertSee('likely a scanned image', false);

    expect(BankStatementCsvImport::query()->count())->toBe(0);
});
