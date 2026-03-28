<?php

use App\Models\Customer;
use App\Services\Logistics\ExcelImportService;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\WithFileUploads;

new #[Title('Customers')] class extends Component
{
    use WithFileUploads;

    public string $legal_name = '';

    public string $tax_id = '';

    public string $trade_name = '';

    public $importFile = null;

    public function mount(): void
    {
        Gate::authorize('viewAny', Customer::class);
    }

    /**
     * @return \Illuminate\Database\Eloquent\Collection<int, Customer>
     */
    public function customerList()
    {
        return Customer::query()->orderByDesc('id')->limit(100)->get();
    }

    public function saveCustomer(): void
    {
        Gate::authorize('create', Customer::class);

        $validated = $this->validate([
            'legal_name' => ['required', 'string', 'max:255'],
            'tax_id' => ['nullable', 'string', 'max:32'],
            'trade_name' => ['nullable', 'string', 'max:255'],
        ]);

        Customer::query()->create([
            'legal_name' => $validated['legal_name'],
            'tax_id' => $validated['tax_id'] ?: null,
            'trade_name' => $validated['trade_name'] ?: null,
            'payment_term_days' => 30,
        ]);

        $this->reset('legal_name', 'tax_id', 'trade_name');
    }

    public function importCustomers(ExcelImportService $excelImport): void
    {
        Gate::authorize('create', Customer::class);

        $this->validate([
            'importFile' => ['required', 'file', 'mimes:xlsx,xls,csv', 'max:10240'],
        ]);

        $tenantId = auth()->user()->tenant_id;
        if ($tenantId === null) {
            abort(403);
        }

        $stored = $this->importFile->store('imports', 'local');
        $path = Storage::disk('local')->path($stored);

        $result = $excelImport->importCustomersFromPath($path, (int) $tenantId);

        session()->flash('import_created', $result['created']);
        session()->flash('import_errors', $result['errors']);

        Storage::disk('local')->delete($stored);

        $this->reset('importFile');
    }
}; ?>

<x-layouts::app :title="__('Customers')">
    <div class="mx-auto flex w-full max-w-6xl flex-col gap-6 p-4 lg:p-8">
        <flux:heading size="xl">{{ __('Customers') }}</flux:heading>

        @if (session()->has('import_created'))
            <flux:callout variant="success" icon="check-circle">
                {{ __('Imported rows: :count', ['count' => session('import_created')]) }}
            </flux:callout>
        @endif

        @if (session()->has('import_errors') && count(session('import_errors')) > 0)
            <flux:callout variant="danger" icon="exclamation-triangle">
                <flux:callout.heading>{{ __('Import errors') }}</flux:callout.heading>
                <flux:callout.text>
                    <ul class="list-inside list-disc text-sm">
                        @foreach (session('import_errors') as $err)
                            <li>{{ __('Row :row: :message', ['row' => $err['row'], 'message' => $err['message']]) }}</li>
                        @endforeach
                    </ul>
                </flux:callout.text>
            </flux:callout>
        @endif

        <div class="grid gap-6 lg:grid-cols-2">
            <flux:card>
                <flux:heading size="lg" class="mb-4">{{ __('New customer') }}</flux:heading>
                <form wire:submit="saveCustomer" class="flex flex-col gap-4">
                    <flux:input wire:model="legal_name" :label="__('Legal name')" required />
                    <flux:input wire:model="tax_id" :label="__('Tax ID')" />
                    <flux:input wire:model="trade_name" :label="__('Trade name')" />
                    <flux:button type="submit" variant="primary">{{ __('Save') }}</flux:button>
                </form>
            </flux:card>

            <flux:card>
                <flux:heading size="lg" class="mb-4">{{ __('Import Excel') }}</flux:heading>
                <p class="mb-4 text-sm text-zinc-600 dark:text-zinc-400">
                    {{ __('First row must be headers matching: İş Ortağı No, Vergi No, Ünvan, Ticari Unvan, Vade Gün.') }}
                </p>
                <div class="flex flex-col gap-4">
                    <flux:input wire:model="importFile" type="file" accept=".xlsx,.xls,.csv" />
                    <flux:button wire:click="importCustomers" variant="primary">{{ __('Import') }}</flux:button>
                </div>
            </flux:card>
        </div>

        <flux:card>
            <flux:heading size="lg" class="mb-4">{{ __('Recent customers') }}</flux:heading>
            <flux:table>
                <flux:table.columns>
                    <flux:table.column>{{ __('Legal name') }}</flux:table.column>
                    <flux:table.column>{{ __('Tax ID') }}</flux:table.column>
                    <flux:table.column>{{ __('Trade name') }}</flux:table.column>
                    <flux:table.column>{{ __('Payment term (days)') }}</flux:table.column>
                </flux:table.columns>
                <flux:table.rows>
                    @forelse ($this->customerList() as $customer)
                        <flux:table.row :key="$customer->id">
                            <flux:table.cell>{{ $customer->legal_name }}</flux:table.cell>
                            <flux:table.cell>{{ $customer->tax_id ?? '—' }}</flux:table.cell>
                            <flux:table.cell>{{ $customer->trade_name ?? '—' }}</flux:table.cell>
                            <flux:table.cell>{{ $customer->payment_term_days }}</flux:table.cell>
                        </flux:table.row>
                    @empty
                        <flux:table.row>
                            <flux:table.cell colspan="4">{{ __('No customers yet.') }}</flux:table.cell>
                        </flux:table.row>
                    @endforelse
                </flux:table.rows>
            </flux:table>
        </flux:card>
    </div>
</x-layouts::app>
