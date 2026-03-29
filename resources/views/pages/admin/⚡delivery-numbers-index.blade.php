<?php

use App\Authorization\LogisticsPermission;
use App\Enums\DeliveryNumberStatus;
use App\Livewire\Concerns\RequiresLogisticsAdmin;
use App\Models\DeliveryNumber;
use App\Models\Order;
use App\Services\Logistics\ExcelImportService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithFileUploads;
use Livewire\WithPagination;

new #[Title('PIN pool')] class extends Component
{
    use RequiresLogisticsAdmin;
    use WithFileUploads;
    use WithPagination;

    public string $pin_code = '';

    public string $sas_no = '';

    public string $assign_pin_code = '';

    public string $assign_order_id = '';

    public mixed $pinImportFile = null;

    public string $filterSearch = '';

    public string $filterStatus = '';

    public string $sortColumn = 'id';

    public string $sortDirection = 'desc';

    public bool $filtersOpen = false;

    /** @var list<int|string> */
    public array $selectedIds = [];

    public function mount(): void
    {
        Gate::authorize('viewAny', DeliveryNumber::class);
    }

    public function updatedFilterSearch(): void
    {
        $this->resetPage();
        $this->selectedIds = [];
    }

    public function updatedFilterStatus(): void
    {
        $this->resetPage();
        $this->selectedIds = [];
    }

    public function updatedPage(): void
    {
        $this->selectedIds = [];
    }

    /**
     * @return array{total: int, available: int, assigned: int, used: int}
     */
    #[Computed]
    public function pinIndexStats(): array
    {
        $available = DeliveryNumberStatus::Available->value;
        $assigned = DeliveryNumberStatus::Assigned->value;
        $used = DeliveryNumberStatus::Used->value;

        $row = DeliveryNumber::query()
            ->toBase()
            ->selectRaw(
                'COUNT(*) as total, '.
                'SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as available, '.
                'SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as assigned, '.
                'SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as used',
                [$available, $assigned, $used]
            )
            ->first();

        return [
            'total' => (int) ($row->total ?? 0),
            'available' => (int) ($row->available ?? 0),
            'assigned' => (int) ($row->assigned ?? 0),
            'used' => (int) ($row->used ?? 0),
        ];
    }

    public function pinStatusLabel(DeliveryNumberStatus $status): string
    {
        return match ($status) {
            DeliveryNumberStatus::Available => __('PIN available'),
            DeliveryNumberStatus::Assigned => __('PIN assigned'),
            DeliveryNumberStatus::Used => __('PIN used'),
        };
    }

    /**
     * @return Builder<DeliveryNumber>
     */
    private function deliveryNumbersQuery(): Builder
    {
        $q = DeliveryNumber::query()->with(['order.customer']);

        if ($this->filterStatus !== '') {
            $q->where('status', $this->filterStatus);
        }

        if ($this->filterSearch !== '') {
            $term = '%'.addcslashes($this->filterSearch, '%_\\').'%';
            $q->where(function (Builder $qq) use ($term): void {
                $qq->where('pin_code', 'like', $term)
                    ->orWhere('sas_no', 'like', $term);
            });
        }

        $allowed = ['id', 'pin_code', 'sas_no', 'status', 'created_at'];
        $column = in_array($this->sortColumn, $allowed, true) ? $this->sortColumn : 'id';
        $direction = $this->sortDirection === 'asc' ? 'asc' : 'desc';

        return $q->orderBy($column, $direction);
    }

    #[Computed]
    public function paginatedPins(): LengthAwarePaginator
    {
        return $this->deliveryNumbersQuery()->paginate(15);
    }

    public function sortBy(string $column): void
    {
        $allowed = ['id', 'pin_code', 'sas_no', 'status', 'created_at'];
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
    }

    public function isPageFullySelected(): bool
    {
        $pageIds = $this->paginatedPins->pluck('id')->map(fn ($id) => (int) $id)->all();
        if ($pageIds === []) {
            return false;
        }

        $selected = array_map('intval', $this->selectedIds);

        return count(array_diff($pageIds, $selected)) === 0;
    }

    public function toggleSelectPage(): void
    {
        $pageIds = $this->paginatedPins->pluck('id')->map(fn ($id) => (int) $id)->all();
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
        $this->ensureLogisticsWrite(LogisticsPermission::PINS_WRITE);

        if ($this->selectedIds === []) {
            return;
        }

        $ids = array_map('intval', $this->selectedIds);
        $pins = DeliveryNumber::query()->whereIn('id', $ids)->get();
        $deleted = 0;

        foreach ($pins as $dn) {
            if ($dn->status !== DeliveryNumberStatus::Available) {
                continue;
            }
            Gate::authorize('delete', $dn);
            $dn->delete();
            $deleted++;
        }

        $this->selectedIds = [];
        $this->resetPage();

        if ($deleted > 0) {
            session()->flash('bulk_deleted', $deleted);
        } else {
            session()->flash('error', __('No deletable PINs in selection (only available PINs can be deleted).'));
        }
    }

    /**
     * @return \Illuminate\Database\Eloquent\Collection<int, Order>
     */
    public function orderOptions()
    {
        return Order::query()->with('customer')->orderByDesc('id')->limit(200)->get();
    }

    public function addPin(): void
    {
        $this->ensureLogisticsWrite(LogisticsPermission::PINS_WRITE);

        Gate::authorize('create', DeliveryNumber::class);

        $tenantId = auth()->user()->tenant_id;
        if ($tenantId === null) {
            abort(403);
        }

        $validated = $this->validate([
            'pin_code' => [
                'required',
                'string',
                'max:64',
                Rule::unique('delivery_numbers', 'pin_code')->where('tenant_id', $tenantId),
            ],
            'sas_no' => ['nullable', 'string', 'max:64'],
        ]);

        DeliveryNumber::query()->create([
            'pin_code' => trim($validated['pin_code']),
            'sas_no' => isset($validated['sas_no']) && $validated['sas_no'] !== '' ? trim($validated['sas_no']) : null,
            'status' => DeliveryNumberStatus::Available,
        ]);

        $this->reset('pin_code', 'sas_no');
    }

    public function assignPinToOrder(): void
    {
        $this->ensureLogisticsWrite(LogisticsPermission::PINS_WRITE);

        $tenantId = auth()->user()->tenant_id;
        if ($tenantId === null) {
            abort(403);
        }

        $validated = $this->validate([
            'assign_pin_code' => ['required', 'string', 'max:64'],
            'assign_order_id' => [
                'required',
                'integer',
                Rule::exists('orders', 'id')->where('tenant_id', $tenantId),
            ],
        ]);

        $pin = trim($validated['assign_pin_code']);

        /** @var DeliveryNumber|null $dn */
        $dn = DeliveryNumber::query()
            ->where('pin_code', $pin)
            ->where('status', DeliveryNumberStatus::Available)
            ->first();

        if ($dn === null) {
            $this->addError('assign_pin_code', __('No available PIN matches this code.'));

            return;
        }

        Gate::authorize('update', $dn);

        /** @var Order $order */
        $order = Order::query()->findOrFail((int) $validated['assign_order_id']);

        DB::transaction(function () use ($dn, $order): void {
            $dn->update([
                'order_id' => $order->id,
                'status' => DeliveryNumberStatus::Assigned,
                'assigned_at' => now(),
            ]);

            if ($order->sas_no === null && $dn->sas_no !== null) {
                $order->update(['sas_no' => $dn->sas_no]);
            }
        });

        $this->reset('assign_pin_code', 'assign_order_id');
    }

    public function deleteAvailable(int $id): void
    {
        $this->ensureLogisticsWrite(LogisticsPermission::PINS_WRITE);

        $dn = DeliveryNumber::query()->findOrFail($id);
        Gate::authorize('delete', $dn);

        if ($dn->status !== DeliveryNumberStatus::Available) {
            return;
        }

        $dn->delete();
    }

    public function importPinsCsv(ExcelImportService $excel): void
    {
        $this->ensureLogisticsWrite(LogisticsPermission::PINS_WRITE);

        Gate::authorize('create', DeliveryNumber::class);

        $tenantId = auth()->user()->tenant_id;
        if ($tenantId === null) {
            abort(403);
        }

        $this->validate([
            'pinImportFile' => ['required', 'file', 'mimes:csv,txt', 'max:5120'],
        ]);

        $path = $this->pinImportFile->getRealPath();
        if ($path === false || ! is_readable($path)) {
            session()->flash('error', __('Could not read the uploaded file.'));

            return;
        }

        $result = $excel->importDeliveryPinsFromPath($path, (int) $tenantId);
        $this->reset('pinImportFile');
        session()->flash('pin_import_result', $result);
    }
}; ?>

