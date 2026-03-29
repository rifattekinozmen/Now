<?php

use App\Authorization\LogisticsPermission;
use App\Livewire\Concerns\RequiresLogisticsAdmin;
use App\Models\FuelIntake;
use App\Models\Vehicle;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

new #[Title('Fuel intakes')] class extends Component
{
    use RequiresLogisticsAdmin;
    use WithPagination;

    public ?int $editingId = null;

    public string $vehicle_id = '';

    public string $liters = '';

    public string $odometer_km = '';

    public string $recorded_at = '';

    public string $filterSearch = '';

    public string $sortColumn = 'recorded_at';

    public string $sortDirection = 'desc';

    public function mount(): void
    {
        Gate::authorize('viewAny', FuelIntake::class);
        $this->recorded_at = now()->timezone(config('app.timezone'))->format('Y-m-d\TH:i');
    }

    public function updatedFilterSearch(): void
    {
        $this->resetPage();
    }

    /**
     * @return array{total: int, total_liters: float}
     */
    #[Computed]
    public function fuelIntakeStats(): array
    {
        $row = FuelIntake::query()
            ->toBase()
            ->selectRaw('COUNT(*) as c, COALESCE(SUM(liters), 0) as sum_liters')
            ->first();

        return [
            'total' => (int) ($row->c ?? 0),
            'total_liters' => (float) ($row->sum_liters ?? 0),
        ];
    }

    /**
     * @return Builder<FuelIntake>
     */
    private function intakesQuery(): Builder
    {
        $q = FuelIntake::query()->with('vehicle');

        if ($this->filterSearch !== '') {
            $term = '%'.addcslashes($this->filterSearch, '%_\\').'%';
            $q->where(function (Builder $qq) use ($term): void {
                $qq->whereHas('vehicle', fn (Builder $vq) => $vq->where('plate', 'like', $term));
            });
        }

        $allowed = ['id', 'recorded_at', 'liters', 'vehicle_id'];
        $column = in_array($this->sortColumn, $allowed, true) ? $this->sortColumn : 'recorded_at';
        $direction = $this->sortDirection === 'asc' ? 'asc' : 'desc';

        return $q->orderBy($column, $direction);
    }

    #[Computed]
    public function paginatedIntakes(): LengthAwarePaginator
    {
        return $this->intakesQuery()->paginate(15);
    }

    public function sortBy(string $column): void
    {
        $allowed = ['id', 'recorded_at', 'liters', 'vehicle_id'];
        if (! in_array($column, $allowed, true)) {
            return;
        }

        if ($this->sortColumn === $column) {
            $this->sortDirection = $this->sortDirection === 'asc' ? 'desc' : 'asc';
        } else {
            $this->sortColumn = $column;
            $this->sortDirection = 'desc';
        }

        $this->resetPage();
    }

    public function startCreate(): void
    {
        $this->ensureLogisticsWrite(LogisticsPermission::VEHICLES_WRITE);
        Gate::authorize('create', FuelIntake::class);
        $this->resetForm();
        $this->editingId = 0;
    }

    public function startEdit(int $id): void
    {
        $this->ensureLogisticsWrite(LogisticsPermission::VEHICLES_WRITE);
        $row = FuelIntake::query()->findOrFail($id);
        Gate::authorize('update', $row);
        $this->editingId = $row->id;
        $this->vehicle_id = (string) $row->vehicle_id;
        $this->liters = (string) $row->liters;
        $this->odometer_km = $row->odometer_km !== null ? (string) $row->odometer_km : '';
        $this->recorded_at = $row->recorded_at->timezone(config('app.timezone'))->format('Y-m-d\TH:i');
    }

    public function cancelForm(): void
    {
        $this->editingId = null;
        $this->resetForm();
    }

    public function save(): void
    {
        $this->ensureLogisticsWrite(LogisticsPermission::VEHICLES_WRITE);

        $validated = $this->validate([
            'vehicle_id' => ['required', 'exists:vehicles,id'],
            'liters' => ['required', 'numeric', 'min:0.001', 'max:999999'],
            'odometer_km' => ['nullable', 'numeric', 'min:0', 'max:9999999'],
            'recorded_at' => ['required', 'date'],
        ]);

        $vehicle = Vehicle::query()->findOrFail((int) $validated['vehicle_id']);
        Gate::authorize('view', $vehicle);

        $data = [
            'vehicle_id' => (int) $validated['vehicle_id'],
            'liters' => $validated['liters'],
            'odometer_km' => $validated['odometer_km'] !== '' && $validated['odometer_km'] !== null
                ? $validated['odometer_km']
                : null,
            'recorded_at' => $validated['recorded_at'],
        ];

        if ($this->editingId !== null && $this->editingId > 0) {
            $row = FuelIntake::query()->findOrFail($this->editingId);
            Gate::authorize('update', $row);
            $row->update($data);
        } else {
            Gate::authorize('create', FuelIntake::class);
            FuelIntake::query()->create($data);
        }

        $this->editingId = null;
        $this->resetForm();
        $this->resetPage();
    }

    public function delete(int $id): void
    {
        $this->ensureLogisticsWrite(LogisticsPermission::VEHICLES_WRITE);
        $row = FuelIntake::query()->findOrFail($id);
        Gate::authorize('delete', $row);
        $row->delete();
        $this->resetPage();
    }

    private function resetForm(): void
    {
        $this->vehicle_id = '';
        $this->liters = '';
        $this->odometer_km = '';
        $this->recorded_at = now()->timezone(config('app.timezone'))->format('Y-m-d\TH:i');
    }
}; ?>

