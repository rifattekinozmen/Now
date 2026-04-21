<?php

use App\Enums\BusinessPartnerType;
use App\Models\BusinessPartner;
use App\Services\Logistics\ExcelImportService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\WithFileUploads;
use Livewire\WithPagination;

new #[Lazy, Title('Business Partners')] class extends Component
{
    use WithFileUploads;
    use WithPagination;

    public string $name = '';

    public string $type = 'supplier';

    public string $tax_no = '';

    public string $contact_person = '';

    public string $phone = '';

    public string $email = '';

    public string $address = '';

    public string $city = '';

    public string $country = '';

    public string $iban = '';

    public int $payment_terms_days = 30;

    public bool $is_active = true;

    public string $notes = '';

    public ?int $editingPartnerId = null;

    public $importFile = null;

    public string $filterSearch = '';

    public string $filterType = '';

    public string $filterStatus = '';

    public string $sortColumn = 'id';

    public string $sortDirection = 'desc';

    public bool $filtersOpen = false;

    public bool $partnerFormOpen = false;

    /** @var list<int|string> */
    public array $selectedIds = [];

    public function mount(): void
    {
        Gate::authorize('viewAny', BusinessPartner::class);
    }

    public function updatedFilterSearch(): void
    {
        $this->resetPage();
        $this->selectedIds = [];
        $this->cancelEdit();
    }

    public function updatedFilterType(): void
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
        $this->cancelEdit();
    }

    /**
     * @return array{total: int, active: int, inactive: int, carriers: int}
     */
    #[Computed]
    public function partnerStats(): array
    {
        $monthStart = now()->startOfMonth()->toDateTimeString();

        $row = BusinessPartner::query()
            ->toBase()
            ->selectRaw(
                'COUNT(*) as total, '.
                'SUM(CASE WHEN is_active = 1 THEN 1 ELSE 0 END) as active, '.
                'SUM(CASE WHEN is_active = 0 THEN 1 ELSE 0 END) as inactive, '.
                'SUM(CASE WHEN type = ? THEN 1 ELSE 0 END) as carriers',
                [BusinessPartnerType::Carrier->value]
            )
            ->first();

        return [
            'total'    => (int) ($row->total ?? 0),
            'active'   => (int) ($row->active ?? 0),
            'inactive' => (int) ($row->inactive ?? 0),
            'carriers' => (int) ($row->carriers ?? 0),
        ];
    }

    #[Computed]
    public function activePartnerAdvancedFilterCount(): int
    {
        $n = 0;
        if ($this->filterType !== '') {
            $n++;
        }
        if ($this->filterStatus !== '') {
            $n++;
        }

        return $n;
    }

    public function clearPartnerAdvancedFilters(): void
    {
        $this->filterType = '';
        $this->filterStatus = '';
        $this->resetPage();
        $this->selectedIds = [];
    }

    public function togglePartnerForm(): void
    {
        $user = auth()->user();
        if (! $user instanceof \App\Models\User || ! $user->hasPermissionTo(\App\Authorization\LogisticsPermission::ADMIN)) {
            abort(403);
        }

        Gate::authorize('create', BusinessPartner::class);

        if ($this->partnerFormOpen) {
            $this->partnerFormOpen = false;

            return;
        }

        $this->cancelEdit();
        $this->partnerFormOpen = true;
    }

    /**
     * @return Builder<BusinessPartner>
     */
    private function partnersQuery(): Builder
    {
        $q = BusinessPartner::query();

        if ($this->filterSearch !== '') {
            $term = '%'.addcslashes($this->filterSearch, '%_\\').'%';
            $q->where(function (Builder $qq) use ($term): void {
                $qq->where('name', 'like', $term)
                    ->orWhere('tax_no', 'like', $term)
                    ->orWhere('contact_person', 'like', $term)
                    ->orWhere('city', 'like', $term);
            });
        }

        if ($this->filterType !== '') {
            $q->where('type', $this->filterType);
        }

        if ($this->filterStatus === 'active') {
            $q->where('is_active', true);
        } elseif ($this->filterStatus === 'inactive') {
            $q->where('is_active', false);
        }

        $allowed = ['id', 'name', 'type', 'city', 'payment_terms_days', 'created_at'];
        $column = in_array($this->sortColumn, $allowed, true) ? $this->sortColumn : 'id';
        $direction = $this->sortDirection === 'asc' ? 'asc' : 'desc';

        return $q->orderBy($column, $direction);
    }

    #[Computed]
    public function paginatedPartners(): LengthAwarePaginator
    {
        return $this->partnersQuery()->paginate(15);
    }

    public function sortBy(string $column): void
    {
        $allowed = ['id', 'name', 'type', 'city', 'payment_terms_days', 'created_at'];
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
        $this->cancelEdit();
    }

    public function isPageFullySelected(): bool
    {
        $pageIds = $this->paginatedPartners->pluck('id')->map(fn ($id) => (int) $id)->all();
        if ($pageIds === []) {
            return false;
        }

        $selected = array_map('intval', $this->selectedIds);

        return count(array_diff($pageIds, $selected)) === 0;
    }

    public function toggleSelectPage(): void
    {
        $pageIds = $this->paginatedPartners->pluck('id')->map(fn ($id) => (int) $id)->all();
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
if ($this->selectedIds === []) {
            return;
        }

        $ids = array_map('intval', $this->selectedIds);
        $partners = BusinessPartner::query()->whereIn('id', $ids)->get();
        $deleted = 0;

        foreach ($partners as $partner) {
            Gate::authorize('delete', $partner);
            $partner->delete();
            $deleted++;
        }

        $this->selectedIds = [];
        $this->resetPage();

        session()->flash('bulk_deleted', $deleted);
    }

    public function savePartner(): void
    {
Gate::authorize('create', BusinessPartner::class);

        $validated = $this->validate([
            'name'               => ['required', 'string', 'max:255'],
            'type'               => ['required', 'string', 'in:'.implode(',', array_column(BusinessPartnerType::cases(), 'value'))],
            'tax_no'             => ['nullable', 'string', 'max:20'],
            'contact_person'     => ['nullable', 'string', 'max:255'],
            'phone'              => ['nullable', 'string', 'max:30'],
            'email'              => ['nullable', 'email', 'max:255'],
            'address'            => ['nullable', 'string', 'max:1000'],
            'city'               => ['nullable', 'string', 'max:100'],
            'country'            => ['nullable', 'string', 'max:100'],
            'iban'               => ['nullable', 'string', 'max:34'],
            'payment_terms_days' => ['nullable', 'integer', 'min:0', 'max:3650'],
            'is_active'          => ['boolean'],
            'notes'              => ['nullable', 'string', 'max:5000'],
        ]);

        BusinessPartner::query()->create($this->mapValidatedToModel($validated));

        $this->resetForm();
    }

    public function startEdit(int $partnerId): void
    {
$partner = BusinessPartner::query()->findOrFail($partnerId);
        Gate::authorize('update', $partner);

        $this->editingPartnerId  = $partner->id;
        $this->partnerFormOpen   = false;
        $this->name              = $partner->name;
        $this->type              = $partner->type->value;
        $this->tax_no            = $partner->tax_no ?? '';
        $this->contact_person    = $partner->contact_person ?? '';
        $this->phone             = $partner->phone ?? '';
        $this->email             = $partner->email ?? '';
        $this->address           = $partner->address ?? '';
        $this->city              = $partner->city ?? '';
        $this->country           = $partner->country ?? '';
        $this->iban              = $partner->iban ?? '';
        $this->payment_terms_days = $partner->payment_terms_days ?? 30;
        $this->is_active         = $partner->is_active;
        $this->notes             = $partner->notes ?? '';
    }

    public function cancelEdit(): void
    {
        $this->editingPartnerId = null;
        $this->resetForm();
    }

    public function updatePartner(): void
    {
if ($this->editingPartnerId === null) {
            return;
        }

        $partner = BusinessPartner::query()->findOrFail($this->editingPartnerId);
        Gate::authorize('update', $partner);

        $validated = $this->validate([
            'name'               => ['required', 'string', 'max:255'],
            'type'               => ['required', 'string', 'in:'.implode(',', array_column(BusinessPartnerType::cases(), 'value'))],
            'tax_no'             => ['nullable', 'string', 'max:20'],
            'contact_person'     => ['nullable', 'string', 'max:255'],
            'phone'              => ['nullable', 'string', 'max:30'],
            'email'              => ['nullable', 'email', 'max:255'],
            'address'            => ['nullable', 'string', 'max:1000'],
            'city'               => ['nullable', 'string', 'max:100'],
            'country'            => ['nullable', 'string', 'max:100'],
            'iban'               => ['nullable', 'string', 'max:34'],
            'payment_terms_days' => ['nullable', 'integer', 'min:0', 'max:3650'],
            'is_active'          => ['boolean'],
            'notes'              => ['nullable', 'string', 'max:5000'],
        ]);

        $partner->update($this->mapValidatedToModel($validated));

        $this->cancelEdit();

        session()->flash('partner_updated', true);
    }

    public function deletePartner(int $partnerId): void
    {
$partner = BusinessPartner::query()->findOrFail($partnerId);
        Gate::authorize('delete', $partner);
        $partner->delete();

        $this->selectedIds = array_values(array_diff(
            array_map('intval', $this->selectedIds),
            [(int) $partnerId]
        ));

        if ($this->editingPartnerId === $partnerId) {
            $this->cancelEdit();
        }

        session()->flash('partner_deleted', true);
    }

    public function importPartners(ExcelImportService $excelImport): void
    {
Gate::authorize('create', BusinessPartner::class);

        $this->validate([
            'importFile' => ['required', 'file', 'mimes:xlsx,xls,csv', 'max:10240'],
        ]);

        $tenantId = auth()->user()->tenant_id;
        if ($tenantId === null) {
            abort(403);
        }

        $stored = $this->importFile->store('imports', 'local');
        $path = Storage::disk('local')->path($stored);

        $result = $excelImport->importBusinessPartnersFromPath($path, (int) $tenantId);

        session()->flash('import_created', $result['created']);
        session()->flash('import_errors', $result['errors']);

        Storage::disk('local')->delete($stored);

        $this->reset('importFile');
    }

    public function updatedImportFile(): void
    {
        if ($this->importFile === null) {
            return;
        }

        try {
            $this->importPartners(app(ExcelImportService::class));
        } catch (ValidationException $e) {
            $this->reset('importFile');
            throw $e;
        }
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    private function mapValidatedToModel(array $validated): array
    {
        return [
            'name'               => $validated['name'],
            'type'               => $validated['type'],
            'tax_no'             => $validated['tax_no'] ?: null,
            'contact_person'     => $validated['contact_person'] ?: null,
            'phone'              => $validated['phone'] ?: null,
            'email'              => $validated['email'] ?: null,
            'address'            => $validated['address'] ?: null,
            'city'               => $validated['city'] ?: null,
            'country'            => $validated['country'] ?: null,
            'iban'               => $validated['iban'] ?: null,
            'payment_terms_days' => $validated['payment_terms_days'] ?? null,
            'is_active'          => (bool) $validated['is_active'],
            'notes'              => $validated['notes'] ?: null,
        ];
    }

    private function resetForm(): void
    {
        $this->reset('name', 'tax_no', 'contact_person', 'phone', 'email', 'address', 'city', 'country', 'iban', 'notes');
        $this->type = 'supplier';
        $this->payment_terms_days = 30;
        $this->is_active = true;
        $this->partnerFormOpen = false;
    }
}; ?>

