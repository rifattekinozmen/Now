<?php

use App\Livewire\Concerns\RequiresLogisticsAdmin;
use App\Models\MaterialCode;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

new #[Lazy, Title('Material codes')] class extends Component
{
    use RequiresLogisticsAdmin;
    use WithPagination;

    public ?int $editingId = null;

    public string $code = '';

    public string $name = '';

    public string $category = '';

    public string $handling_type = '';

    public bool $is_adr = false;

    public string $unit = 'ton';

    public bool $is_active = true;

    public string $filterSearch = '';

    public string $filterCategory = '';

    public bool $filtersOpen = false;

    public function mount(): void
    {
        Gate::authorize('viewAny', MaterialCode::class);
    }

    public function updatedFilterSearch(): void
    {
        $this->resetPage();
    }

    public function updatedFilterCategory(): void
    {
        $this->resetPage();
    }

    /**
     * @return array{total: int, active: int, adr: int, categories: int}
     */
    #[Computed]
    public function stats(): array
    {
        $total = MaterialCode::query()->count();
        $active = MaterialCode::query()->where('is_active', true)->count();
        $adr = MaterialCode::query()->where('is_adr', true)->count();
        $categories = MaterialCode::query()->distinct('category')->count('category');

        return compact('total', 'active', 'adr', 'categories');
    }

    /**
     * @return Builder<MaterialCode>
     */
    private function codesQuery(): Builder
    {
        $q = MaterialCode::query();

        if ($this->filterSearch !== '') {
            $term = '%'.addcslashes($this->filterSearch, '%_\\').'%';
            $q->where(function (Builder $qq) use ($term): void {
                $qq->where('code', 'like', $term)
                    ->orWhere('name', 'like', $term)
                    ->orWhere('handling_type', 'like', $term);
            });
        }

        if ($this->filterCategory !== '') {
            $q->where('category', $this->filterCategory);
        }

        return $q->orderBy('code');
    }

    #[Computed]
    public function paginatedCodes(): LengthAwarePaginator
    {
        return $this->codesQuery()->paginate(20);
    }

    public function saveMaterialCode(): void
    {
        Gate::authorize('create', MaterialCode::class);

        $validated = $this->validate([
            'code'         => ['required', 'string', 'max:50', 'unique:material_codes,code'],
            'name'         => ['required', 'string', 'max:255'],
            'category'     => ['required', 'string', 'max:50'],
            'handling_type' => ['nullable', 'string', 'max:100'],
            'is_adr'       => ['boolean'],
            'unit'         => ['required', 'string', 'max:20'],
            'is_active'    => ['boolean'],
        ]);

        MaterialCode::create($validated);

        $this->resetForm();
        $this->resetPage();
        session()->flash('success', __('Material code created.'));
    }

    public function startEdit(int $id): void
    {
        Gate::authorize('update', MaterialCode::findOrFail($id));

        $mc = MaterialCode::findOrFail($id);
        $this->editingId = $id;
        $this->code = $mc->code;
        $this->name = $mc->name;
        $this->category = $mc->category ?? '';
        $this->handling_type = $mc->handling_type ?? '';
        $this->is_adr = (bool) $mc->is_adr;
        $this->unit = $mc->unit ?? 'ton';
        $this->is_active = (bool) $mc->is_active;
    }

    public function updateMaterialCode(): void
    {
        $mc = MaterialCode::findOrFail($this->editingId);
        Gate::authorize('update', $mc);

        $validated = $this->validate([
            'code'         => ['required', 'string', 'max:50', 'unique:material_codes,code,'.$this->editingId],
            'name'         => ['required', 'string', 'max:255'],
            'category'     => ['required', 'string', 'max:50'],
            'handling_type' => ['nullable', 'string', 'max:100'],
            'is_adr'       => ['boolean'],
            'unit'         => ['required', 'string', 'max:20'],
            'is_active'    => ['boolean'],
        ]);

        $mc->update($validated);

        $this->resetForm();
        session()->flash('success', __('Material code updated.'));
    }

    public function cancelEdit(): void
    {
        $this->resetForm();
    }

    public function deleteMaterialCode(int $id): void
    {
        $mc = MaterialCode::findOrFail($id);
        Gate::authorize('delete', $mc);
        $mc->delete();
        $this->resetPage();
        session()->flash('success', __('Material code deleted.'));
    }

    private function resetForm(): void
    {
        $this->editingId = null;
        $this->code = '';
        $this->name = '';
        $this->category = '';
        $this->handling_type = '';
        $this->is_adr = false;
        $this->unit = 'ton';
        $this->is_active = true;
    }
}; ?>

