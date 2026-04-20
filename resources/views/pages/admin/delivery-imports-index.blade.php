<?php

use App\Enums\DeliveryImportStatus;
use App\Models\DeliveryImport;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithFileUploads;
use Livewire\WithPagination;

new #[Lazy, Title('Delivery Imports')] class extends Component
{
    use WithFileUploads;
    use WithPagination;

    // Filters
    public string $filterStatus = '';
    public string $filterSource = '';
    public string $filterFrom   = '';
    public string $filterTo     = '';

    public string $sortColumn    = 'import_date';
    public string $sortDirection = 'desc';

    public bool $filtersOpen = false;

    /** @var int[] */
    public array $selectedIds = [];

    public ?int $confirmingDeleteId = null;

    // Upload form
    public bool $showUploadForm = false;
    public string $reference_no   = '';
    public string $import_date    = '';
    public string $source         = 'excel';
    public string $notes          = '';
    public $uploadFile = null;

    // Analysis modal
    public ?int $analyzingId = null;

    public function mount(): void
    {
        Gate::authorize('viewAny', DeliveryImport::class);
        $this->import_date = now()->format('Y-m-d');
    }

    public function updatedFilterStatus(): void { $this->resetPage(); }
    public function updatedFilterSource(): void { $this->resetPage(); }
    public function updatedFilterFrom(): void { $this->resetPage(); }
    public function updatedFilterTo(): void { $this->resetPage(); }

    public function sortBy(string $column): void
    {
        $allowed = ['import_date', 'row_count', 'matched_count', 'status', 'created_at'];
        if (! in_array($column, $allowed, true)) {
            return;
        }
        if ($this->sortColumn === $column) {
            $this->sortDirection = $this->sortDirection === 'asc' ? 'desc' : 'asc';
        } else {
            $this->sortColumn    = $column;
            $this->sortDirection = 'asc';
        }
        $this->resetPage();
    }

    public function toggleSelectPage(): void
    {
        $pageIds = $this->paginatedImports->pluck('id')->map(fn ($id) => (int) $id)->toArray();
        if ($this->isPageFullySelected()) {
            $this->selectedIds = array_values(array_diff($this->selectedIds, $pageIds));
        } else {
            $this->selectedIds = array_values(array_unique(array_merge($this->selectedIds, $pageIds)));
        }
    }

    public function isPageFullySelected(): bool
    {
        $pageIds = $this->paginatedImports->pluck('id')->map(fn ($id) => (int) $id)->toArray();

        return count($pageIds) > 0 && count(array_diff($pageIds, $this->selectedIds)) === 0;
    }

    public function bulkDeleteSelected(): void
    {
        Gate::authorize('create', DeliveryImport::class);
        $count             = DeliveryImport::query()->whereIn('id', $this->selectedIds)->delete();
        $this->selectedIds = [];
        session()->flash('bulk_deleted', __('Deleted :count records.', ['count' => $count]));
        $this->resetPage();
    }

    public function confirmDelete(int $id): void
    {
        $this->confirmingDeleteId = $id;
        $this->modal('confirm-delete')->show();
    }

    public function executeDelete(): void
    {
        if ($this->confirmingDeleteId) {
            $record = DeliveryImport::query()->findOrFail($this->confirmingDeleteId);
            Gate::authorize('delete', $record);
            $record->delete();
        }
        $this->confirmingDeleteId = null;
        $this->resetPage();
    }

    /**
     * @return array{total:int, processed:int, total_matched:int, total_unmatched:int}
     */
    #[Computed]
    public function kpiStats(): array
    {
        return [
            'total'            => DeliveryImport::query()->count(),
            'processed'        => DeliveryImport::query()->where('status', DeliveryImportStatus::Processed->value)->count(),
            'total_matched'    => (int) DeliveryImport::query()->sum('matched_count'),
            'total_unmatched'  => (int) DeliveryImport::query()->sum('unmatched_count'),
        ];
    }

    private function importQuery(): Builder
    {
        $q = DeliveryImport::query();

        if ($this->filterStatus !== '') {
            $q->where('status', $this->filterStatus);
        }
        if ($this->filterSource !== '') {
            $q->where('source', $this->filterSource);
        }
        if ($this->filterFrom !== '') {
            $q->where('import_date', '>=', $this->filterFrom);
        }
        if ($this->filterTo !== '') {
            $q->where('import_date', '<=', $this->filterTo);
        }

        return $q->orderBy($this->sortColumn, $this->sortDirection)->orderByDesc('id');
    }

    #[Computed]
    public function paginatedImports(): LengthAwarePaginator
    {
        return $this->importQuery()->paginate(20);
    }

    public function startUpload(): void
    {
        Gate::authorize('create', DeliveryImport::class);
        $this->resetUploadForm();
        $this->showUploadForm = true;
    }

    public function cancelUpload(): void
    {
        $this->showUploadForm = false;
        $this->resetUploadForm();
    }

    public function save(): void
    {
        Gate::authorize('create', DeliveryImport::class);

        $validated = $this->validate([
            'reference_no' => ['nullable', 'string', 'max:100'],
            'import_date'  => ['required', 'date'],
            'source'       => ['required', 'in:excel,csv,api'],
            'notes'        => ['nullable', 'string', 'max:2000'],
            'uploadFile'   => ['nullable', 'file', 'max:20480', 'mimes:xlsx,xls,csv'],
        ]);

        $filePath = null;
        if ($this->uploadFile) {
            $filePath = $this->uploadFile->store('delivery-imports', 'local');
        }

        DeliveryImport::query()->create([
            'reference_no'    => filled($validated['reference_no']) ? $validated['reference_no'] : null,
            'import_date'     => $validated['import_date'],
            'source'          => $validated['source'],
            'status'          => DeliveryImportStatus::Pending->value,
            'file_path'       => $filePath,
            'row_count'       => 0,
            'matched_count'   => 0,
            'unmatched_count' => 0,
            'imported_by'     => auth()->id(),
            'notes'           => filled($validated['notes']) ? $validated['notes'] : null,
        ]);

        $this->showUploadForm = false;
        $this->resetUploadForm();
        $this->resetPage();
        session()->flash('saved', __('Import record created.'));
    }

    public function markProcessed(int $id): void
    {
        $record = DeliveryImport::query()->findOrFail($id);
        Gate::authorize('delete', $record);
        $record->update(['status' => DeliveryImportStatus::Processed->value]);
    }

    public function showAnalysis(int $id): void
    {
        $this->analyzingId = $id;
        $this->modal('analysis')->show();
    }

    /**
     * @return DeliveryImport|null
     */
    #[Computed]
    public function analyzingRecord(): ?DeliveryImport
    {
        if (! $this->analyzingId) {
            return null;
        }

        return DeliveryImport::query()->find($this->analyzingId);
    }

    private function resetUploadForm(): void
    {
        $this->reference_no  = '';
        $this->import_date   = now()->format('Y-m-d');
        $this->source        = 'excel';
        $this->notes         = '';
        $this->uploadFile    = null;
    }
}; ?>

