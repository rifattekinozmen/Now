<?php

use App\Authorization\LogisticsPermission;
use App\Livewire\Concerns\RequiresLogisticsAdmin;
use App\Models\Employee;
use App\Services\Logistics\ExcelImportService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\WithFileUploads;
use Livewire\WithPagination;

new #[Title('Employees')] class extends Component
{
    use RequiresLogisticsAdmin;
    use WithFileUploads;
    use WithPagination;

    public $importFile = null;

    public ?int $editingEmployeeId = null;

    public string $first_name = '';

    public string $last_name = '';

    public string $national_id = '';

    public string $blood_group = '';

    public bool $is_driver = false;

    public string $license_class = '';

    public ?string $license_valid_until = null;

    public ?string $src_valid_until = null;

    public ?string $psychotechnical_valid_until = null;

    public string $phone = '';

    public string $email = '';

    public string $filterSearch = '';

    public string $sortColumn = 'id';

    public string $sortDirection = 'desc';

    public bool $filtersOpen = false;

    /** @var list<int|string> */
    public array $selectedIds = [];

    public function mount(): void
    {
        Gate::authorize('viewAny', Employee::class);
    }

    public function updatedFilterSearch(): void
    {
        $this->resetPage();
        $this->selectedIds = [];
    }

    public function updatedPage(): void
    {
        $this->selectedIds = [];
    }

    /**
     * @return array{total: int, drivers: int, docs_soon: int, with_national_id: int}
     */
    #[Computed]
    public function employeeIndexStats(): array
    {
        $until = now()->addDays(30)->toDateString();
        $today = now()->toDateString();

        $row = Employee::query()
            ->toBase()
            ->selectRaw(
                'COUNT(*) as total, '.
                'SUM(CASE WHEN is_driver = 1 THEN 1 ELSE 0 END) as drivers, '.
                'SUM(CASE WHEN ('.
                '(license_valid_until IS NOT NULL AND license_valid_until <= ? AND license_valid_until >= ?) OR '.
                '(src_valid_until IS NOT NULL AND src_valid_until <= ? AND src_valid_until >= ?) OR '.
                '(psychotechnical_valid_until IS NOT NULL AND psychotechnical_valid_until <= ? AND psychotechnical_valid_until >= ?)'.
                ') THEN 1 ELSE 0 END) as docs_soon, '.
                'SUM(CASE WHEN national_id IS NOT NULL AND national_id != ? THEN 1 ELSE 0 END) as with_national_id',
                [$until, $today, $until, $today, $until, $today, '']
            )
            ->first();

        return [
            'total' => (int) ($row->total ?? 0),
            'drivers' => (int) ($row->drivers ?? 0),
            'docs_soon' => (int) ($row->docs_soon ?? 0),
            'with_national_id' => (int) ($row->with_national_id ?? 0),
        ];
    }

    /**
     * @return Builder<Employee>
     */
    private function employeesQuery(): Builder
    {
        $q = Employee::query();

        if ($this->filterSearch !== '') {
            $term = '%'.addcslashes($this->filterSearch, '%_\\').'%';
            $q->where(function (Builder $qq) use ($term): void {
                $qq->where('first_name', 'like', $term)
                    ->orWhere('last_name', 'like', $term)
                    ->orWhere('national_id', 'like', $term)
                    ->orWhere('phone', 'like', $term)
                    ->orWhere('email', 'like', $term);
            });
        }

        $allowed = ['id', 'first_name', 'last_name', 'is_driver', 'national_id', 'created_at'];
        $column = in_array($this->sortColumn, $allowed, true) ? $this->sortColumn : 'id';
        $direction = $this->sortDirection === 'asc' ? 'asc' : 'desc';

        return $q->orderBy($column, $direction);
    }

    #[Computed]
    public function paginatedEmployees(): LengthAwarePaginator
    {
        return $this->employeesQuery()->paginate(15);
    }

    public function sortBy(string $column): void
    {
        $allowed = ['id', 'first_name', 'last_name', 'is_driver', 'national_id', 'created_at'];
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
        $pageIds = $this->paginatedEmployees->pluck('id')->map(fn ($id) => (int) $id)->all();
        if ($pageIds === []) {
            return false;
        }

        $selected = array_map('intval', $this->selectedIds);

        return count(array_diff($pageIds, $selected)) === 0;
    }

    public function toggleSelectPage(): void
    {
        $pageIds = $this->paginatedEmployees->pluck('id')->map(fn ($id) => (int) $id)->all();
        $selected = array_map('intval', $this->selectedIds);
        $allSelected = $pageIds !== [] && count(array_diff($pageIds, $selected)) === 0;

        if ($allSelected) {
            $this->selectedIds = array_values(array_diff($selected, $pageIds));
        } else {
            $this->selectedIds = array_values(array_unique(array_merge($selected, $pageIds)));
        }
    }

    public function bulkDeleteSelected(): void
    {
        $this->ensureLogisticsWrite(LogisticsPermission::EMPLOYEES_WRITE);

        if ($this->selectedIds === []) {
            return;
        }

        $ids = array_map('intval', $this->selectedIds);
        $employees = Employee::query()->whereIn('id', $ids)->get();
        $deleted = 0;

        foreach ($employees as $employee) {
            Gate::authorize('delete', $employee);
            $employee->delete();
            $deleted++;
        }

        $this->selectedIds = [];
        $this->resetPage();

        session()->flash('bulk_deleted', $deleted);
    }

    public function saveEmployee(): void
    {
        $this->ensureLogisticsWrite(LogisticsPermission::EMPLOYEES_WRITE);

        Gate::authorize('create', Employee::class);

        $validated = $this->validate([
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'national_id' => ['nullable', 'string', 'size:11', 'regex:/^[0-9]+$/'],
            'blood_group' => ['nullable', 'string', 'max:8'],
            'is_driver' => ['boolean'],
            'license_class' => ['nullable', 'string', 'max:16'],
            'license_valid_until' => ['nullable', 'date'],
            'src_valid_until' => ['nullable', 'date'],
            'psychotechnical_valid_until' => ['nullable', 'date'],
            'phone' => ['nullable', 'string', 'max:32'],
            'email' => ['nullable', 'email', 'max:255'],
        ]);

        $nid = $validated['national_id'] !== '' ? $validated['national_id'] : null;

        Employee::query()->create([
            'first_name' => $validated['first_name'],
            'last_name' => $validated['last_name'],
            'national_id' => $nid,
            'blood_group' => $validated['blood_group'] ?: null,
            'is_driver' => $validated['is_driver'],
            'license_class' => $validated['license_class'] ?: null,
            'license_valid_until' => $validated['license_valid_until'],
            'src_valid_until' => $validated['src_valid_until'],
            'psychotechnical_valid_until' => $validated['psychotechnical_valid_until'],
            'phone' => $validated['phone'] ?: null,
            'email' => $validated['email'] ?: null,
        ]);

        $this->reset(
            'first_name',
            'last_name',
            'national_id',
            'blood_group',
            'is_driver',
            'license_class',
            'license_valid_until',
            'src_valid_until',
            'psychotechnical_valid_until',
            'phone',
            'email',
        );
    }

    public function startEditEmployee(int $employeeId): void
    {
        $this->ensureLogisticsWrite(LogisticsPermission::EMPLOYEES_WRITE);

        $employee = Employee::query()->findOrFail($employeeId);
        Gate::authorize('update', $employee);

        $this->editingEmployeeId = $employee->id;
        $this->first_name = $employee->first_name;
        $this->last_name = $employee->last_name;
        $this->national_id = $employee->national_id ?? '';
        $this->blood_group = $employee->blood_group ?? '';
        $this->is_driver = $employee->is_driver;
        $this->license_class = $employee->license_class ?? '';
        $this->license_valid_until = $employee->license_valid_until?->format('Y-m-d');
        $this->src_valid_until = $employee->src_valid_until?->format('Y-m-d');
        $this->psychotechnical_valid_until = $employee->psychotechnical_valid_until?->format('Y-m-d');
        $this->phone = $employee->phone ?? '';
        $this->email = $employee->email ?? '';
    }

    public function cancelEmployeeEdit(): void
    {
        $this->editingEmployeeId = null;
        $this->reset(
            'first_name',
            'last_name',
            'national_id',
            'blood_group',
            'is_driver',
            'license_class',
            'license_valid_until',
            'src_valid_until',
            'psychotechnical_valid_until',
            'phone',
            'email',
        );
    }

    public function updateEmployee(): void
    {
        $this->ensureLogisticsWrite(LogisticsPermission::EMPLOYEES_WRITE);

        if ($this->editingEmployeeId === null) {
            return;
        }

        $employee = Employee::query()->findOrFail($this->editingEmployeeId);
        Gate::authorize('update', $employee);

        $tenantId = auth()->user()?->tenant_id;
        if ($tenantId === null) {
            abort(403);
        }

        $nationalRules = ['nullable', 'string'];
        if ($this->national_id !== '') {
            $nationalRules[] = 'size:11';
            $nationalRules[] = 'regex:/^[0-9]+$/';
            $nationalRules[] = Rule::unique('employees', 'national_id')->ignore($employee->id)->where('tenant_id', $tenantId);
        }

        $validated = $this->validate([
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'national_id' => $nationalRules,
            'blood_group' => ['nullable', 'string', 'max:8'],
            'is_driver' => ['boolean'],
            'license_class' => ['nullable', 'string', 'max:16'],
            'license_valid_until' => ['nullable', 'date'],
            'src_valid_until' => ['nullable', 'date'],
            'psychotechnical_valid_until' => ['nullable', 'date'],
            'phone' => ['nullable', 'string', 'max:32'],
            'email' => ['nullable', 'email', 'max:255'],
        ]);

        $nid = $validated['national_id'] !== '' ? $validated['national_id'] : null;

        $employee->update([
            'first_name' => $validated['first_name'],
            'last_name' => $validated['last_name'],
            'national_id' => $nid,
            'blood_group' => $validated['blood_group'] ?: null,
            'is_driver' => $validated['is_driver'],
            'license_class' => $validated['license_class'] ?: null,
            'license_valid_until' => $validated['license_valid_until'],
            'src_valid_until' => $validated['src_valid_until'],
            'psychotechnical_valid_until' => $validated['psychotechnical_valid_until'],
            'phone' => $validated['phone'] ?: null,
            'email' => $validated['email'] ?: null,
        ]);

        $this->cancelEmployeeEdit();
    }

    public function importEmployees(ExcelImportService $excelImport): void
    {
        $this->ensureLogisticsWrite(LogisticsPermission::EMPLOYEES_WRITE);

        $tenantId = auth()->user()?->tenant_id;
        if ($tenantId === null) {
            abort(403);
        }

        $this->validate([
            'importFile' => ['required', 'file', 'mimes:xlsx,xls,csv', 'max:10240'],
        ]);

        $stored = $this->importFile->store('imports', 'local');
        $path = Storage::disk('local')->path($stored);
        $result = $excelImport->importEmployeesFromPath($path, (int) $tenantId);
        Storage::disk('local')->delete($stored);

        session()->flash('import_created', $result['created']);
        session()->flash('import_errors', $result['errors']);
        $this->reset('importFile');
        $this->resetPage();
    }
}; ?>

