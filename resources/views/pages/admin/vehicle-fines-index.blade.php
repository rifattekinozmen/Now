<?php

use App\Enums\VehicleFineStatus;
use App\Enums\VehicleFineType;
use App\Livewire\Concerns\RequiresLogisticsAdmin;
use App\Models\Vehicle;
use App\Models\VehicleFine;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

new #[Lazy, Title('Traffic Fines')] class extends Component
{
    use RequiresLogisticsAdmin;
    use WithPagination;

    public string $filterSearch = '';

    public string $filterStatus = '';

    public string $filterType = '';

    public bool $filtersOpen = false;

    public function mount(): void
    {
        Gate::authorize('viewAny', VehicleFine::class);
    }

    public function updatedFilterSearch(): void { $this->resetPage(); }

    public function updatedFilterStatus(): void { $this->resetPage(); }

    public function updatedFilterType(): void { $this->resetPage(); }

    /**
     * @return array{total: int, pending: int, paid: int, total_amount: float}
     */
    #[Computed]
    public function stats(): array
    {
        $q = VehicleFine::query();

        return [
            'total'        => (int) $q->count(),
            'pending'      => (int) VehicleFine::query()->where('status', 'pending')->count(),
            'paid'         => (int) VehicleFine::query()->where('status', 'paid')->count(),
            'total_amount' => (float) VehicleFine::query()->sum('amount'),
        ];
    }

    /**
     * @return Builder<VehicleFine>
     */
    private function finesQuery(): Builder
    {
        $q = VehicleFine::query()->with('vehicle');

        if ($this->filterSearch !== '') {
            $term = '%'.addcslashes($this->filterSearch, '%_\\').'%';
            $q->where(function (Builder $qq) use ($term): void {
                $qq->where('fine_no', 'like', $term)
                    ->orWhere('location', 'like', $term)
                    ->orWhereHas('vehicle', fn (Builder $v) => $v->where('plate', 'like', $term));
            });
        }

        if ($this->filterStatus !== '') {
            $q->where('status', $this->filterStatus);
        }

        if ($this->filterType !== '') {
            $q->where('fine_type', $this->filterType);
        }

        return $q->orderByDesc('fine_date');
    }

    #[Computed]
    public function paginatedFines(): LengthAwarePaginator
    {
        return $this->finesQuery()->paginate(20);
    }

    public function markPaid(int $id): void
    {
        $fine = VehicleFine::findOrFail($id);
        Gate::authorize('update', $fine);
        $fine->update(['status' => VehicleFineStatus::Paid->value, 'paid_at' => now()]);
        unset($this->paginatedFines, $this->stats);
    }
}; ?>

<div class="flex flex-col gap-6">
    <flux:heading size="xl">{{ __('Traffic Fines') }}</flux:heading>

    {{-- KPI Cards --}}
    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <flux:card>
            <flux:heading>{{ __('Total Fines') }}</flux:heading>
            <p class="mt-1 text-2xl font-bold">{{ $this->stats['total'] }}</p>
        </flux:card>
        <flux:card>
            <flux:heading>{{ __('Pending') }}</flux:heading>
            <p class="mt-1 text-2xl font-bold text-red-600">{{ $this->stats['pending'] }}</p>
        </flux:card>
        <flux:card>
            <flux:heading>{{ __('Paid') }}</flux:heading>
            <p class="mt-1 text-2xl font-bold text-green-600">{{ $this->stats['paid'] }}</p>
        </flux:card>
        <flux:card>
            <flux:heading>{{ __('Total Amount') }}</flux:heading>
            <p class="mt-1 text-2xl font-bold">{{ number_format($this->stats['total_amount'], 0) }} TRY</p>
        </flux:card>
    </div>

    {{-- Filter bar --}}
    <x-admin.filter-bar>
        <flux:input wire:model.live.debounce.400ms="filterSearch" :label="__('Search plate / fine no / location')" />
        <flux:select wire:model.live="filterStatus" :label="__('Status')">
            <flux:select.option value="">{{ __('All statuses') }}</flux:select.option>
            @foreach (VehicleFineStatus::cases() as $s)
                <flux:select.option :value="$s->value">{{ $s->label() }}</flux:select.option>
            @endforeach
        </flux:select>
        <flux:select wire:model.live="filterType" :label="__('Type')">
            <flux:select.option value="">{{ __('All types') }}</flux:select.option>
            @foreach (VehicleFineType::cases() as $t)
                <flux:select.option :value="$t->value">{{ $t->label() }}</flux:select.option>
            @endforeach
        </flux:select>
    </x-admin.filter-bar>

    {{-- Table --}}
    <flux:card>
        <flux:table>
            <flux:table.columns>
                <flux:table.column>{{ __('Date') }}</flux:table.column>
                <flux:table.column>{{ __('Vehicle') }}</flux:table.column>
                <flux:table.column>{{ __('Type') }}</flux:table.column>
                <flux:table.column>{{ __('Fine No') }}</flux:table.column>
                <flux:table.column>{{ __('Location') }}</flux:table.column>
                <flux:table.column>{{ __('Amount') }}</flux:table.column>
                <flux:table.column>{{ __('Status') }}</flux:table.column>
                <flux:table.column>{{ __('Actions') }}</flux:table.column>
            </flux:table.columns>
            <flux:table.rows>
                @forelse ($this->paginatedFines as $fine)
                    <flux:table.row :key="$fine->id">
                        <flux:table.cell>{{ $fine->fine_date->format('d M Y') }}</flux:table.cell>
                        <flux:table.cell>
                            <a href="{{ route('admin.vehicles.show', $fine->vehicle_id) }}" wire:navigate
                               class="font-mono text-sm text-blue-600 hover:underline">
                                {{ $fine->vehicle?->plate ?? '—' }}
                            </a>
                        </flux:table.cell>
                        <flux:table.cell>{{ $fine->fine_type->label() }}</flux:table.cell>
                        <flux:table.cell>{{ $fine->fine_no ?? '—' }}</flux:table.cell>
                        <flux:table.cell>{{ $fine->location ?? '—' }}</flux:table.cell>
                        <flux:table.cell>{{ number_format((float) $fine->amount, 2) }} {{ $fine->currency_code }}</flux:table.cell>
                        <flux:table.cell>
                            @php $statusColor = match($fine->status) {
                                VehicleFineStatus::Paid => 'green',
                                VehicleFineStatus::Appealed => 'yellow',
                                default => 'red',
                            }; @endphp
                            <flux:badge :color="$statusColor" size="sm">{{ $fine->status->label() }}</flux:badge>
                        </flux:table.cell>
                        <flux:table.cell>
                            @if ($fine->status !== VehicleFineStatus::Paid)
                                <flux:button size="sm" variant="primary"
                                    wire:click="markPaid({{ $fine->id }})"
                                    wire:confirm="{{ __('Mark fine as paid?') }}"
                                >{{ __('Mark Paid') }}</flux:button>
                            @else
                                <span class="text-sm text-zinc-400">{{ $fine->paid_at?->format('d M Y') }}</span>
                            @endif
                        </flux:table.cell>
                    </flux:table.row>
                @empty
                    <flux:table.row>
                        <flux:table.cell colspan="8" class="text-center text-zinc-500">
                            {{ __('No fines found.') }}
                        </flux:table.cell>
                    </flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>
        <div class="mt-4">
            {{ $this->paginatedFines->links() }}
        </div>
    </flux:card>
</div>