<div class="mx-auto flex w-full max-w-7xl flex-col gap-6 p-4 lg:p-8">
    @php
        $canWrite = auth()->user() instanceof \App\Models\User
            && auth()->user()->hasPermissionTo(\App\Authorization\LogisticsPermission::ADMIN);
        $types = \App\Enums\BusinessPartnerType::cases();
    @endphp

    <x-admin.page-header
        :heading="__('Business Partners')"
        :description="__('Carriers, suppliers, brokers and agents — scoped to your tenant.')"
    >
        <x-slot name="actions">
            @if ($canWrite)
                <x-admin.index-actions>
                    <x-slot name="import">
                        <div class="flex min-w-0 flex-wrap items-center justify-end gap-2" x-data>
                            <input
                                type="file"
                                wire:model="importFile"
                                accept=".xlsx,.xls,.csv"
                                class="sr-only"
                                x-ref="importFileInput"
                            />
                            <flux:tooltip
                                :content="__('First row must be headers: İsim, Tip, Vergi No, İletişim Kişisi, Telefon, Email, Şehir, Ülke, IBAN, Vade Gün.')"
                                position="bottom"
                            >
                                <flux:button
                                    type="button"
                                    icon="information-circle"
                                    variant="ghost"
                                    size="sm"
                                    :aria-label="__('Import format help')"
                                />
                            </flux:tooltip>
                            <flux:button
                                type="button"
                                size="sm"
                                icon="arrow-up-tray"
                                variant="primary"
                                @click.prevent="$refs.importFileInput.click()"
                            >
                                {{ __('Import file') }}
                            </flux:button>
                        </div>
                    </x-slot>
                    <x-slot name="primary">
                        <flux:button size="sm" icon="plus" variant="primary" wire:click="togglePartnerForm">
                            {{ __('New partner') }}
                        </flux:button>
                    </x-slot>
                </x-admin.index-actions>
            @endif
        </x-slot>
    </x-admin.page-header>

    @if (session()->has('bulk_deleted'))
        <flux:callout variant="success" icon="check-circle">
            {{ __('Deleted :count records.', ['count' => session('bulk_deleted')]) }}
        </flux:callout>
    @endif

    @if (session()->has('partner_updated'))
        <flux:callout variant="success" icon="check-circle">{{ __('Partner updated.') }}</flux:callout>
    @endif

    @if (session()->has('partner_deleted'))
        <flux:callout variant="success" icon="check-circle">{{ __('Partner deleted.') }}</flux:callout>
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
            <flux:text class="text-sm text-zinc-500 dark:text-zinc-400">{{ __('Total partners') }}</flux:text>
            <flux:heading size="xl">{{ $this->partnerStats['total'] }}</flux:heading>
        </flux:card>
        <flux:card class="!p-4">
            <flux:text class="text-sm text-zinc-500 dark:text-zinc-400">{{ __('Active') }}</flux:text>
            <flux:heading size="xl">{{ $this->partnerStats['active'] }}</flux:heading>
        </flux:card>
        <flux:card class="!p-4">
            <flux:text class="text-sm text-zinc-500 dark:text-zinc-400">{{ __('Inactive') }}</flux:text>
            <flux:heading size="xl">{{ $this->partnerStats['inactive'] }}</flux:heading>
        </flux:card>
        <flux:card class="!p-4">
            <flux:text class="text-sm text-zinc-500 dark:text-zinc-400">{{ __('Carriers') }}</flux:text>
            <flux:heading size="xl">{{ $this->partnerStats['carriers'] }}</flux:heading>
        </flux:card>
    </div>

    <flux:card class="p-4">
        <div class="flex flex-wrap items-center justify-between gap-2">
            <flux:input
                wire:model.live.debounce.400ms="filterSearch"
                :placeholder="__('Search (name, tax no, city)')"
                icon="magnifying-glass"
                class="max-w-full min-w-0 flex-1 sm:max-w-md"
            />
            <div class="flex flex-wrap items-center justify-end gap-2">
                @if ($this->activePartnerAdvancedFilterCount > 0)
                    <flux:button type="button" variant="ghost" size="sm" wire:click="clearPartnerAdvancedFilters">
                        {{ __('Clear filters') }}
                    </flux:button>
                @endif
                <flux:button variant="ghost" wire:click="$toggle('filtersOpen')" icon="{{ $filtersOpen ? 'chevron-up' : 'chevron-down' }}" class="inline-flex items-center gap-2">
                    {{ __('Filters') }}
                    @if ($this->activePartnerAdvancedFilterCount > 0)
                        <flux:badge color="zinc" size="sm">{{ $this->activePartnerAdvancedFilterCount }}</flux:badge>
                    @endif
                </flux:button>
            </div>
        </div>
        @if ($filtersOpen)
            <div class="mt-3 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                <flux:select wire:model.live="filterType" :label="__('Type')">
                    <flux:select.option value="">{{ __('All types') }}</flux:select.option>
                    @foreach ($types as $t)
                        <flux:select.option :value="$t->value">{{ $t->label() }}</flux:select.option>
                    @endforeach
                </flux:select>
                <flux:select wire:model.live="filterStatus" :label="__('Status')">
                    <flux:select.option value="">{{ __('All') }}</flux:select.option>
                    <flux:select.option value="active">{{ __('Active') }}</flux:select.option>
                    <flux:select.option value="inactive">{{ __('Inactive') }}</flux:select.option>
                </flux:select>
            </div>
        @endif
    </flux:card>

    @if ($canWrite)
        @if ($editingPartnerId !== null)
            <flux:card>
                <flux:heading size="lg" class="mb-4">{{ __('Edit partner') }}</flux:heading>
                <form wire:submit="updatePartner" class="flex flex-col gap-4">
                    <div class="grid gap-4 sm:grid-cols-2">
                        <flux:input wire:model="name" :label="__('Name')" required />
                        <flux:select wire:model="type" :label="__('Type')">
                            @foreach ($types as $t)
                                <flux:select.option :value="$t->value">{{ $t->label() }}</flux:select.option>
                            @endforeach
                        </flux:select>
                    </div>
                    <div class="grid gap-4 sm:grid-cols-2">
                        <flux:input wire:model="tax_no" :label="__('Tax No')" />
                        <flux:input wire:model="contact_person" :label="__('Contact Person')" />
                    </div>
                    <div class="grid gap-4 sm:grid-cols-2">
                        <flux:input wire:model="phone" :label="__('Phone')" />
                        <flux:input wire:model="email" type="email" :label="__('Email')" />
                    </div>
                    <div class="grid gap-4 sm:grid-cols-2">
                        <flux:input wire:model="city" :label="__('City')" />
                        <flux:input wire:model="country" :label="__('Country')" />
                    </div>
                    <div class="grid gap-4 sm:grid-cols-2">
                        <flux:input wire:model="iban" :label="__('IBAN')" />
                        <flux:input wire:model="payment_terms_days" type="number" min="0" max="3650" :label="__('Payment Terms (days)')" />
                    </div>
                    <flux:checkbox wire:model="is_active" :label="__('Active')" />
                    <flux:textarea wire:model="notes" :label="__('Notes')" rows="3" />
                    <div class="flex flex-wrap items-center justify-end gap-2">
                        <flux:button type="button" variant="ghost" wire:click="cancelEdit">{{ __('Cancel') }}</flux:button>
                        <flux:button type="submit" variant="primary">{{ __('Save changes') }}</flux:button>
                    </div>
                </form>
            </flux:card>
        @else
            @if ($partnerFormOpen)
                <flux:card>
                    <flux:heading size="lg" class="mb-4">{{ __('New partner') }}</flux:heading>
                    <form wire:submit="savePartner" class="flex flex-col gap-4">
                        <div class="grid gap-4 sm:grid-cols-2">
                            <flux:input wire:model="name" :label="__('Name')" required />
                            <flux:select wire:model="type" :label="__('Type')">
                                @foreach ($types as $t)
                                    <flux:select.option :value="$t->value">{{ $t->label() }}</flux:select.option>
                                @endforeach
                            </flux:select>
                        </div>
                        <div class="grid gap-4 sm:grid-cols-2">
                            <flux:input wire:model="tax_no" :label="__('Tax No')" />
                            <flux:input wire:model="contact_person" :label="__('Contact Person')" />
                        </div>
                        <div class="grid gap-4 sm:grid-cols-2">
                            <flux:input wire:model="phone" :label="__('Phone')" />
                            <flux:input wire:model="email" type="email" :label="__('Email')" />
                        </div>
                        <div class="grid gap-4 sm:grid-cols-2">
                            <flux:input wire:model="city" :label="__('City')" />
                            <flux:input wire:model="country" :label="__('Country')" />
                        </div>
                        <div class="grid gap-4 sm:grid-cols-2">
                            <flux:input wire:model="iban" :label="__('IBAN')" />
                            <flux:input wire:model="payment_terms_days" type="number" min="0" max="3650" :label="__('Payment Terms (days)')" />
                        </div>
                        <flux:checkbox wire:model="is_active" :label="__('Active')" />
                        <flux:textarea wire:model="notes" :label="__('Notes')" rows="3" />
                        <div class="flex flex-wrap items-center justify-end gap-2">
                            <flux:button type="button" variant="ghost" wire:click="$set('partnerFormOpen', false)">{{ __('Cancel') }}</flux:button>
                            <flux:button type="submit" variant="primary">{{ __('Save') }}</flux:button>
                        </div>
                    </form>
                </flux:card>
            @endif
        @endif
    @endif

    @if ($canWrite && count($selectedIds) > 0)
        <div class="flex flex-wrap items-center justify-between gap-4 rounded-lg border border-zinc-200 bg-zinc-50 p-4 dark:border-zinc-600 dark:bg-zinc-900">
            <flux:text>{{ __(':count selected', ['count' => count($selectedIds)]) }}</flux:text>
            <flux:button
                type="button"
                variant="danger"
                wire:click="bulkDeleteSelected"
                wire:confirm="{{ __('Delete selected business partners?') }}"
            >
                {{ __('Delete selected') }}
            </flux:button>
        </div>
    @endif

    <flux:card>
        <flux:heading size="lg" class="mb-4">{{ __('Partners') }}</flux:heading>
        <flux:table>
            <flux:table.columns>
                @if ($canWrite)
                    <flux:table.column class="w-12">
                        <span class="sr-only">{{ __('Select page') }}</span>
                        <input
                            type="checkbox"
                            class="size-4 rounded border-zinc-300 text-primary focus:ring-primary dark:border-zinc-600"
                            @checked($this->isPageFullySelected())
                            wire:click.prevent="toggleSelectPage"
                            wire:key="select-page-partners"
                        />
                    </flux:table.column>
                @endif
                <flux:table.column>
                    <button type="button" wire:click="sortBy('id')" class="flex items-center gap-1 font-medium text-zinc-800 dark:text-white">
                        {{ __('ID') }}
                        @if ($sortColumn === 'id')<span class="text-xs">{{ $sortDirection === 'asc' ? '↑' : '↓' }}</span>@endif
                    </button>
                </flux:table.column>
                <flux:table.column>
                    <button type="button" wire:click="sortBy('name')" class="flex items-center gap-1 font-medium text-zinc-800 dark:text-white">
                        {{ __('Name') }}
                        @if ($sortColumn === 'name')<span class="text-xs">{{ $sortDirection === 'asc' ? '↑' : '↓' }}</span>@endif
                    </button>
                </flux:table.column>
                <flux:table.column>
                    <button type="button" wire:click="sortBy('type')" class="flex items-center gap-1 font-medium text-zinc-800 dark:text-white">
                        {{ __('Type') }}
                        @if ($sortColumn === 'type')<span class="text-xs">{{ $sortDirection === 'asc' ? '↑' : '↓' }}</span>@endif
                    </button>
                </flux:table.column>
                <flux:table.column>{{ __('Tax No') }}</flux:table.column>
                <flux:table.column>{{ __('Contact') }}</flux:table.column>
                <flux:table.column>
                    <button type="button" wire:click="sortBy('city')" class="flex items-center gap-1 font-medium text-zinc-800 dark:text-white">
                        {{ __('City') }}
                        @if ($sortColumn === 'city')<span class="text-xs">{{ $sortDirection === 'asc' ? '↑' : '↓' }}</span>@endif
                    </button>
                </flux:table.column>
                <flux:table.column>{{ __('Status') }}</flux:table.column>
                <flux:table.column>{{ __('Actions') }}</flux:table.column>
            </flux:table.columns>
            <flux:table.rows>
                @forelse ($this->paginatedPartners as $partner)
                    <flux:table.row :key="$partner->id">
                        @if ($canWrite)
                            <flux:table.cell>
                                <flux:checkbox
                                    wire:key="partner-select-{{ $partner->id }}"
                                    wire:model.live="selectedIds"
                                    :value="(int) $partner->id"
                                />
                            </flux:table.cell>
                        @endif
                        <flux:table.cell>{{ $partner->id }}</flux:table.cell>
                        <flux:table.cell>{{ $partner->name }}</flux:table.cell>
                        <flux:table.cell>
                            <flux:badge :color="$partner->type->color()" size="sm">{{ $partner->type->label() }}</flux:badge>
                        </flux:table.cell>
                        <flux:table.cell>{{ $partner->tax_no ?? '—' }}</flux:table.cell>
                        <flux:table.cell>
                            <div class="text-sm">
                                @if ($partner->contact_person)
                                    <div>{{ $partner->contact_person }}</div>
                                @endif
                                @if ($partner->phone)
                                    <div class="text-zinc-500">{{ $partner->phone }}</div>
                                @endif
                            </div>
                        </flux:table.cell>
                        <flux:table.cell>{{ $partner->city ?? '—' }}</flux:table.cell>
                        <flux:table.cell>
                            @if ($partner->is_active)
                                <flux:badge color="green" size="sm">{{ __('Active') }}</flux:badge>
                            @else
                                <flux:badge color="zinc" size="sm">{{ __('Inactive') }}</flux:badge>
                            @endif
                        </flux:table.cell>
                        <flux:table.cell>
                            <div class="flex flex-wrap items-center gap-2">
                                @if ($canWrite)
                                    <flux:button type="button" size="sm" variant="ghost" wire:click="startEdit({{ $partner->id }})">
                                        {{ __('Edit') }}
                                    </flux:button>
                                    <flux:button
                                        type="button"
                                        size="sm"
                                        variant="danger"
                                        wire:click="deletePartner({{ $partner->id }})"
                                        wire:confirm="{{ __('Delete this business partner?') }}"
                                    >
                                        {{ __('Delete') }}
                                    </flux:button>
                                @endif
                            </div>
                        </flux:table.cell>
                    </flux:table.row>
                @empty
                    <flux:table.row>
                        <flux:table.cell colspan="{{ $canWrite ? 9 : 8 }}">{{ __('No business partners yet.') }}</flux:table.cell>
                    </flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>
        <div class="mt-4">
            {{ $this->paginatedPartners->links() }}
        </div>
    </flux:card>
</div>