<div class="mx-auto flex w-full max-w-6xl flex-col gap-6 p-4 lg:p-8">
    @php
        $authUser = auth()->user();
        $canWritePins =
            $authUser instanceof \App\Models\User
            && \App\Authorization\LogisticsPermission::canWrite($authUser, \App\Authorization\LogisticsPermission::PINS_WRITE);
    @endphp
    <x-admin.page-header :heading="__('PIN pool')">
        <x-slot name="breadcrumb">
            <span class="font-medium text-zinc-800 dark:text-zinc-100">{{ __('PIN pool') }}</span>
        </x-slot>
        <x-slot name="actions">
            <flux:button :href="route('admin.delivery-numbers.template.xlsx')" variant="outline">{{ __('Download XLSX template') }}</flux:button>
        </x-slot>
    </x-admin.page-header>

    @if (session()->has('pin_import_result'))
        @php($r = session('pin_import_result'))
        <flux:callout variant="success" icon="check-circle" class="mb-2">
            {{ __('Imported: :c, skipped (already in pool): :s', ['c' => $r['created'], 's' => $r['skipped']]) }}
        </flux:callout>
        @if ($r['errors'] !== [])
            <flux:callout variant="danger" icon="exclamation-triangle">
                <flux:callout.heading>{{ __('Import row errors') }}</flux:callout.heading>
                <flux:callout.text>
                    <ul class="list-inside list-disc text-sm">
                        @foreach (array_slice($r['errors'], 0, 15) as $err)
                            <li>{{ __('Row :row: :msg', ['row' => $err['row'], 'msg' => $err['message']]) }}</li>
                        @endforeach
                    </ul>
                </flux:callout.text>
            </flux:callout>
        @endif
    @endif
    @if (session()->has('error'))
        <flux:callout variant="danger" icon="exclamation-triangle">{{ session('error') }}</flux:callout>
    @endif

    @if (session()->has('bulk_deleted'))
        <flux:callout variant="success" icon="check-circle">
            {{ __('Deleted :count records.', ['count' => session('bulk_deleted')]) }}
        </flux:callout>
    @endif

    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <flux:card class="!p-4">
            <flux:text class="text-sm text-zinc-500 dark:text-zinc-400">{{ __('Total PINs') }}</flux:text>
            <flux:heading size="xl">{{ $this->pinIndexStats['total'] }}</flux:heading>
        </flux:card>
        <flux:card class="!p-4">
            <flux:text class="text-sm text-zinc-500 dark:text-zinc-400">{{ __('Available PINs') }}</flux:text>
            <flux:heading size="xl">{{ $this->pinIndexStats['available'] }}</flux:heading>
        </flux:card>
        <flux:card class="!p-4">
            <flux:text class="text-sm text-zinc-500 dark:text-zinc-400">{{ __('Assigned PINs') }}</flux:text>
            <flux:heading size="xl">{{ $this->pinIndexStats['assigned'] }}</flux:heading>
        </flux:card>
        <flux:card class="!p-4">
            <flux:text class="text-sm text-zinc-500 dark:text-zinc-400">{{ __('Used PINs') }}</flux:text>
            <flux:heading size="xl">{{ $this->pinIndexStats['used'] }}</flux:heading>
        </flux:card>
    </div>

    <x-admin.filter-bar :label="__('Advanced filters')">
        <div class="flex flex-wrap items-center justify-between gap-2">
            <flux:button type="button" variant="ghost" size="sm" wire:click="$toggle('filtersOpen')">
                {{ $filtersOpen ? __('Hide') : __('Show') }}
            </flux:button>
        </div>
        @if ($filtersOpen)
            <div class="flex flex-col gap-4">
                <flux:input
                    wire:model.live.debounce.400ms="filterSearch"
                    :label="__('Search (PIN, SAS)')"
                />
                <flux:select wire:model.live="filterStatus" :label="__('Filter by PIN status')" class="max-w-md">
                    <option value="">{{ __('All statuses') }}</option>
                    @foreach (\App\Enums\DeliveryNumberStatus::cases() as $case)
                        <option value="{{ $case->value }}">{{ $this->pinStatusLabel($case) }}</option>
                    @endforeach
                </flux:select>
            </div>
        @endif
    </x-admin.filter-bar>

    <flux:card>
        <flux:heading size="lg" class="mb-4">{{ __('Bulk import (CSV)') }}</flux:heading>
        <flux:text class="mb-4 text-sm text-zinc-600 dark:text-zinc-400">
            {{ __('First row: headers') }} <code class="rounded bg-zinc-100 px-1 dark:bg-zinc-700">pin_code</code> {{ __('or') }} <code class="rounded bg-zinc-100 px-1 dark:bg-zinc-700">PIN Kodu</code>;
            {{ __('optional') }} <code class="rounded bg-zinc-100 px-1 dark:bg-zinc-700">sas_no</code> / <code class="rounded bg-zinc-100 px-1 dark:bg-zinc-700">SAS</code>.
        </flux:text>
        @if ($canWritePins)
            <div class="flex max-w-xl flex-col gap-4">
                <flux:field :label="__('CSV file')">
                    <input
                        type="file"
                        wire:model="pinImportFile"
                        accept=".csv,text/csv,text/plain"
                        class="block w-full text-sm text-zinc-600 file:me-4 file:rounded-lg file:border-0 file:bg-zinc-100 file:px-4 file:py-2 file:text-sm file:font-medium file:text-zinc-900 dark:text-zinc-400 dark:file:bg-zinc-700 dark:file:text-zinc-100"
                    />
                </flux:field>
                <flux:button type="button" variant="filled" wire:click="importPinsCsv" wire:loading.attr="disabled">
                    {{ __('Import CSV') }}
                </flux:button>
            </div>
        @endif
    </flux:card>

    @if ($canWritePins)
        <div class="grid gap-6 lg:grid-cols-2">
            <flux:card>
                <flux:heading size="lg" class="mb-4">{{ __('Add PIN') }}</flux:heading>
                <form wire:submit="addPin" class="flex flex-col gap-4">
                    <flux:field :label="__('PIN code')">
                        <flux:input wire:model="pin_code" required maxlength="64" />
                    </flux:field>
                    <flux:field :label="__('SAS no. (optional)')">
                        <flux:input wire:model="sas_no" maxlength="64" />
                    </flux:field>
                    <flux:button type="submit" variant="primary">{{ __('Save PIN') }}</flux:button>
                </form>
            </flux:card>

            <flux:card>
                <flux:heading size="lg" class="mb-4">{{ __('Assign PIN to order') }}</flux:heading>
                <form wire:submit="assignPinToOrder" class="flex flex-col gap-4">
                    <flux:field :label="__('PIN code')">
                        <flux:input wire:model="assign_pin_code" required maxlength="64" />
                    </flux:field>
                    <flux:select wire:model="assign_order_id" :label="__('Order')" required>
                        <option value="">{{ __('Select…') }}</option>
                        @foreach ($this->orderOptions() as $o)
                            <option value="{{ $o->id }}">{{ $o->order_number }} — {{ $o->customer?->legal_name ?? '—' }}</option>
                        @endforeach
                    </flux:select>
                    <flux:button type="submit" variant="filled">{{ __('Assign') }}</flux:button>
                </form>
            </flux:card>
        </div>
    @endif

    @if ($canWritePins)
        @if (count($selectedIds) > 0)
            <div class="flex flex-wrap items-center gap-4 rounded-lg border border-zinc-200 bg-zinc-50 p-4 dark:border-zinc-600 dark:bg-zinc-900">
                <flux:text>{{ __(':count selected', ['count' => count($selectedIds)]) }}</flux:text>
                <flux:button
                    type="button"
                    variant="danger"
                    wire:click="bulkDeleteSelected"
                    wire:confirm="{{ __('Delete selected available PINs only; assigned/used rows are skipped.') }}"
                >
                    {{ __('Delete selected') }}
                </flux:button>
            </div>
        @endif
    @endif

    <flux:card>
        <flux:heading size="lg" class="mb-4">{{ __('Recent PINs') }}</flux:heading>
        <flux:table>
            <flux:table.columns>
                @if ($canWritePins)
                    <flux:table.column class="w-12">
                        <span class="sr-only">{{ __('Select page') }}</span>
                        <input
                            type="checkbox"
                            class="size-4 rounded border-zinc-300 text-primary focus:ring-primary dark:border-zinc-600"
                            @checked($this->isPageFullySelected())
                            wire:click.prevent="toggleSelectPage"
                            wire:key="select-page-pins"
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
                    <button type="button" wire:click="sortBy('pin_code')" class="flex items-center gap-1 font-medium text-zinc-800 dark:text-white">
                        {{ __('PIN') }}
                        @if ($sortColumn === 'pin_code')
                            <span class="text-xs">{{ $sortDirection === 'asc' ? '↑' : '↓' }}</span>
                        @endif
                    </button>
                </flux:table.column>
                <flux:table.column>
                    <button type="button" wire:click="sortBy('sas_no')" class="flex items-center gap-1 font-medium text-zinc-800 dark:text-white">
                        {{ __('SAS') }}
                        @if ($sortColumn === 'sas_no')
                            <span class="text-xs">{{ $sortDirection === 'asc' ? '↑' : '↓' }}</span>
                        @endif
                    </button>
                </flux:table.column>
                <flux:table.column>
                    <button type="button" wire:click="sortBy('status')" class="flex items-center gap-1 font-medium text-zinc-800 dark:text-white">
                        {{ __('Status') }}
                        @if ($sortColumn === 'status')
                            <span class="text-xs">{{ $sortDirection === 'asc' ? '↑' : '↓' }}</span>
                        @endif
                    </button>
                </flux:table.column>
                <flux:table.column>{{ __('Order') }}</flux:table.column>
                <flux:table.column></flux:table.column>
            </flux:table.columns>
            <flux:table.rows>
                @forelse ($this->paginatedPins as $row)
                    <flux:table.row :key="$row->id">
                        @if ($canWritePins)
                            <flux:table.cell>
                                <flux:checkbox
                                    wire:key="pin-select-{{ $row->id }}"
                                    wire:model.live="selectedIds"
                                    :value="(int) $row->id"
                                />
                            </flux:table.cell>
                        @endif
                        <flux:table.cell>{{ $row->id }}</flux:table.cell>
                        <flux:table.cell>{{ $row->pin_code }}</flux:table.cell>
                        <flux:table.cell>{{ $row->sas_no ?? '—' }}</flux:table.cell>
                        <flux:table.cell>{{ $this->pinStatusLabel($row->status) }}</flux:table.cell>
                        <flux:table.cell>{{ $row->order?->order_number ?? '—' }}</flux:table.cell>
                        <flux:table.cell>
                            @if ($canWritePins)
                                @if ($row->status === \App\Enums\DeliveryNumberStatus::Available)
                                    <flux:button type="button" size="sm" variant="ghost" wire:click="deleteAvailable({{ $row->id }})" wire:confirm="{{ __('Delete this PIN?') }}">
                                        {{ __('Delete') }}
                                    </flux:button>
                                @endif
                            @endif
                        </flux:table.cell>
                    </flux:table.row>
                @empty
                    <flux:table.row>
                        <flux:table.cell colspan="{{ $canWritePins ? 7 : 6 }}">{{ __('No PINs yet.') }}</flux:table.cell>
                    </flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>
        <div class="mt-4">
            {{ $this->paginatedPins->links() }}
        </div>
    </flux:card>
</div>