<div class="mx-auto flex w-full max-w-6xl flex-col gap-6 p-4 lg:p-8">
    @php
        $authUser = auth()->user();
        $canWriteEmployees =
            $authUser instanceof \App\Models\User
            && \App\Authorization\LogisticsPermission::canWrite($authUser, \App\Authorization\LogisticsPermission::EMPLOYEES_WRITE);
    @endphp
    <flux:heading size="xl">{{ __('Employees') }}</flux:heading>

    @if (session()->has('bulk_deleted'))
        <flux:callout variant="success" icon="check-circle">
            {{ __('Deleted :count records.', ['count' => session('bulk_deleted')]) }}
        </flux:callout>
    @endif

    @if (session()->has('import_created'))
        <flux:callout variant="success" icon="check-circle">
            {{ __('Imported rows: :count', ['count' => session('import_created')]) }}
        </flux:callout>
    @endif

    @if (session()->has('import_errors') && count(session('import_errors')) > 0)
        <flux:callout variant="warning" icon="exclamation-triangle">
            <flux:heading size="sm" class="mb-2">{{ __('Import errors') }}</flux:heading>
            <ul class="list-inside list-disc text-sm">
                @foreach (session('import_errors') as $err)
                    <li>{{ __('Row :row: :message', ['row' => $err['row'], 'message' => $err['message']]) }}</li>
                @endforeach
            </ul>
        </flux:callout>
    @endif

    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <flux:card class="!p-4">
            <flux:text class="text-sm text-zinc-500 dark:text-zinc-400">{{ __('Total employees') }}</flux:text>
            <flux:heading size="xl">{{ $this->employeeIndexStats['total'] }}</flux:heading>
        </flux:card>
        <flux:card class="!p-4">
            <flux:text class="text-sm text-zinc-500 dark:text-zinc-400">{{ __('Drivers') }}</flux:text>
            <flux:heading size="xl">{{ $this->employeeIndexStats['drivers'] }}</flux:heading>
        </flux:card>
        <flux:card class="!p-4">
            <flux:text class="text-sm text-zinc-500 dark:text-zinc-400">{{ __('Docs expiring (30d)') }}</flux:text>
            <flux:heading size="xl">{{ $this->employeeIndexStats['docs_soon'] }}</flux:heading>
        </flux:card>
        <flux:card class="!p-4">
            <flux:text class="text-sm text-zinc-500 dark:text-zinc-400">{{ __('With national ID') }}</flux:text>
            <flux:heading size="xl">{{ $this->employeeIndexStats['with_national_id'] }}</flux:heading>
        </flux:card>
    </div>

    @if ($canWriteEmployees)
        @if ($editingEmployeeId !== null)
            <flux:card>
                <flux:heading size="lg" class="mb-4">{{ __('Edit employee') }}</flux:heading>
                <form wire:submit="updateEmployee" class="flex max-w-2xl flex-col gap-4">
                    <div class="grid gap-4 sm:grid-cols-2">
                        <flux:input wire:model="first_name" :label="__('First name')" required />
                        <flux:input wire:model="last_name" :label="__('Last name')" required />
                    </div>
                    <div class="grid gap-4 sm:grid-cols-2">
                        <flux:input wire:model="national_id" :label="__('National ID')" maxlength="11" />
                        <flux:input wire:model="blood_group" :label="__('Blood group')" />
                    </div>
                    <flux:checkbox wire:model="is_driver" :label="__('Driver')" />
                    <div class="grid gap-4 sm:grid-cols-2">
                        <flux:input wire:model="license_class" :label="__('License class')" />
                        <flux:input wire:model="license_valid_until" type="date" :label="__('License valid until')" />
                    </div>
                    <div class="grid gap-4 sm:grid-cols-2">
                        <flux:input wire:model="src_valid_until" type="date" :label="__('SRC valid until')" />
                        <flux:input wire:model="psychotechnical_valid_until" type="date" :label="__('Psychotechnical valid until')" />
                    </div>
                    <div class="grid gap-4 sm:grid-cols-2">
                        <flux:input wire:model="phone" :label="__('Phone')" />
                        <flux:input wire:model="email" type="email" :label="__('Email')" />
                    </div>
                    <div class="flex flex-wrap gap-2">
                        <flux:button type="submit" variant="primary">{{ __('Save changes') }}</flux:button>
                        <flux:button type="button" variant="ghost" wire:click="cancelEmployeeEdit">{{ __('Cancel') }}</flux:button>
                    </div>
                </form>
            </flux:card>
        @else
            <flux:card>
                <flux:heading size="lg" class="mb-4">{{ __('New employee') }}</flux:heading>
                <form wire:submit="saveEmployee" class="flex max-w-2xl flex-col gap-4">
                    <div class="grid gap-4 sm:grid-cols-2">
                        <flux:input wire:model="first_name" :label="__('First name')" required />
                        <flux:input wire:model="last_name" :label="__('Last name')" required />
                    </div>
                    <div class="grid gap-4 sm:grid-cols-2">
                        <flux:input wire:model="national_id" :label="__('National ID')" maxlength="11" />
                        <flux:input wire:model="blood_group" :label="__('Blood group')" />
                    </div>
                    <flux:checkbox wire:model="is_driver" :label="__('Driver')" />
                    <div class="grid gap-4 sm:grid-cols-2">
                        <flux:input wire:model="license_class" :label="__('License class')" />
                        <flux:input wire:model="license_valid_until" type="date" :label="__('License valid until')" />
                    </div>
                    <div class="grid gap-4 sm:grid-cols-2">
                        <flux:input wire:model="src_valid_until" type="date" :label="__('SRC valid until')" />
                        <flux:input wire:model="psychotechnical_valid_until" type="date" :label="__('Psychotechnical valid until')" />
                    </div>
                    <div class="grid gap-4 sm:grid-cols-2">
                        <flux:input wire:model="phone" :label="__('Phone')" />
                        <flux:input wire:model="email" type="email" :label="__('Email')" />
                    </div>
                    <flux:button type="submit" variant="primary">{{ __('Save') }}</flux:button>
                </form>
            </flux:card>
        @endif

        <flux:card>
            <flux:heading size="lg" class="mb-4">{{ __('Import employees (CSV / Excel)') }}</flux:heading>
            <flux:text class="mb-3 text-sm text-zinc-600 dark:text-zinc-400">
                {{ __('Headers: Ad, Soyad, T.C., Kan, Telefon') }}
            </flux:text>
            <div class="flex max-w-xl flex-col gap-3">
                <flux:input wire:model="importFile" type="file" accept=".xlsx,.xls,.csv" />
                <flux:button type="button" wire:click="importEmployees" variant="ghost">{{ __('Import') }}</flux:button>
            </div>
        </flux:card>
    @endif

    <div class="flex flex-col gap-3">
        <div class="flex flex-wrap items-center justify-between gap-2">
            <flux:heading size="lg">{{ __('Advanced filters') }}</flux:heading>
            <flux:button type="button" variant="ghost" size="sm" wire:click="$toggle('filtersOpen')">
                {{ $filtersOpen ? __('Hide') : __('Show') }}
            </flux:button>
        </div>
        @if ($filtersOpen)
            <flux:card class="!p-4">
                <flux:input
                    wire:model.live.debounce.400ms="filterSearch"
                    :label="__('Search (name, national ID, phone)')"
                />
            </flux:card>
        @endif
    </div>

    @if ($canWriteEmployees && count($selectedIds) > 0)
        <div class="flex flex-wrap items-center gap-4 rounded-lg border border-zinc-200 bg-zinc-50 p-4 dark:border-zinc-600 dark:bg-zinc-900">
            <flux:text>{{ __(':count selected', ['count' => count($selectedIds)]) }}</flux:text>
            <flux:button
                type="button"
                variant="danger"
                wire:click="bulkDeleteSelected"
                wire:confirm="{{ __('Delete selected employees?') }}"
            >
                {{ __('Delete selected') }}
            </flux:button>
        </div>
    @endif

    <flux:card>
        <flux:heading size="lg" class="mb-4">{{ __('Personnel list') }}</flux:heading>
        <flux:table>
            <flux:table.columns>
                @if ($canWriteEmployees)
                    <flux:table.column class="w-12">
                        <span class="sr-only">{{ __('Select page') }}</span>
                        <input
                            type="checkbox"
                            class="size-4 rounded border-zinc-300 text-primary focus:ring-primary dark:border-zinc-600"
                            @checked($this->isPageFullySelected())
                            wire:click="toggleSelectPage"
                            wire:key="select-page-employees"
                        />
                    </flux:table.column>
                    <flux:table.column>{{ __('Actions') }}</flux:table.column>
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
                    <button type="button" wire:click="sortBy('first_name')" class="flex items-center gap-1 font-medium text-zinc-800 dark:text-white">
                        {{ __('First name') }}
                        @if ($sortColumn === 'first_name')
                            <span class="text-xs">{{ $sortDirection === 'asc' ? '↑' : '↓' }}</span>
                        @endif
                    </button>
                </flux:table.column>
                <flux:table.column>
                    <button type="button" wire:click="sortBy('last_name')" class="flex items-center gap-1 font-medium text-zinc-800 dark:text-white">
                        {{ __('Last name') }}
                        @if ($sortColumn === 'last_name')
                            <span class="text-xs">{{ $sortDirection === 'asc' ? '↑' : '↓' }}</span>
                        @endif
                    </button>
                </flux:table.column>
                <flux:table.column>
                    <button type="button" wire:click="sortBy('national_id')" class="flex items-center gap-1 font-medium text-zinc-800 dark:text-white">
                        {{ __('National ID') }}
                        @if ($sortColumn === 'national_id')
                            <span class="text-xs">{{ $sortDirection === 'asc' ? '↑' : '↓' }}</span>
                        @endif
                    </button>
                </flux:table.column>
                <flux:table.column>
                    <button type="button" wire:click="sortBy('is_driver')" class="flex items-center gap-1 font-medium text-zinc-800 dark:text-white">
                        {{ __('Driver') }}
                        @if ($sortColumn === 'is_driver')
                            <span class="text-xs">{{ $sortDirection === 'asc' ? '↑' : '↓' }}</span>
                        @endif
                    </button>
                </flux:table.column>
            </flux:table.columns>
            <flux:table.rows>
                @forelse ($this->paginatedEmployees as $employee)
                    <flux:table.row :key="$employee->id">
                        @if ($canWriteEmployees)
                            <flux:table.cell>
                                <flux:checkbox wire:model.live="selectedIds" value="{{ $employee->id }}" />
                            </flux:table.cell>
                            <flux:table.cell>
                                <flux:button type="button" size="sm" variant="ghost" wire:click="startEditEmployee({{ $employee->id }})">
                                    {{ __('Edit') }}
                                </flux:button>
                            </flux:table.cell>
                        @endif
                        <flux:table.cell>{{ $employee->id }}</flux:table.cell>
                        <flux:table.cell>{{ $employee->first_name }}</flux:table.cell>
                        <flux:table.cell>{{ $employee->last_name }}</flux:table.cell>
                        <flux:table.cell>{{ $employee->national_id ?? '—' }}</flux:table.cell>
                        <flux:table.cell>{{ $employee->is_driver ? __('Yes') : __('No') }}</flux:table.cell>
                    </flux:table.row>
                @empty
                    <flux:table.row>
                        <flux:table.cell colspan="{{ $canWriteEmployees ? 8 : 5 }}">{{ __('No employees yet.') }}</flux:table.cell>
                    </flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>
        <div class="mt-4">
            {{ $this->paginatedEmployees->links() }}
        </div>
    </flux:card>
</div>
