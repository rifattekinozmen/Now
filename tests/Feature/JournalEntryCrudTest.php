<?php

use App\Models\ChartAccount;
use App\Models\JournalEntry;
use App\Models\Tenant;
use App\Models\User;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;

beforeEach(function (): void {
    Permission::firstOrCreate(['name' => 'logistics.admin', 'guard_name' => 'web']);
    Permission::firstOrCreate(['name' => 'logistics.view', 'guard_name' => 'web']);
    Permission::firstOrCreate(['name' => 'logistics.finance.write', 'guard_name' => 'web']);
});

it('admin can view journal entries page', function (): void {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->create(['tenant_id' => $tenant->id]);
    $user->givePermissionTo('logistics.admin');

    $this->actingAs($user)
        ->get(route('admin.finance.journal-entries.index'))
        ->assertSuccessful();
});

it('viewer can view journal entries page', function (): void {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->create(['tenant_id' => $tenant->id]);
    $user->givePermissionTo('logistics.view');

    $this->actingAs($user)
        ->get(route('admin.finance.journal-entries.index'))
        ->assertSuccessful();
});

it('unauthenticated user is redirected from journal entries', function (): void {
    $this->get(route('admin.finance.journal-entries.index'))
        ->assertRedirect();
});

it('finance writer can create a balanced journal entry', function (): void {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->create(['tenant_id' => $tenant->id]);
    $user->givePermissionTo('logistics.admin');
    $user->givePermissionTo('logistics.finance.write');

    $acc1 = ChartAccount::factory()->create(['tenant_id' => $tenant->id]);
    $acc2 = ChartAccount::factory()->create(['tenant_id' => $tenant->id]);

    $component = Livewire::actingAs($user)
        ->test('pages::admin.journal-entries-index')
        ->call('initCreate')
        ->set('entryDate', '2026-01-15')
        ->set('reference', 'JE-TEST-001')
        ->set('memo', 'Test entry')
        ->set('lines', [
            ['chart_account_id' => (string) $acc1->id, 'debit' => '1000.00', 'credit' => ''],
            ['chart_account_id' => (string) $acc2->id, 'debit' => '', 'credit' => '1000.00'],
        ])
        ->call('saveEntry');

    $component->assertHasNoErrors();

    expect(JournalEntry::query()->where('reference', 'JE-TEST-001')->exists())->toBeTrue();

    $entry = JournalEntry::query()->where('reference', 'JE-TEST-001')->first();
    expect($entry->lines()->count())->toBe(2)
        ->and((float) $entry->lines()->sum('debit'))->toBe(1000.0)
        ->and((float) $entry->lines()->sum('credit'))->toBe(1000.0);
});

it('rejects unbalanced journal entry', function (): void {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->create(['tenant_id' => $tenant->id]);
    $user->givePermissionTo('logistics.admin');
    $user->givePermissionTo('logistics.finance.write');

    $acc1 = ChartAccount::factory()->create(['tenant_id' => $tenant->id]);
    $acc2 = ChartAccount::factory()->create(['tenant_id' => $tenant->id]);

    Livewire::actingAs($user)
        ->test('pages::admin.journal-entries-index')
        ->call('initCreate')
        ->set('entryDate', '2026-01-15')
        ->set('lines', [
            ['chart_account_id' => (string) $acc1->id, 'debit' => '500.00', 'credit' => ''],
            ['chart_account_id' => (string) $acc2->id, 'debit' => '', 'credit' => '999.00'],
        ])
        ->call('saveEntry')
        ->assertHasErrors('lines');
});

it('finance writer can delete a manual journal entry', function (): void {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->create(['tenant_id' => $tenant->id]);
    $user->givePermissionTo('logistics.admin');
    $user->givePermissionTo('logistics.finance.write');

    $entry = JournalEntry::factory()->create([
        'tenant_id' => $tenant->id,
        'user_id' => $user->id,
        'source_type' => null,
    ]);

    Livewire::actingAs($user)
        ->test('pages::admin.journal-entries-index')
        ->call('deleteEntry', $entry->id);

    expect(JournalEntry::query()->find($entry->id))->toBeNull();
});

it('journal entries are tenant-scoped', function (): void {
    $tenantA = Tenant::factory()->create();
    $tenantB = Tenant::factory()->create();

    $userA = User::factory()->create(['tenant_id' => $tenantA->id]);
    $userA->givePermissionTo('logistics.admin');

    $entryB = JournalEntry::factory()->create(['tenant_id' => $tenantB->id]);

    $this->actingAs($userA);

    $visible = JournalEntry::query()->get();
    expect($visible->pluck('id'))->not->toContain($entryB->id);
});
