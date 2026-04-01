<?php

use App\Authorization\LogisticsPermission;
use App\Enums\ExpenseType;
use App\Models\Employee;
use App\Models\TripExpense;
use App\Models\Vehicle;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

new #[Title('Trip Expenses')] class extends Component
{
    use WithPagination;

    public ?int $editingId = null;

    public ?int $vehicle_id = null;
    public ?int $employee_id = null;
    public ?int $shipment_id = null;
    public string $expense_type = 'fuel';
    public string $amount = '';
    public string $currency_code = 'TRY';
    public string $expense_date = '';
    public string $odometer_km = '';
    public string $description = '';

    public string $filterVehicle = '';
    public string $filterEmployee = '';
    public string $filterType = '';
    public string $filterDateFrom = '';
    public string $filterDateTo = '';

    public function mount(): void
    {
        Gate::authorize('viewAny', TripExpense::class);
        $this->expense_date = now()->toDateString();
    }

    public function updatedFilterVehicle(): void { $this->resetPage(); }
    public function updatedFilterEmployee(): void { $this->resetPage(); }
    public function updatedFilterType(): void { $this->resetPage(); }
    public function updatedFilterDateFrom(): void { $this->resetPage(); }
    public function updatedFilterDateTo(): void { $this->resetPage(); }

    /**
     * @return array{total_this_month: float, top_type: string, avg_per_vehicle: float, total_count: int}
     */
    #[Computed]
    public function kpiStats(): array
    {
        $thisMonth = TripExpense::query()
            ->whereYear('expense_date', now()->year)
            ->whereMonth('expense_date', now()->month)
            ->sum('amount');

        $topType = TripExpense::query()
            ->selectRaw('expense_type, SUM(amount) as total')
            ->groupBy('expense_type')
            ->orderByDesc('total')
            ->value('expense_type');

        $vehicleCount = Vehicle::query()->count();
        $totalAmount  = TripExpense::query()->sum('amount');

        return [
            'total_this_month' => (float) $thisMonth,
            'top_type'         => $topType ? ExpenseType::from($topType)->label() : '—',
            'avg_per_vehicle'  => $vehicleCount > 0 ? round($totalAmount / $vehicleCount, 2) : 0,
            'total_count'      => TripExpense::query()->count(),
        ];
    }

    /** @return \Illuminate\Database\Eloquent\Collection<int, Vehicle> */
    #[Computed]
    public function vehicles(): \Illuminate\Database\Eloquent\Collection
    {
        return Vehicle::query()->orderBy('plate')->get(['id', 'plate']);
    }

    /** @return \Illuminate\Database\Eloquent\Collection<int, Employee> */
    #[Computed]
    public function employees(): \Illuminate\Database\Eloquent\Collection
    {
        return Employee::query()->orderBy('name')->get(['id', 'name']);
    }

    private function expensesQuery(): Builder
    {
        $q = TripExpense::query()->with(['vehicle', 'employee']);

        if ($this->filterVehicle !== '') {
            $q->where('vehicle_id', $this->filterVehicle);
        }

        if ($this->filterEmployee !== '') {
            $q->where('employee_id', $this->filterEmployee);
        }

        if ($this->filterType !== '') {
            $q->where('expense_type', $this->filterType);
        }

        if ($this->filterDateFrom !== '') {
            $q->where('expense_date', '>=', $this->filterDateFrom);
        }

        if ($this->filterDateTo !== '') {
            $q->where('expense_date', '<=', $this->filterDateTo);
        }

        return $q->orderByDesc('expense_date')->orderByDesc('id');
    }

    #[Computed]
    public function paginatedExpenses(): LengthAwarePaginator
    {
        return $this->expensesQuery()->paginate(20);
    }

    public function startCreate(): void
    {
        Gate::authorize('create', TripExpense::class);
        $this->resetForm();
        $this->editingId = 0;
    }

    public function startEdit(int $id): void
    {
        $row = TripExpense::query()->findOrFail($id);
        Gate::authorize('update', $row);
        $this->editingId    = $row->id;
        $this->vehicle_id   = $row->vehicle_id;
        $this->employee_id  = $row->employee_id;
        $this->expense_type = $row->expense_type->value;
        $this->amount       = (string) $row->amount;
        $this->currency_code= $row->currency_code;
        $this->expense_date = $row->expense_date->toDateString();
        $this->odometer_km  = $row->odometer_km ? (string) $row->odometer_km : '';
        $this->description  = $row->description ?? '';
    }

    public function cancelForm(): void
    {
        $this->editingId = null;
        $this->resetForm();
    }

    public function save(): void
    {
        $authUser = auth()->user();
        if (! ($authUser instanceof \App\Models\User) || ! LogisticsPermission::canWrite($authUser, LogisticsPermission::TRIP_EXPENSES_WRITE)) {
            abort(403);
        }

        $validated = $this->validate([
            'vehicle_id'   => ['required', 'integer', 'exists:vehicles,id'],
            'employee_id'  => ['nullable', 'integer', 'exists:employees,id'],
            'expense_type' => ['required', 'in:'.implode(',', array_column(ExpenseType::cases(), 'value'))],
            'amount'       => ['required', 'numeric', 'min:0.01'],
            'currency_code'=> ['required', 'in:TRY,USD,EUR,GBP'],
            'expense_date' => ['required', 'date'],
            'odometer_km'  => ['nullable', 'numeric', 'min:0'],
            'description'  => ['nullable', 'string', 'max:500'],
        ]);

        $data = [
            'vehicle_id'   => $validated['vehicle_id'],
            'employee_id'  => $validated['employee_id'] ?: null,
            'expense_type' => $validated['expense_type'],
            'amount'       => $validated['amount'],
            'currency_code'=> $validated['currency_code'],
            'expense_date' => $validated['expense_date'],
            'odometer_km'  => filled($validated['odometer_km']) ? $validated['odometer_km'] : null,
            'description'  => filled($validated['description']) ? $validated['description'] : null,
        ];

        if ($this->editingId !== null && $this->editingId > 0) {
            $row = TripExpense::query()->findOrFail($this->editingId);
            Gate::authorize('update', $row);
            $row->update($data);
        } else {
            Gate::authorize('create', TripExpense::class);
            TripExpense::query()->create($data);
        }

        $this->editingId = null;
        $this->resetForm();
        $this->resetPage();
    }

    public function delete(int $id): void
    {
        $row = TripExpense::query()->findOrFail($id);
        Gate::authorize('delete', $row);
        $row->delete();
        $this->resetPage();
    }

    private function resetForm(): void
    {
        $this->vehicle_id    = null;
        $this->employee_id   = null;
        $this->shipment_id   = null;
        $this->expense_type  = 'fuel';
        $this->amount        = '';
        $this->currency_code = 'TRY';
        $this->expense_date  = now()->toDateString();
        $this->odometer_km   = '';
        $this->description   = '';
    }
}; ?>