<div class="mx-auto flex w-full max-w-7xl flex-col gap-6 p-4 lg:p-8">
    <x-admin.page-header
        :heading="__('Material codes')"
        :description="__('CEM, raw materials, packaging and ADR flags for pricing and operations.')"
    />

    {{-- KPI Cards --}}
    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <flux:card>
            <flux:heading>{{ __('Total') }}</flux:heading>
            <p class="mt-1 text-2xl font-bold">{{ $this->stats['total'] }}</p>
        </flux:card>
        <flux:card>
            <flux:heading>{{ __('Active') }}</flux:heading>
            <p class="mt-1 text-2xl font-bold text-green-600">{{ $this->stats['active'] }}</p>
        </flux:card>
        <flux:card>
            <flux:heading>{{ __('ADR / Dangerous') }}</flux:heading>
            <p class="mt-1 text-2xl font-bold text-red-600">{{ $this->stats['adr'] }}</p>
        </flux:card>
        <flux:card>
            <flux:heading>{{ __('Categories') }}</flux:heading>
            <p class="mt-1 text-2xl font-bold">{{ $this->stats['categories'] }}</p>
        </flux:card>
    </div>

    @if (session('success'))
        <flux:callout variant="success" icon="check-circle">{{ session('success') }}</flux:callout>
    @endif

    {{-- Filter bar --}}
    <x-admin.filter-bar>
        <flux:input wire:model.live.debounce.400ms="filterSearch" :label="__('Search code / name')" />
        <flux:select wire:model.live="filterCategory" :label="__('Category')">
            <flux:select.option value="">{{ __('All categories') }}</flux:select.option>
            <flux:select.option value="raw_material">{{ __('Raw Material') }}</flux:select.option>
            <flux:select.option value="cement">{{ __('Cement') }}</flux:select.option>
            <flux:select.option value="packaged">{{ __('Packaged') }}</flux:select.option>
            <flux:select.option value="fertilizer">{{ __('Fertilizer') }}</flux:select.option>
            <flux:select.option value="mine">{{ __('Mine') }}</flux:select.option>
            <flux:select.option value="other">{{ __('Other') }}</flux:select.option>
        </flux:select>
    </x-admin.filter-bar>

    {{-- Create / Edit Form --}}
    <flux:card>
        <flux:heading size="lg" class="mb-4">
            {{ $editingId ? __('Edit material code') : __('New material code') }}
        </flux:heading>
        <form wire:submit="{{ $editingId ? 'updateMaterialCode' : 'saveMaterialCode' }}" class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            <flux:input wire:model="code" :label="__('Code')" required />
            <flux:input wire:model="name" :label="__('Name')" required class="lg:col-span-2" />
            <flux:select wire:model="category" :label="__('Category')" required>
                <flux:select.option value="">{{ __('-- Select --') }}</flux:select.option>
                <flux:select.option value="raw_material">{{ __('Raw Material') }}</flux:select.option>
                <flux:select.option value="cement">{{ __('Cement') }}</flux:select.option>
                <flux:select.option value="packaged">{{ __('Packaged') }}</flux:select.option>
                <flux:select.option value="fertilizer">{{ __('Fertilizer') }}</flux:select.option>
                <flux:select.option value="mine">{{ __('Mine') }}</flux:select.option>
                <flux:select.option value="other">{{ __('Other') }}</flux:select.option>
            </flux:select>
            <flux:input wire:model="handling_type" :label="__('Handling type')" />
            <flux:input wire:model="unit" :label="__('Unit (ton/kg/m³)')" required />
            <div class="flex items-end gap-6 pb-1">
                <flux:checkbox wire:model="is_adr" :label="__('ADR (hazardous)')" />
                <flux:checkbox wire:model="is_active" :label="__('Active')" />
            </div>
            <div class="flex gap-2 sm:col-span-2 lg:col-span-3">
                <flux:button type="submit" variant="primary">
                    {{ $editingId ? __('Save changes') : __('Create') }}
                </flux:button>
                @if ($editingId)
                    <flux:button type="button" variant="ghost" wire:click="cancelEdit">{{ __('Cancel') }}</flux:button>
                @endif
            </div>
        </form>
    </flux:card>

    {{-- Table --}}
    <flux:card>
        <flux:table>
            <flux:table.columns>
                <flux:table.column>{{ __('Code') }}</flux:table.column>
                <flux:table.column>{{ __('Name') }}</flux:table.column>
                <flux:table.column>{{ __('Category') }}</flux:table.column>
                <flux:table.column>{{ __('Handling') }}</flux:table.column>
                <flux:table.column>{{ __('Unit') }}</flux:table.column>
                <flux:table.column>{{ __('ADR') }}</flux:table.column>
                <flux:table.column>{{ __('Active') }}</flux:table.column>
                <flux:table.column>{{ __('Actions') }}</flux:table.column>
            </flux:table.columns>
            <flux:table.rows>
                @forelse ($this->paginatedCodes as $mc)
                    <flux:table.row :key="$mc->id">
                        <flux:table.cell class="font-mono text-sm">{{ $mc->code }}</flux:table.cell>
                        <flux:table.cell>{{ $mc->name }}</flux:table.cell>
                        <flux:table.cell>{{ $mc->categoryLabel() }}</flux:table.cell>
                        <flux:table.cell>{{ $mc->handling_type ?? '—' }}</flux:table.cell>
                        <flux:table.cell>{{ $mc->unit }}</flux:table.cell>
                        <flux:table.cell>
                            @if ($mc->is_adr)
                                <flux:badge color="red" size="sm">{{ __('ADR') }}</flux:badge>
                            @else
                                <span class="text-zinc-400">—</span>
                            @endif
                        </flux:table.cell>
                        <flux:table.cell>
                            @if ($mc->is_active)
                                <flux:badge color="green" size="sm">{{ __('Active') }}</flux:badge>
                            @else
                                <flux:badge color="zinc" size="sm">{{ __('Inactive') }}</flux:badge>
                            @endif
                        </flux:table.cell>
                        <flux:table.cell>
                            <div class="flex gap-2">
                                <flux:button size="sm" wire:click="startEdit({{ $mc->id }})">{{ __('Edit') }}</flux:button>
                                <flux:button
                                    size="sm"
                                    variant="danger"
                                    wire:click="deleteMaterialCode({{ $mc->id }})"
                                    wire:confirm="{{ __('Delete this material code?') }}"
                                >{{ __('Delete') }}</flux:button>
                            </div>
                        </flux:table.cell>
                    </flux:table.row>
                @empty
                    <flux:table.row>
                        <flux:table.cell colspan="8" class="text-center text-zinc-500">
                            {{ __('No material codes found.') }}
                        </flux:table.cell>
                    </flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>
        <div class="mt-4">
            {{ $this->paginatedCodes->links() }}
        </div>
    </flux:card>
</div>