<div class="mx-auto flex w-full max-w-6xl flex-col gap-6 p-4 lg:p-8">
    @php
        $authUser = auth()->user();
        $canWriteFuel =
            $authUser instanceof \App\Models\User
            && \App\Authorization\LogisticsPermission::canWrite($authUser, \App\Authorization\LogisticsPermission::VEHICLES_WRITE);
    @endphp

    <div class="flex flex-wrap items-center justify-between gap-4">
        <div>
            <flux:heading size="xl">{{ __('Fuel intakes') }}</flux:heading>
            <flux:text class="mt-1 text-sm text-zinc-600 dark:text-zinc-400">
                {{ __('Fleet fuel records for audit and anomaly checks.') }}
            </flux:text>
        </div>
        <flux:button :href="route('admin.vehicles.index')" variant="ghost" wire:navigate>{{ __('Vehicles') }}</flux:button>
    </div>

    <div class="grid gap-3 sm:grid-cols-2">
        <flux:card class="p-4">
            <flux:text class="text-sm text-zinc-500 dark:text-zinc-400">{{ __('Records') }}</flux:text>
            <flux:heading size="lg">{{ number_format($this->fuelIntakeStats['total']) }}</flux:heading>
        </flux:card>
        <flux:card class="p-4">
            <flux:text class="text-sm text-zinc-500 dark:text-zinc-400">{{ __('Total liters (tenant)') }}</flux:text>
            <flux:heading size="lg">{{ number_format($this->fuelIntakeStats['total_liters'], 3) }}</flux:heading>
        </flux:card>
    </div>

    @if ($canWriteFuel)
        <flux:card class="p-4">
            <div class="mb-4 flex flex-wrap items-center justify-between gap-2">
                <flux:heading size="lg">{{ __('Add or edit record') }}</flux:heading>
                @if ($editingId === null)
                    <flux:button type="button" variant="primary" wire:click="startCreate">{{ __('New fuel intake') }}</flux:button>
                @endif
            </div>

            @if ($editingId !== null)
                <form wire:submit="save" class="grid max-w-xl gap-4">
                    <flux:select wire:model="vehicle_id" :label="__('Vehicle')" required>
                        <option value="">{{ __('Select vehicle') }}</option>
                        @foreach (\App\Models\Vehicle::query()->orderBy('plate')->get() as $v)
                            <option value="{{ $v->id }}">{{ $v->plate }}</option>
                        @endforeach
                    </flux:select>
                    <flux:input wire:model="liters" type="text" :label="__('Liters')" required />
                    <flux:input wire:model="odometer_km" type="text" :label="__('Odometer (km)')" />
                    <flux:input wire:model="recorded_at" type="datetime-local" :label="__('Recorded at')" required />
                    <div class="flex flex-wrap gap-2">
                        <flux:button type="submit" variant="primary">{{ __('Save') }}</flux:button>
                        <flux:button type="button" variant="ghost" wire:click="cancelForm">{{ __('Cancel') }}</flux:button>
                    </div>
                </form>
            @endif
        </flux:card>
    @endif

    <flux:card class="p-4">
        <flux:input wire:model.live.debounce.300ms="filterSearch" :label="__('Search by plate')" class="mb-4 max-w-md" />

        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-zinc-200 text-sm dark:divide-zinc-700">
                <thead>
                    <tr class="text-start text-zinc-500 dark:text-zinc-400">
                        <th class="py-2 pe-4">
                            <button type="button" class="hover:underline" wire:click="sortBy('id')">ID</button>
                        </th>
                        <th class="py-2 pe-4">
                            <button type="button" class="hover:underline" wire:click="sortBy('vehicle_id')">{{ __('Vehicle') }}</button>
                        </th>
                        <th class="py-2 pe-4">
                            <button type="button" class="hover:underline" wire:click="sortBy('liters')">{{ __('Liters') }}</button>
                        </th>
                        <th class="py-2 pe-4">{{ __('Odometer') }}</th>
                        <th class="py-2 pe-4">
                            <button type="button" class="hover:underline" wire:click="sortBy('recorded_at')">{{ __('Recorded') }}</button>
                        </th>
                        @if ($canWriteFuel)
                            <th class="py-2 text-end">{{ __('Actions') }}</th>
                        @endif
                    </tr>
                </thead>
                <tbody class="divide-y divide-zinc-100 dark:divide-zinc-800">
                    @forelse ($this->paginatedIntakes as $row)
                        <tr>
                            <td class="py-2 pe-4 font-mono text-xs">{{ $row->id }}</td>
                            <td class="py-2 pe-4">{{ $row->vehicle?->plate ?? '—' }}</td>
                            <td class="py-2 pe-4">{{ number_format((float) $row->liters, 3) }}</td>
                            <td class="py-2 pe-4">{{ $row->odometer_km !== null ? number_format((float) $row->odometer_km, 2) : '—' }}</td>
                            <td class="py-2 pe-4 whitespace-nowrap">
                                {{ $row->recorded_at->timezone(config('app.timezone'))->format('Y-m-d H:i') }}
                            </td>
                            @if ($canWriteFuel)
                                <td class="py-2 text-end">
                                    <flux:button size="sm" variant="ghost" wire:click="startEdit({{ $row->id }})">{{ __('Edit') }}</flux:button>
                                    <flux:button
                                        size="sm"
                                        variant="ghost"
                                        wire:click="delete({{ $row->id }})"
                                        wire:confirm="{{ __('Delete this fuel intake?') }}"
                                    >
                                        {{ __('Delete') }}
                                    </flux:button>
                                </td>
                            @endif
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="py-6 text-center text-zinc-500">{{ __('No fuel intakes yet.') }}</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="mt-4">
            {{ $this->paginatedIntakes->links() }}
        </div>
    </flux:card>
</div>