<div class="mx-auto flex w-full max-w-7xl flex-col gap-6 p-4 lg:p-8">

    <x-admin.page-header
        :heading="__('Delivery Imports')"
        :description="__('Track and manage delivery reconciliation imports.')"
    >
        <x-slot name="actions">
            @can('create', \App\Models\DeliveryImport::class)
                <flux:button type="button" variant="primary" wire:click="startUpload" icon="arrow-up-tray">
                    {{ __('New import') }}
                </flux:button>
            @endcan
        </x-slot>
    </x-admin.page-header>

    {{-- Flash --}}
    @if (session('bulk_deleted'))
        <flux:callout variant="success" icon="check-circle">
            <flux:callout.text>{{ session('bulk_deleted') }}</flux:callout.text>
        </flux:callout>
    @endif
    @if (session('saved'))
        <flux:callout variant="success" icon="check-circle">
            <flux:callout.text>{{ session('saved') }}</flux:callout.text>
        </flux:callout>
    @endif

    {{-- KPI Cards --}}
    <div class="grid gap-3 sm:grid-cols-4">
        <flux:card class="p-4">
            <flux:text class="text-sm text-zinc-500 dark:text-zinc-400">{{ __('Total Imports') }}</flux:text>
            <flux:heading size="lg">{{ $this->kpiStats['total'] }}</flux:heading>
        </flux:card>
        <flux:card class="p-4">
            <flux:text class="text-sm text-zinc-500 dark:text-zinc-400">{{ __('Processed') }}</flux:text>
            <flux:heading size="lg" class="text-green-600">{{ $this->kpiStats['processed'] }}</flux:heading>
        </flux:card>
        <flux:card class="p-4">
            <flux:text class="text-sm text-zinc-500 dark:text-zinc-400">{{ __('Total Matched') }}</flux:text>
            <flux:heading size="lg" class="text-blue-600">{{ number_format($this->kpiStats['total_matched']) }}</flux:heading>
        </flux:card>
        <flux:card class="p-4">
            <flux:text class="text-sm text-zinc-500 dark:text-zinc-400">{{ __('Total Unmatched') }}</flux:text>
            <flux:heading size="lg" class="{{ $this->kpiStats['total_unmatched'] > 0 ? 'text-red-500' : '' }}">
                {{ number_format($this->kpiStats['total_unmatched']) }}
            </flux:heading>
        </flux:card>
    </div>

    {{-- Filters --}}
    <flux:card class="p-4">
        <div class="flex flex-wrap items-center justify-end gap-2">
            <flux:button variant="ghost" wire:click="$toggle('filtersOpen')" icon="{{ $filtersOpen ? 'chevron-up' : 'chevron-down' }}">
                {{ __('Filters') }}
            </flux:button>
        </div>
        @if ($filtersOpen)
            <div class="mt-4 flex flex-wrap gap-4">
                <flux:select wire:model.live="filterStatus" :label="__('Status')" class="max-w-[180px]">
                    <option value="">{{ __('All statuses') }}</option>
                    @foreach (\App\Enums\DeliveryImportStatus::cases() as $s)
                        <option value="{{ $s->value }}">{{ $s->label() }}</option>
                    @endforeach
                </flux:select>
                <flux:select wire:model.live="filterSource" :label="__('Source')" class="max-w-[160px]">
                    <option value="">{{ __('All sources') }}</option>
                    <option value="excel">Excel</option>
                    <option value="csv">CSV</option>
                    <option value="api">API</option>
                </flux:select>
                <flux:input wire:model.live="filterFrom" type="date" :label="__('From')" class="max-w-[160px]" />
                <flux:input wire:model.live="filterTo" type="date" :label="__('To')" class="max-w-[160px]" />
            </div>
        @endif
    </flux:card>

    {{-- Upload Form --}}
    @if ($showUploadForm)
        <flux:card class="p-4">
            <flux:heading size="lg" class="mb-4">{{ __('New Import Record') }}</flux:heading>
            <form wire:submit="save" class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                <flux:input wire:model="reference_no" :label="__('Reference No')" placeholder="IMP-2024-001" />
                <flux:input wire:model="import_date" type="date" :label="__('Import Date')" required />
                <flux:select wire:model="source" :label="__('Source')">
                    <option value="excel">Excel</option>
                    <option value="csv">CSV</option>
                    <option value="api">API</option>
                </flux:select>
                <div class="sm:col-span-2 lg:col-span-3">
                    <flux:label>{{ __('File (optional)') }}</flux:label>
                    <input type="file" wire:model="uploadFile" accept=".xlsx,.xls,.csv"
                           class="mt-1 block w-full text-sm text-zinc-600 file:mr-3 file:rounded file:border-0 file:bg-zinc-100 file:px-3 file:py-1 file:text-sm file:font-medium hover:file:bg-zinc-200 dark:text-zinc-400 dark:file:bg-zinc-800 dark:hover:file:bg-zinc-700" />
                    @error('uploadFile') <flux:error>{{ $message }}</flux:error> @enderror
                </div>
                <flux:textarea wire:model="notes" :label="__('Notes')" rows="2" class="sm:col-span-2 lg:col-span-3" />
                <div class="flex flex-wrap gap-2 lg:col-span-3">
                    <flux:button type="submit" variant="primary">{{ __('Save') }}</flux:button>
                    <flux:button type="button" variant="ghost" wire:click="cancelUpload">{{ __('Cancel') }}</flux:button>
                </div>
            </form>
        </flux:card>
    @endif

    {{-- Bulk delete toolbar --}}
    @if (count($selectedIds) > 0)
        <div class="flex flex-wrap items-center gap-4 rounded-lg border border-zinc-200 bg-zinc-50 p-4 dark:border-zinc-600 dark:bg-zinc-900">
            <flux:text>{{ __(':count selected', ['count' => count($selectedIds)]) }}</flux:text>
            <flux:button type="button" variant="danger" wire:click="bulkDeleteSelected"
                wire:confirm="{{ __('Delete selected import records?') }}">
                {{ __('Delete selected') }}
            </flux:button>
        </div>
    @endif

    {{-- Table --}}
    <flux:card class="p-4">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-zinc-200 text-sm dark:divide-zinc-700">
                <thead>
                    <tr class="text-start text-zinc-500 dark:text-zinc-400">
                        <th class="w-12 py-2 pe-3">
                            <input type="checkbox"
                                   wire:click.prevent="toggleSelectPage"
                                   @checked($this->isPageFullySelected())
                                   class="rounded border-zinc-300" />
                        </th>
                        <th class="py-2 pe-3 font-medium">{{ __('Reference') }}</th>
                        <th class="py-2 pe-3 font-medium">
                            <button wire:click="sortBy('import_date')" class="flex items-center gap-1 hover:text-zinc-700 dark:hover:text-zinc-200">
                                {{ __('Import Date') }}@if ($sortColumn === 'import_date') <span class="text-xs">{{ $sortDirection === 'asc' ? '↑' : '↓' }}</span>@endif
                            </button>
                        </th>
                        <th class="py-2 pe-3 font-medium">{{ __('Source') }}</th>
                        <th class="py-2 pe-3 font-medium text-end">
                            <button wire:click="sortBy('row_count')" class="ms-auto flex items-center gap-1 hover:text-zinc-700 dark:hover:text-zinc-200">
                                {{ __('Rows') }}@if ($sortColumn === 'row_count') <span class="text-xs">{{ $sortDirection === 'asc' ? '↑' : '↓' }}</span>@endif
                            </button>
                        </th>
                        <th class="py-2 pe-3 font-medium text-end">
                            <button wire:click="sortBy('matched_count')" class="ms-auto flex items-center gap-1 hover:text-zinc-700 dark:hover:text-zinc-200">
                                {{ __('Matched') }}@if ($sortColumn === 'matched_count') <span class="text-xs">{{ $sortDirection === 'asc' ? '↑' : '↓' }}</span>@endif
                            </button>
                        </th>
                        <th class="py-2 pe-3 font-medium text-end">{{ __('Unmatched') }}</th>
                        <th class="py-2 pe-3 font-medium">
                            <button wire:click="sortBy('status')" class="flex items-center gap-1 hover:text-zinc-700 dark:hover:text-zinc-200">
                                {{ __('Status') }}@if ($sortColumn === 'status') <span class="text-xs">{{ $sortDirection === 'asc' ? '↑' : '↓' }}</span>@endif
                            </button>
                        </th>
                        <th class="py-2 text-end font-medium">{{ __('Actions') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-zinc-100 dark:divide-zinc-800">
                    @forelse ($this->paginatedImports as $import)
                        <tr>
                            <td class="py-2 pe-3">
                                <input type="checkbox" wire:model="selectedIds" :value="$import->id" class="rounded border-zinc-300" />
                            </td>
                            <td class="py-2 pe-3">
                                <span class="font-mono text-xs font-medium">{{ $import->reference_no ?? '—' }}</span>
                                @if ($import->notes)
                                    <span class="block max-w-[200px] truncate text-xs text-zinc-400">{{ $import->notes }}</span>
                                @endif
                            </td>
                            <td class="py-2 pe-3 whitespace-nowrap text-xs">{{ $import->import_date->format('d M Y') }}</td>
                            <td class="py-2 pe-3">
                                <flux:badge color="zinc" size="sm">{{ strtoupper($import->source) }}</flux:badge>
                            </td>
                            <td class="py-2 pe-3 text-end font-mono text-xs">{{ number_format($import->row_count) }}</td>
                            <td class="py-2 pe-3 text-end font-mono text-xs text-green-600">{{ number_format($import->matched_count) }}</td>
                            <td class="py-2 pe-3 text-end font-mono text-xs {{ $import->unmatched_count > 0 ? 'text-red-500' : 'text-zinc-400' }}">
                                {{ number_format($import->unmatched_count) }}
                            </td>
                            <td class="py-2 pe-3">
                                <flux:badge color="{{ $import->status->color() }}" size="sm">{{ $import->status->label() }}</flux:badge>
                            </td>
                            <td class="py-2 text-end">
                                <div class="flex justify-end gap-1">
                                    <flux:button size="sm" variant="ghost" wire:click="showAnalysis({{ $import->id }})">
                                        {{ __('Analyse') }}
                                    </flux:button>
                                    @can('delete', $import)
                                        @if ($import->status->value === 'pending')
                                            <flux:button size="sm" variant="primary" wire:click="markProcessed({{ $import->id }})">
                                                {{ __('Mark processed') }}
                                            </flux:button>
                                        @endif
                                        <flux:button size="sm" variant="danger" wire:click="confirmDelete({{ $import->id }})">
                                            {{ __('Delete') }}
                                        </flux:button>
                                    @endcan
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="9" class="py-8 text-center text-zinc-500">
                                {{ __('No import records yet.') }}
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="mt-4">{{ $this->paginatedImports->links() }}</div>
    </flux:card>

    {{-- Confirm Delete Modal --}}
    <flux:modal name="confirm-delete" class="min-w-[22rem]">
        <div class="space-y-6">
            <div>
                <flux:heading size="lg">{{ __('Delete import record?') }}</flux:heading>
                <flux:text class="mt-2">{{ __('This action cannot be undone.') }}</flux:text>
            </div>
            <div class="flex justify-end gap-2">
                <flux:modal.close>
                    <flux:button variant="ghost">{{ __('Cancel') }}</flux:button>
                </flux:modal.close>
                <flux:button variant="danger" wire:click="executeDelete">{{ __('Delete') }}</flux:button>
            </div>
        </div>
    </flux:modal>

    {{-- Analysis Modal --}}
    <flux:modal name="analysis" class="min-w-[32rem]">
        @if ($this->analyzingRecord)
            @php $rec = $this->analyzingRecord; @endphp
            <div class="space-y-4">
                <flux:heading size="lg">{{ __('Data Analysis Report') }}</flux:heading>
                <div class="grid grid-cols-2 gap-3">
                    <flux:card class="p-3">
                        <flux:text class="text-xs text-zinc-500">{{ __('Reference') }}</flux:text>
                        <flux:text class="font-mono font-medium">{{ $rec->reference_no ?? '—' }}</flux:text>
                    </flux:card>
                    <flux:card class="p-3">
                        <flux:text class="text-xs text-zinc-500">{{ __('Import Date') }}</flux:text>
                        <flux:text class="font-medium">{{ $rec->import_date->format('d M Y') }}</flux:text>
                    </flux:card>
                    <flux:card class="p-3">
                        <flux:text class="text-xs text-zinc-500">{{ __('Total Rows') }}</flux:text>
                        <flux:heading size="lg">{{ number_format($rec->row_count) }}</flux:heading>
                    </flux:card>
                    <flux:card class="p-3">
                        <flux:text class="text-xs text-zinc-500">{{ __('Status') }}</flux:text>
                        <flux:badge color="{{ $rec->status->color() }}">{{ $rec->status->label() }}</flux:badge>
                    </flux:card>
                </div>

                {{-- Match bar --}}
                @if ($rec->row_count > 0)
                    @php
                        $matchPct = round($rec->matched_count / $rec->row_count * 100);
                    @endphp
                    <div>
                        <div class="mb-1 flex justify-between text-xs text-zinc-500">
                            <span>{{ __('Match rate') }}</span>
                            <span>{{ $matchPct }}%</span>
                        </div>
                        <div class="h-3 w-full overflow-hidden rounded-full bg-zinc-200 dark:bg-zinc-700">
                            <div class="h-full rounded-full bg-green-500 transition-all" style="width: {{ $matchPct }}%"></div>
                        </div>
                        <div class="mt-2 flex justify-between text-xs">
                            <span class="text-green-600">{{ __('Matched: :n', ['n' => number_format($rec->matched_count)]) }}</span>
                            <span class="{{ $rec->unmatched_count > 0 ? 'text-red-500' : 'text-zinc-400' }}">
                                {{ __('Unmatched: :n', ['n' => number_format($rec->unmatched_count)]) }}
                            </span>
                        </div>
                    </div>
                @endif

                @if ($rec->notes)
                    <div class="rounded-lg bg-zinc-50 p-3 dark:bg-zinc-800">
                        <flux:text class="text-xs text-zinc-500">{{ __('Notes') }}</flux:text>
                        <flux:text class="mt-1 text-sm">{{ $rec->notes }}</flux:text>
                    </div>
                @endif

                <div class="flex justify-end">
                    <flux:modal.close>
                        <flux:button variant="ghost">{{ __('Close') }}</flux:button>
                    </flux:modal.close>
                </div>
            </div>
        @endif
    </flux:modal>

</div>
