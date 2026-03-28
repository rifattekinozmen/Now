<?php

use App\Models\Vehicle;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Vehicles')] class extends Component
{
    public string $plate = '';

    public string $brand = '';

    public string $model = '';

    public ?string $inspection_valid_until = null;

    public function mount(): void
    {
        Gate::authorize('viewAny', Vehicle::class);
    }

    /**
     * @return \Illuminate\Database\Eloquent\Collection<int, Vehicle>
     */
    public function vehicleList()
    {
        return Vehicle::query()->orderByDesc('id')->limit(100)->get();
    }

    public function saveVehicle(): void
    {
        Gate::authorize('create', Vehicle::class);

        $validated = $this->validate([
            'plate' => ['required', 'string', 'max:32'],
            'brand' => ['nullable', 'string', 'max:100'],
            'model' => ['nullable', 'string', 'max:100'],
            'inspection_valid_until' => ['nullable', 'date'],
        ]);

        Vehicle::query()->create([
            'plate' => strtoupper($validated['plate']),
            'brand' => $validated['brand'] ?: null,
            'model' => $validated['model'] ?: null,
            'inspection_valid_until' => $validated['inspection_valid_until'],
        ]);

        $this->reset('plate', 'brand', 'model', 'inspection_valid_until');
    }
}; ?>

<x-layouts::app :title="__('Vehicles')">
    <div class="mx-auto flex w-full max-w-6xl flex-col gap-6 p-4 lg:p-8">
        <flux:heading size="xl">{{ __('Vehicles') }}</flux:heading>

        <div class="grid gap-6 lg:grid-cols-2">
            <flux:card>
                <flux:heading size="lg" class="mb-4">{{ __('New vehicle') }}</flux:heading>
                <form wire:submit="saveVehicle" class="flex flex-col gap-4">
                    <flux:input wire:model="plate" :label="__('Plate')" required />
                    <flux:input wire:model="brand" :label="__('Brand')" />
                    <flux:input wire:model="model" :label="__('Model')" />
                    <flux:input wire:model="inspection_valid_until" type="date" :label="__('Inspection valid until')" />
                    <flux:button type="submit" variant="primary">{{ __('Save') }}</flux:button>
                </form>
            </flux:card>
        </div>

        <flux:card>
            <flux:heading size="lg" class="mb-4">{{ __('Fleet') }}</flux:heading>
            <flux:table>
                <flux:table.columns>
                    <flux:table.column>{{ __('Plate') }}</flux:table.column>
                    <flux:table.column>{{ __('Brand') }}</flux:table.column>
                    <flux:table.column>{{ __('Model') }}</flux:table.column>
                    <flux:table.column>{{ __('Inspection') }}</flux:table.column>
                </flux:table.columns>
                <flux:table.rows>
                    @forelse ($this->vehicleList() as $vehicle)
                        <flux:table.row :key="$vehicle->id">
                            <flux:table.cell>{{ $vehicle->plate }}</flux:table.cell>
                            <flux:table.cell>{{ $vehicle->brand ?? '—' }}</flux:table.cell>
                            <flux:table.cell>{{ $vehicle->model ?? '—' }}</flux:table.cell>
                            <flux:table.cell>
                                {{ $vehicle->inspection_valid_until?->format('Y-m-d') ?? '—' }}
                            </flux:table.cell>
                        </flux:table.row>
                    @empty
                        <flux:table.row>
                            <flux:table.cell>{{ __('No vehicles yet.') }}</flux:table.cell>
                            <flux:table.cell></flux:table.cell>
                            <flux:table.cell></flux:table.cell>
                            <flux:table.cell></flux:table.cell>
                        </flux:table.row>
                    @endforelse
                </flux:table.rows>
            </flux:table>
        </flux:card>
    </div>
</x-layouts::app>