<div class="mx-auto flex w-full max-w-6xl flex-col gap-6 p-4 lg:p-8">
    @php
        $authUser = auth()->user();
        $canWrite = $authUser instanceof \App\Models\User
            && \App\Authorization\LogisticsPermission::canWrite($authUser, \App\Authorization\LogisticsPermission::TRIP_EXPENSES_WRITE);
    @endphp

    <x-admin.page-header
        :heading="__('Trip expenses')"
        :description="__('Record and track fuel, toll, repair and other vehicle expenses.')"
    >
        <x-slot name="actions">
            @if ($canWrite)
                <flux:button type="button" variant="primary" wire:click="startCreate" icon="plus">
                    {{ __('New expense') }}
                </flux:button>
            @endif
        </x-slot>
    </x-admin.page-header>

    {{-- KPI Cards --}}
    <div class="grid gap-3 sm:grid-cols-4">
        <flux:card class="p-4">
            <flux:text class="text-sm text-zinc-500 dark:text-zinc-400">{{ __('This month (TRY)') }}</flux:text>
            <flux:heading size="lg">{{ number_format($this->kpiStats['total_this_month'], 2) }} ₺</flux:heading>
        </flux:card>
        <flux:card class="p-4">
            <flux:text class="text-sm text-zinc-500 dark:text-zinc-400">{{ __('Top expense type') }}</flux:text>
            <flux:heading size="lg">{{ $this->kpiStats['top_type'] }}</flux:heading>
        </flux:card>
        <flux:card class="p-4">
            <flux:text class="text-sm text-zinc-500 dark:text-zinc-400">{{ __('Avg per vehicle') }}</flux:text>
            <flux:heading size="lg">{{ number_format($this->kpiStats['avg_per_vehicle'], 2) }} ₺</flux:heading>
        </flux:card>
        <flux:card class="p-4">
            <flux:text class="text-sm text-zinc-500 dark:text-zinc-400">{{ __('Total records') }}</flux:text>
            <flux:heading size="lg">{{ $this->kpiStats['total_count'] }}</flux:heading>
        </flux:card>
    </div>

    {{-- Filters --}}
    <x-admin.filter-bar :label="__('Filters')">
        <flux:select wire:model.live="filterVehicle" :label="__('Vehicle')" class="max-w-[200px]">
            <option value="">{{ __('All vehicles') }}</option>
            @foreach ($this->vehicles as $v)
                <option value="{{ $v->id }}">{{ $v->plate }}</option>
            @endforeach
        </flux:select>
        <flux:select wire:model.live="filterType" :label="__('Type')" class="max-w-[160px]">
            <option value="">{{ __('All types') }}</option>
            @foreach (\App\Enums\ExpenseType::cases() as $type)
                <option value="{{ $type->value }}">{{ $type->label() }}</option>
            @endforeach
        </flux:select>
        <flux:input wire:model.live="filterDateFrom" type="date" :label="__('From')" class="max-w-[160px]" />
        <flux:input wire:model.live="filterDateTo" type="date" :label="__('To')" class="max-w-[160px]" />
    </x-admin.filter-bar>

    {{-- Create / Edit Form --}}
    @if ($canWrite && $editingId !== null)
        <flux:card class="p-4">
            <flux:heading size="lg" class="mb-4">
                {{ $editingId === 0 ? __('New expense') : __('Edit expense') }}
            </flux:heading>
            <form wire:submit="save" class="grid gap-4 sm:grid-cols-2">
                <flux:select wire:model="vehicle_id" :label="__('Vehicle')" required>
                    <option value="">{{ __('— Select vehicle —') }}</option>
                    @foreach ($this->vehicles as $v)
                        <option value="{{ $v->id }}">{{ $v->plate }}</option>
                    @endforeach
                </flux:select>
                <flux:select wire:model="employee_id" :label="__('Driver (optional)')">
                    <option value="">{{ __('— None —') }}</option>
                    @foreach ($this->employees as $e)
                        <option value="{{ $e->id }}">{{ $e->name }}</option>
                    @endforeach
                </flux:select>
                <flux:select wire:model="expense_type" :label="__('Expense type')" required>
                    @foreach (\App\Enums\ExpenseType::cases() as $type)
                        <option value="{{ $type->value }}">{{ $type->label() }}</option>
                    @endforeach
                </flux:select>
                <flux:input wire:model="expense_date" type="date" :label="__('Date')" required />
                <flux:input wire:model="amount" type="number" step="0.01" min="0.01" :label="__('Amount')" required />
                <flux:select wire:model="currency_code" :label="__('Currency')" required>
                    <option value="TRY">TRY</option>
                    <option value="USD">USD</option>
                    <option value="EUR">EUR</option>
                </flux:select>
                <flux:input wire:model="odometer_km" type="number" step="0.1" min="0" :label="__('Odometer (km)')" />
                <flux:textarea wire:model="description" :label="__('Description')" rows="2" class="sm:col-span-2" />
                <div class="flex flex-wrap gap-2 sm:col-span-2">
                    <flux:button type="submit" variant="primary">{{ __('Save') }}</flux:button>
                    <flux:button type="button" variant="ghost" wire:click="cancelForm">{{ __('Cancel') }}</flux:button>
                </div>
            </form>
        </flux:card>
    @endif

    {{-- Table --}}
    <flux:card class="p-4">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-zinc-200 text-sm dark:divide-zinc-700">
                <thead>
                    <tr class="text-start text-zinc-500 dark:text-zinc-400">
                        <th class="py-2 pe-4 font-medium">{{ __('Date') }}</th>
                        <th class="py-2 pe-4 font-medium">{{ __('Vehicle') }}</th>
                        <th class="py-2 pe-4 font-medium">{{ __('Driver') }}</th>
                        <th class="py-2 pe-4 font-medium">{{ __('Type') }}</th>
                        <th class="py-2 pe-4 font-medium text-end">{{ __('Amount') }}</th>
                        <th class="py-2 pe-4 font-medium">{{ __('KM') }}</th>
                        @if ($canWrite)
                            <th class="py-2 text-end font-medium">{{ __('Actions') }}</th>
                        @endif
                    </tr>
                </thead>
                <tbody class="divide-y divide-zinc-100 dark:divide-zinc-800">
                    @forelse ($this->paginatedExpenses as $row)
                        <tr>
                            <td class="py-2 pe-4 whitespace-nowrap">{{ $row->expense_date->format('d.m.Y') }}</td>
                            <td class="py-2 pe-4">
                                @if ($row->vehicle)
                                    <a href="{{ route('admin.vehicles.show', $row->vehicle) }}" class="text-primary hover:underline font-mono" wire:navigate>
                                        {{ $row->vehicle->plate }}
                                    </a>
                                @else
                                    —
                                @endif
                            </td>
                            <td class="py-2 pe-4 text-zinc-500">{{ $row->employee?->name ?? '—' }}</td>
                            <td class="py-2 pe-4">
                                <flux:badge :color="$row->expense_type->color()" size="sm">
                                    {{ $row->expense_type->label() }}
                                </flux:badge>
                            </td>
                            <td class="py-2 pe-4 text-end font-mono">
                                {{ number_format((float) $row->amount, 2) }}
                                <span class="text-xs text-zinc-400">{{ $row->currency_code }}</span>
                            </td>
                            <td class="py-2 pe-4 font-mono text-xs text-zinc-500">
                                {{ $row->odometer_km ? number_format((float) $row->odometer_km, 0) : '—' }}
                            </td>
                            @if ($canWrite)
                                <td class="py-2 text-end">
                                    <flux:button size="sm" variant="ghost" wire:click="startEdit({{ $row->id }})">{{ __('Edit') }}</flux:button>
                                    <flux:button
                                        size="sm" variant="ghost"
                                        wire:click="delete({{ $row->id }})"
                                        wire:confirm="{{ __('Delete this expense?') }}"
                                    >{{ __('Delete') }}</flux:button>
                                </td>
                            @endif
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="py-8 text-center text-zinc-500">
                                {{ __('No trip expenses yet.') }}
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="mt-4">
            {{ $this->paginatedExpenses->links() }}
        </div>
    </flux:card>
</div>
