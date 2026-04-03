<?php

use App\Authorization\LogisticsPermission;
use App\Livewire\Concerns\RequiresLogisticsAdmin;
use App\Models\Customer;
use App\Services\Logistics\ExcelImportService;
use App\Support\TenantContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\WithFileUploads;
use Livewire\WithPagination;

new #[Lazy, Title('Customers')] class extends Component
{
    use RequiresLogisticsAdmin;
    use WithFileUploads;
    use WithPagination;

    public string $legal_name = '';

    public string $tax_id = '';

    public string $trade_name = '';

    public int $payment_term_days = 30;

    public ?int $editingCustomerId = null;

    public $importFile = null;

    public string $filterSearch = '';

    public string $sortColumn = 'id';

    public string $sortDirection = 'desc';

    public bool $filtersOpen = false;

    /** @var list<int|string> */
    public array $selectedIds = [];

    public function mount(): void
    {
        Gate::authorize('viewAny', Customer::class);
    }

    public function updatedFilterSearch(): void
    {
        $this->resetPage();
        $this->selectedIds = [];
        $this->cancelCustomerEdit();
    }

    public function updatedPage(): void
    {
        $this->selectedIds = [];
        $this->cancelCustomerEdit();
    }

    /**
     * @return array{total: int, new_this_month: int, with_tax_id: int, avg_payment_term: int}
     */
    #[Computed]
    public function customerStats(): array
    {
        $monthStart = now()->startOfMonth()->toDateTimeString();
        $tenantId = TenantContext::id();

        if ($tenantId !== null) {
            $row = DB::selectOne(
                'SELECT
                    (SELECT COUNT(*) FROM customers WHERE tenant_id = ?) AS total,
                    (SELECT COUNT(*) FROM customers WHERE tenant_id = ? AND created_at >= ?) AS new_this_month,
                    (SELECT COUNT(*) FROM customers WHERE tenant_id = ? AND tax_id IS NOT NULL AND tax_id != ?) AS with_tax_id,
                    (SELECT ROUND(AVG(payment_term_days)) FROM customers WHERE tenant_id = ?) AS avg_payment_term',
                [$tenantId, $tenantId, $monthStart, $tenantId, '', $tenantId]
            );
        } else {
            $row = DB::selectOne(
                'SELECT
                    (SELECT COUNT(*) FROM customers) AS total,
                    (SELECT COUNT(*) FROM customers WHERE created_at >= ?) AS new_this_month,
                    (SELECT COUNT(*) FROM customers WHERE tax_id IS NOT NULL AND tax_id != ?) AS with_tax_id,
                    (SELECT ROUND(AVG(payment_term_days)) FROM customers) AS avg_payment_term',
                [$monthStart, '']
            );
        }

        return [
            'total' => (int) ($row->total ?? 0),
            'new_this_month' => (int) ($row->new_this_month ?? 0),
            'with_tax_id' => (int) ($row->with_tax_id ?? 0),
            'avg_payment_term' => (int) ($row->avg_payment_term ?? 0),
        ];
    }

    /**
     * @return Builder<Customer>
     */
    private function customersQuery(): Builder
    {
        $q = Customer::query();

        if ($this->filterSearch !== '') {
            $term = '%'.addcslashes($this->filterSearch, '%_\\').'%';
            $q->where(function (Builder $qq) use ($term): void {
                $qq->where('legal_name', 'like', $term)
                    ->orWhere('trade_name', 'like', $term)
                    ->orWhere('tax_id', 'like', $term);
            });
        }

        $allowed = ['id', 'legal_name', 'tax_id', 'trade_name', 'payment_term_days', 'created_at'];
        $column = in_array($this->sortColumn, $allowed, true) ? $this->sortColumn : 'id';
        $direction = $this->sortDirection === 'asc' ? 'asc' : 'desc';

        return $q->orderBy($column, $direction);
    }

    #[Computed]
    public function paginatedCustomers(): LengthAwarePaginator
    {
        return $this->customersQuery()->paginate(15);
    }

    public function sortBy(string $column): void
    {
        $allowed = ['id', 'legal_name', 'tax_id', 'trade_name', 'payment_term_days', 'created_at'];
        if (! in_array($column, $allowed, true)) {
            return;
        }

        if ($this->sortColumn === $column) {
            $this->sortDirection = $this->sortDirection === 'asc' ? 'desc' : 'asc';
        } else {
            $this->sortColumn = $column;
            $this->sortDirection = 'asc';
        }

        $this->resetPage();
        $this->selectedIds = [];
        $this->cancelCustomerEdit();
    }

    public function isPageFullySelected(): bool
    {
        $pageIds = $this->paginatedCustomers->pluck('id')->map(fn ($id) => (int) $id)->all();
        if ($pageIds === []) {
            return false;
        }

        $selected = array_map('intval', $this->selectedIds);

        return count(array_diff($pageIds, $selected)) === 0;
    }

    public function toggleSelectPage(): void
    {
        $pageIds = $this->paginatedCustomers->pluck('id')->map(fn ($id) => (int) $id)->all();
        $selected = array_map('intval', $this->selectedIds);
        $allSelected = $pageIds !== [] && count(array_diff($pageIds, $selected)) === 0;

        if ($allSelected) {
            $this->selectedIds = array_values(array_diff($selected, $pageIds));
        } else {
            $this->selectedIds = array_values(array_unique(array_merge($selected, $pageIds)));
        }

        $this->selectedIds = array_values(array_map('intval', $this->selectedIds));
    }

    public function bulkDeleteSelected(): void
    {
        $this->ensureLogisticsAdmin();

        if ($this->selectedIds === []) {
            return;
        }

        $ids = array_map('intval', $this->selectedIds);
        $customers = Customer::query()->whereIn('id', $ids)->get();
        $deleted = 0;

        foreach ($customers as $customer) {
            Gate::authorize('delete', $customer);
            $customer->delete();
            $deleted++;
        }

        $this->selectedIds = [];
        $this->resetPage();

        session()->flash('bulk_deleted', $deleted);
    }

    public function saveCustomer(): void
    {
        $this->ensureLogisticsWrite(LogisticsPermission::CUSTOMERS_WRITE);

        Gate::authorize('create', Customer::class);

        $validated = $this->validate([
            'legal_name' => ['required', 'string', 'max:255'],
            'tax_id' => ['nullable', 'string', 'max:32'],
            'trade_name' => ['nullable', 'string', 'max:255'],
            'payment_term_days' => ['required', 'integer', 'min:0', 'max:3650'],
        ]);

        Customer::query()->create([
            'legal_name' => $validated['legal_name'],
            'tax_id' => $validated['tax_id'] ?: null,
            'trade_name' => $validated['trade_name'] ?: null,
            'payment_term_days' => $validated['payment_term_days'],
        ]);

        $this->reset('legal_name', 'tax_id', 'trade_name', 'payment_term_days');
        $this->payment_term_days = 30;
    }

    public function startEditCustomer(int $customerId): void
    {
        $this->ensureLogisticsWrite(LogisticsPermission::CUSTOMERS_WRITE);

        $customer = Customer::query()->findOrFail($customerId);
        Gate::authorize('update', $customer);

        $this->editingCustomerId = $customer->id;
        $this->legal_name = $customer->legal_name;
        $this->tax_id = $customer->tax_id ?? '';
        $this->trade_name = $customer->trade_name ?? '';
        $this->payment_term_days = $customer->payment_term_days;
    }

    public function cancelCustomerEdit(): void
    {
        $this->editingCustomerId = null;
        $this->reset('legal_name', 'tax_id', 'trade_name', 'payment_term_days');
        $this->payment_term_days = 30;
    }

    public function updateCustomer(): void
    {
        $this->ensureLogisticsWrite(LogisticsPermission::CUSTOMERS_WRITE);

        if ($this->editingCustomerId === null) {
            return;
        }

        $customer = Customer::query()->findOrFail($this->editingCustomerId);
        Gate::authorize('update', $customer);

        $validated = $this->validate([
            'legal_name' => ['required', 'string', 'max:255'],
            'tax_id' => ['nullable', 'string', 'max:32'],
            'trade_name' => ['nullable', 'string', 'max:255'],
            'payment_term_days' => ['required', 'integer', 'min:0', 'max:3650'],
        ]);

        $customer->update([
            'legal_name' => $validated['legal_name'],
            'tax_id' => $validated['tax_id'] ?: null,
            'trade_name' => $validated['trade_name'] ?: null,
            'payment_term_days' => $validated['payment_term_days'],
        ]);

        $this->cancelCustomerEdit();

        session()->flash('customer_updated', true);
    }

    public function deleteCustomer(int $customerId): void
    {
        $this->ensureLogisticsWrite(LogisticsPermission::CUSTOMERS_WRITE);

        $customer = Customer::query()->findOrFail($customerId);
        Gate::authorize('delete', $customer);
        $customer->delete();

        $this->selectedIds = array_values(array_diff(
            array_map('intval', $this->selectedIds),
            [(int) $customerId]
        ));

        if ($this->editingCustomerId === $customerId) {
            $this->cancelCustomerEdit();
        }

        session()->flash('customer_deleted', true);
    }

    public function importCustomers(ExcelImportService $excelImport): void
    {
        $this->ensureLogisticsWrite(LogisticsPermission::CUSTOMERS_WRITE);

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

<div class="mx-auto flex w-full max-w-6xl flex-col gap-6 p-4 lg:p-8">
    @php
        $authUser = auth()->user();
        $canWriteCustomers =
            $authUser instanceof \App\Models\User
            && \App\Authorization\LogisticsPermission::canWrite($authUser, \App\Authorization\LogisticsPermission::CUSTOMERS_WRITE);
    @endphp
    <x-admin.page-header
        :heading="__('Customers')"
        :description="__('Legal names, payment terms, and CSV/XLSX import — scoped to your tenant.')"
    >
        <x-slot name="breadcrumb">
            <span class="font-medium text-zinc-800 dark:text-zinc-100">{{ __('Customers') }}</span>
        </x-slot>
        <x-slot name="actions">
            <flux:tooltip :content="__('Export CSV')" position="bottom">
                <flux:button icon="arrow-down-tray" variant="outline" :href="route('admin.customers.export.csv')" />
            </flux:tooltip>
            <flux:tooltip :content="__('Download XLSX template')" position="bottom">
                <flux:button icon="document-arrow-down" variant="outline" :href="route('admin.customers.template.xlsx')" />
            </flux:tooltip>
        </x-slot>
    </x-admin.page-header>

    @if (session()->has('bulk_deleted'))
        <flux:callout variant="success" icon="check-circle">
            {{ __('Deleted :count records.', ['count' => session('bulk_deleted')]) }}
        </flux:callout>
    @endif

    @if (session()->has('customer_updated'))
        <flux:callout variant="success" icon="check-circle">
            {{ __('Customer updated.') }}
        </flux:callout>
    @endif

    @if (session()->has('customer_deleted'))
        <flux:callout variant="success" icon="check-circle">
            {{ __('Customer deleted.') }}
        </flux:callout>
    @endif

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

    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <flux:card class="!p-4">
            <flux:text class="text-sm text-zinc-500 dark:text-zinc-400">{{ __('Total customers') }}</flux:text>
            <flux:heading size="xl">{{ $this->customerStats['total'] }}</flux:heading>
        </flux:card>
        <flux:card class="!p-4">
            <flux:text class="text-sm text-zinc-500 dark:text-zinc-400">{{ __('New this month') }}</flux:text>
            <flux:heading size="xl">{{ $this->customerStats['new_this_month'] }}</flux:heading>
        </flux:card>
        <flux:card class="!p-4">
            <flux:text class="text-sm text-zinc-500 dark:text-zinc-400">{{ __('With tax ID') }}</flux:text>
            <flux:heading size="xl">{{ $this->customerStats['with_tax_id'] }}</flux:heading>
        </flux:card>
        <flux:card class="!p-4">
            <flux:text class="text-sm text-zinc-500 dark:text-zinc-400">{{ __('Avg. payment term') }}</flux:text>
            <flux:heading size="xl">{{ $this->customerStats['avg_payment_term'] }}</flux:heading>
        </flux:card>
    </div>

    <x-admin.filter-bar :label="__('Advanced filters')">
        <div class="flex flex-wrap items-center justify-between gap-2">
            <flux:button type="button" variant="ghost" size="sm" wire:click="$toggle('filtersOpen')">
                {{ $filtersOpen ? __('Hide') : __('Show') }}
            </flux:button>
        </div>
        @if ($filtersOpen)
            <flux:input
                wire:model.live.debounce.400ms="filterSearch"
                :label="__('Search (name, tax ID, trade name)')"
            />
        @endif
    </x-admin.filter-bar>

    @if ($canWriteCustomers)
        <div class="grid gap-6 lg:grid-cols-2">
            @if ($editingCustomerId !== null)
                <flux:card>
                    <flux:heading size="lg" class="mb-4">{{ __('Edit customer') }}</flux:heading>
                    <form wire:submit="updateCustomer" class="flex flex-col gap-4">
                        <flux:input wire:model="legal_name" :label="__('Legal name')" required />
                        <flux:input wire:model="tax_id" :label="__('Tax ID')" />
                        <flux:input wire:model="trade_name" :label="__('Trade name')" />
                        <flux:input wire:model="payment_term_days" type="number" min="0" max="3650" :label="__('Payment term (days)')" />
                        <div class="flex flex-wrap gap-2">
                            <flux:button type="submit" variant="primary">{{ __('Save changes') }}</flux:button>
                            <flux:button type="button" variant="ghost" wire:click="cancelCustomerEdit">{{ __('Cancel') }}</flux:button>
                        </div>
                    </form>
                </flux:card>
            @else
                <flux:card>
                    <flux:heading size="lg" class="mb-4">{{ __('New customer') }}</flux:heading>
                    <form wire:submit="saveCustomer" class="flex flex-col gap-4">
                        <flux:input wire:model="legal_name" :label="__('Legal name')" required />
                        <flux:input wire:model="tax_id" :label="__('Tax ID')" />
                        <flux:input wire:model="trade_name" :label="__('Trade name')" />
                        <flux:input wire:model="payment_term_days" type="number" min="0" max="3650" :label="__('Payment term (days)')" />
                        <flux:button type="submit" variant="primary">{{ __('Save') }}</flux:button>
                    </form>
                </flux:card>
            @endif

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
    @endif

    @if ($canWriteCustomers)
        @if (count($selectedIds) > 0)
            <div class="flex flex-wrap items-center gap-4 rounded-lg border border-zinc-200 bg-zinc-50 p-4 dark:border-zinc-600 dark:bg-zinc-900">
                <flux:text>{{ __(':count selected', ['count' => count($selectedIds)]) }}</flux:text>
                <flux:button
                    type="button"
                    variant="danger"
                    wire:click="bulkDeleteSelected"
                    wire:confirm="{{ __('Delete selected customers? This also removes their orders and shipments.') }}"
                >
                    {{ __('Delete selected') }}
                </flux:button>
            </div>
        @endif
    @endif

    <flux:card>
        <flux:heading size="lg" class="mb-4">{{ __('Recent customers') }}</flux:heading>
        <flux:table>
            <flux:table.columns>
                @if ($canWriteCustomers)
                    <flux:table.column class="w-12">
                        <span class="sr-only">{{ __('Select page') }}</span>
                        <input
                            type="checkbox"
                            class="size-4 rounded border-zinc-300 text-primary focus:ring-primary dark:border-zinc-600"
                            @checked($this->isPageFullySelected())
                            wire:click.prevent="toggleSelectPage"
                            wire:key="select-page-customers"
                        />
                    </flux:table.column>
                @endif
                <flux:table.column>
                    <button type="button" wire:click="sortBy('id')" class="flex items-center gap-1 font-medium text-zinc-800 dark:text-white">
                        {{ __('ID') }}
                        @if ($sortColumn === 'id')
                            <span class="text-xs">{{ $sortDirection === 'asc' ? '↑' : '↓' }}</span>
                        @endif
                    </button>
                </flux:table.column>
                <flux:table.column>
                    <button type="button" wire:click="sortBy('legal_name')" class="flex items-center gap-1 font-medium text-zinc-800 dark:text-white">
                        {{ __('Legal name') }}
                        @if ($sortColumn === 'legal_name')
                            <span class="text-xs">{{ $sortDirection === 'asc' ? '↑' : '↓' }}</span>
                        @endif
                    </button>
                </flux:table.column>
                <flux:table.column>
                    <button type="button" wire:click="sortBy('tax_id')" class="flex items-center gap-1 font-medium text-zinc-800 dark:text-white">
                        {{ __('Tax ID') }}
                        @if ($sortColumn === 'tax_id')
                            <span class="text-xs">{{ $sortDirection === 'asc' ? '↑' : '↓' }}</span>
                        @endif
                    </button>
                </flux:table.column>
                <flux:table.column>
                    <button type="button" wire:click="sortBy('trade_name')" class="flex items-center gap-1 font-medium text-zinc-800 dark:text-white">
                        {{ __('Trade name') }}
                        @if ($sortColumn === 'trade_name')
                            <span class="text-xs">{{ $sortDirection === 'asc' ? '↑' : '↓' }}</span>
                        @endif
                    </button>
                </flux:table.column>
                <flux:table.column>
                    <button type="button" wire:click="sortBy('payment_term_days')" class="flex items-center gap-1 font-medium text-zinc-800 dark:text-white">
                        {{ __('Payment term (days)') }}
                        @if ($sortColumn === 'payment_term_days')
                            <span class="text-xs">{{ $sortDirection === 'asc' ? '↑' : '↓' }}</span>
                        @endif
                    </button>
                </flux:table.column>
                <flux:table.column>
                    <button type="button" wire:click="sortBy('created_at')" class="flex items-center gap-1 font-medium text-zinc-800 dark:text-white">
                        {{ __('Created at') }}
                        @if ($sortColumn === 'created_at')
                            <span class="text-xs">{{ $sortDirection === 'asc' ? '↑' : '↓' }}</span>
                        @endif
                    </button>
                </flux:table.column>
                <flux:table.column>{{ __('Actions') }}</flux:table.column>
            </flux:table.columns>
            <flux:table.rows>
                @forelse ($this->paginatedCustomers as $customer)
                    <flux:table.row :key="$customer->id">
                        @if ($canWriteCustomers)
                            <flux:table.cell>
                                <flux:checkbox
                                    wire:key="customer-select-{{ $customer->id }}"
                                    wire:model.live="selectedIds"
                                    :value="(int) $customer->id"
                                />
                            </flux:table.cell>
                        @endif
                        <flux:table.cell>{{ $customer->id }}</flux:table.cell>
                        <flux:table.cell>{{ $customer->legal_name }}</flux:table.cell>
                        <flux:table.cell>{{ $customer->tax_id ?? '—' }}</flux:table.cell>
                        <flux:table.cell>{{ $customer->trade_name ?? '—' }}</flux:table.cell>
                        <flux:table.cell>{{ $customer->payment_term_days }}</flux:table.cell>
                        <flux:table.cell>{{ $customer->created_at?->timezone(config('app.timezone'))->format('Y-m-d H:i') }}</flux:table.cell>
                        <flux:table.cell>
                            <div class="flex flex-wrap items-center gap-2">
                                <flux:link :href="route('admin.customers.show', $customer)" wire:navigate>
                                    {{ __('View') }}
                                </flux:link>
                                @if ($canWriteCustomers)
                                    <flux:button type="button" size="sm" variant="ghost" wire:click="startEditCustomer({{ $customer->id }})">
                                        {{ __('Edit') }}
                                    </flux:button>
                                    <flux:button
                                        type="button"
                                        size="sm"
                                        variant="danger"
                                        wire:click="deleteCustomer({{ $customer->id }})"
                                        wire:confirm="{{ __('Delete this customer? This also removes their orders and shipments.') }}"
                                    >
                                        {{ __('Delete') }}
                                    </flux:button>
                                @endif
                            </div>
                        </flux:table.cell>
                    </flux:table.row>
                @empty
                    <flux:table.row>
                        <flux:table.cell colspan="{{ $canWriteCustomers ? 8 : 7 }}">{{ __('No customers yet.') }}</flux:table.cell>
                    </flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>
        <div class="mt-4">
            {{ $this->paginatedCustomers->links() }}
        </div>
    </flux:card>
</div>
