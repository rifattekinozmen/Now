<?php

use App\Authorization\LogisticsPermission;
use App\Enums\DocumentCategory;
use App\Enums\DocumentFileType;
use App\Models\Document;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\WithFileUploads;
use Livewire\WithPagination;

new #[Lazy, Title('Documents')] class extends Component
{
    use WithFileUploads;
    use WithPagination;

    public ?int $editingId = null;

    // Form fields
    public string $title      = '';
    public string $category   = 'other';
    public ?string $expires_at = null;
    public string $notes      = '';
    public $uploadedFile      = null;

    // Filters
    public string $filterSearch   = '';
    public string $filterCategory = '';
    public string $filterExpiry   = '';

    public bool $filtersOpen = false;

    public string $sortColumn    = 'created_at';
    public string $sortDirection = 'desc';

    /** @var int[] */
    public array $selectedIds = [];

    public function mount(): void
    {
        Gate::authorize('viewAny', Document::class);
    }

    public function updatedFilterSearch(): void { $this->resetPage(); $this->selectedIds = []; }
    public function updatedFilterCategory(): void { $this->resetPage(); }
    public function updatedFilterExpiry(): void { $this->resetPage(); }

    public function sortBy(string $column): void
    {
        $allowed = ['id', 'title', 'category', 'expires_at', 'created_at'];
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

    /**
     * @return array{total: int, expiring_soon: int, expired: int, contracts: int}
     */
    #[Computed]
    public function kpiStats(): array
    {
        return [
            'total'         => Document::query()->count(),
            'expiring_soon' => Document::query()->expiringSoon(30)->count(),
            'expired'       => Document::query()->expired()->count(),
            'contracts'     => Document::query()->where('category', DocumentCategory::Contract->value)->count(),
        ];
    }

    private function documentsQuery(): Builder
    {
        $q = Document::query()->with('uploader');

        if ($this->filterSearch !== '') {
            $term = '%' . addcslashes($this->filterSearch, '%_\\') . '%';
            $q->where('title', 'like', $term);
        }

        if ($this->filterCategory !== '') {
            $q->where('category', $this->filterCategory);
        }

        if ($this->filterExpiry === 'expiring') {
            $q->expiringSoon(30);
        } elseif ($this->filterExpiry === 'expired') {
            $q->expired();
        } elseif ($this->filterExpiry === 'valid') {
            $q->where(function (Builder $sub): void {
                $sub->whereNull('expires_at')
                    ->orWhere('expires_at', '>=', now()->toDateString());
            });
        }

        return $q->orderBy($this->sortColumn, $this->sortDirection)->orderByDesc('id');
    }

    #[Computed]
    public function paginatedDocuments(): LengthAwarePaginator
    {
        return $this->documentsQuery()->paginate(20);
    }

    public function toggleSelectPage(): void
    {
        $pageIds = $this->paginatedDocuments->pluck('id')->map(fn ($id) => (int) $id)->toArray();
        if ($this->isPageFullySelected()) {
            $this->selectedIds = array_values(array_diff($this->selectedIds, $pageIds));
        } else {
            $this->selectedIds = array_values(array_unique(array_merge($this->selectedIds, $pageIds)));
        }
    }

    public function isPageFullySelected(): bool
    {
        $pageIds = $this->paginatedDocuments->pluck('id')->map(fn ($id) => (int) $id)->toArray();

        return count($pageIds) > 0 && count(array_diff($pageIds, $this->selectedIds)) === 0;
    }

    public function bulkDeleteSelected(): void
    {
        Gate::authorize('viewAny', Document::class);
        $docs = Document::query()->whereIn('id', $this->selectedIds)->get();
        foreach ($docs as $doc) {
            Storage::disk('local')->delete($doc->file_path);
            $doc->delete();
        }
        $count = count($this->selectedIds);
        $this->selectedIds = [];
        session()->flash('bulk_deleted', __('Deleted :count records.', ['count' => $count]));
        $this->resetPage();
    }

    public function startCreate(): void
    {
        Gate::authorize('create', Document::class);
        $this->resetForm();
        $this->editingId = 0;
    }

    public function startEdit(int $id): void
    {
        $doc = Document::query()->findOrFail($id);
        Gate::authorize('update', $doc);

        $this->editingId   = $id;
        $this->title       = $doc->title;
        $this->category    = $doc->category->value;
        $this->expires_at  = $doc->expires_at?->format('Y-m-d');
        $this->notes       = $doc->notes ?? '';
        $this->uploadedFile = null;
    }

    public function cancelForm(): void
    {
        $this->editingId = null;
        $this->resetForm();
    }

    public function save(): void
    {
        $user = auth()->user();
        if (! ($user instanceof \App\Models\User) || ! LogisticsPermission::canWrite($user, LogisticsPermission::DOCUMENTS_WRITE)) {
            abort(403);
        }

        $rules = [
            'title'      => ['required', 'string', 'max:255'],
            'category'   => ['required', 'in:' . implode(',', array_column(DocumentCategory::cases(), 'value'))],
            'expires_at' => ['nullable', 'date'],
            'notes'      => ['nullable', 'string', 'max:1000'],
        ];

        if ($this->editingId === 0) {
            $rules['uploadedFile'] = ['required', 'file', 'max:51200']; // 50 MB
        }

        $validated = $this->validate($rules);

        $data = [
            'title'      => $validated['title'],
            'category'   => $validated['category'],
            'expires_at' => filled($validated['expires_at']) ? $validated['expires_at'] : null,
            'notes'      => filled($validated['notes']) ? $validated['notes'] : null,
        ];

        if ($this->editingId === 0) {
            Gate::authorize('create', Document::class);

            $file      = $this->uploadedFile;
            $mime      = $file->getMimeType() ?? 'application/octet-stream';
            $path      = $file->store('documents', 'local');
            $fileType  = DocumentFileType::fromMime($mime);
            $fileSize  = $file->getSize();

            Document::query()->create(array_merge($data, [
                'file_path'   => $path,
                'file_type'   => $fileType->value,
                'file_size'   => $fileSize,
                'uploaded_by' => $user->id,
            ]));
        } else {
            $doc = Document::query()->findOrFail($this->editingId);
            Gate::authorize('update', $doc);

            if ($this->uploadedFile !== null) {
                Storage::disk('local')->delete($doc->file_path);
                $file     = $this->uploadedFile;
                $mime     = $file->getMimeType() ?? 'application/octet-stream';
                $path     = $file->store('documents', 'local');
                $fileType = DocumentFileType::fromMime($mime);
                $fileSize = $file->getSize();

                $data = array_merge($data, [
                    'file_path'  => $path,
                    'file_type'  => $fileType->value,
                    'file_size'  => $fileSize,
                ]);
            }

            $doc->update($data);
        }

        $this->editingId = null;
        $this->resetForm();
        $this->resetPage();
    }

    public function delete(int $id): void
    {
        $doc = Document::query()->findOrFail($id);
        Gate::authorize('delete', $doc);
        Storage::disk('local')->delete($doc->file_path);
        $doc->delete();
        $this->resetPage();
    }

    private function resetForm(): void
    {
        $this->title        = '';
        $this->category     = 'other';
        $this->expires_at   = null;
        $this->notes        = '';
        $this->uploadedFile = null;
    }
}; ?>

<div class="mx-auto flex w-full max-w-7xl flex-col gap-6 p-4 lg:p-8">
    @php
        $authUser = auth()->user();
        $canWrite = $authUser instanceof \App\Models\User
            && \App\Authorization\LogisticsPermission::canWrite($authUser, \App\Authorization\LogisticsPermission::DOCUMENTS_WRITE);
    @endphp

    @if (session()->has('bulk_deleted'))
        <flux:callout variant="success">{{ session('bulk_deleted') }}</flux:callout>
    @endif

    <x-admin.page-header
        :heading="__('Documents')"
        :description="__('Archive, track expiry and manage company documents.')"
    >
        <x-slot name="actions">
            @if ($canWrite)
                <flux:button type="button" variant="primary" wire:click="startCreate" icon="plus">
                    {{ __('Upload Document') }}
                </flux:button>
            @endif
        </x-slot>
    </x-admin.page-header>

    {{-- KPI Cards --}}
    <div class="grid grid-cols-2 gap-4 lg:grid-cols-4">
        <flux:card class="flex flex-col gap-1 p-4">
            <span class="text-sm text-zinc-500 dark:text-zinc-400">{{ __('Total Documents') }}</span>
            <span class="text-2xl font-bold text-zinc-900 dark:text-zinc-100">{{ $this->kpiStats['total'] }}</span>
        </flux:card>
        <flux:card class="flex flex-col gap-1 p-4">
            <span class="text-sm text-zinc-500 dark:text-zinc-400">{{ __('Expiring (30d)') }}</span>
            <span class="text-2xl font-bold {{ $this->kpiStats['expiring_soon'] > 0 ? 'text-yellow-600' : 'text-zinc-900 dark:text-zinc-100' }}">
                {{ $this->kpiStats['expiring_soon'] }}
            </span>
        </flux:card>
        <flux:card class="flex flex-col gap-1 p-4">
            <span class="text-sm text-zinc-500 dark:text-zinc-400">{{ __('Expired') }}</span>
            <span class="text-2xl font-bold {{ $this->kpiStats['expired'] > 0 ? 'text-red-600' : 'text-zinc-900 dark:text-zinc-100' }}">
                {{ $this->kpiStats['expired'] }}
            </span>
        </flux:card>
        <flux:card class="flex flex-col gap-1 p-4">
            <span class="text-sm text-zinc-500 dark:text-zinc-400">{{ __('Contracts') }}</span>
            <span class="text-2xl font-bold text-zinc-900 dark:text-zinc-100">{{ $this->kpiStats['contracts'] }}</span>
        </flux:card>
    </div>

    {{-- Inline form --}}
    @if ($editingId !== null)
        <flux:card class="p-6">
            <h3 class="mb-4 text-base font-semibold text-zinc-800 dark:text-zinc-200">
                {{ $editingId === 0 ? __('Upload Document') : __('Edit Document') }}
            </h3>
            <form wire:submit.prevent="save" class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
                <flux:field class="sm:col-span-2 lg:col-span-2">
                    <flux:label>{{ __('Title') }}</flux:label>
                    <flux:input wire:model="title" placeholder="{{ __('Document title…') }}" />
                    <flux:error name="title" />
                </flux:field>

                <flux:field>
                    <flux:label>{{ __('Category') }}</flux:label>
                    <flux:select wire:model="category">
                        @foreach (\App\Enums\DocumentCategory::cases() as $cat)
                            <option value="{{ $cat->value }}">{{ $cat->label() }}</option>
                        @endforeach
                    </flux:select>
                    <flux:error name="category" />
                </flux:field>

                <flux:field>
                    <flux:label>{{ __('Expiry Date') }}</flux:label>
                    <flux:input type="date" wire:model="expires_at" />
                    <flux:error name="expires_at" />
                </flux:field>

                <flux:field class="sm:col-span-2 lg:col-span-2">
                    <flux:label>{{ __('File') }}{{ $editingId > 0 ? ' (' . __('leave empty to keep current') . ')' : '' }}</flux:label>
                    <input type="file" wire:model="uploadedFile" class="block w-full text-sm text-zinc-600 file:mr-3 file:rounded-md file:border-0 file:bg-zinc-100 file:px-3 file:py-1.5 file:text-sm file:font-medium file:text-zinc-700 hover:file:bg-zinc-200 dark:text-zinc-400 dark:file:bg-zinc-800 dark:file:text-zinc-300" />
                    <flux:error name="uploadedFile" />
                </flux:field>

                <flux:field class="sm:col-span-2 lg:col-span-3">
                    <flux:label>{{ __('Notes') }}</flux:label>
                    <flux:textarea wire:model="notes" rows="2" />
                    <flux:error name="notes" />
                </flux:field>

                <div class="flex gap-2 sm:col-span-2 lg:col-span-3">
                    <flux:button type="submit" variant="primary">{{ __('Save') }}</flux:button>
                    <flux:button type="button" wire:click="cancelForm" variant="ghost">{{ __('Cancel') }}</flux:button>
                </div>
            </form>
        </flux:card>
    @endif

    {{-- Filters --}}
    <flux:card class="p-4">
        <div class="flex flex-wrap items-center gap-3">
            <flux:input
                wire:model.live.debounce.300ms="filterSearch"
                placeholder="{{ __('Search title…') }}"
                icon="magnifying-glass"
                class="w-56"
            />
            <flux:select wire:model.live="filterCategory" class="w-44">
                <option value="">{{ __('All Categories') }}</option>
                @foreach (\App\Enums\DocumentCategory::cases() as $cat)
                    <option value="{{ $cat->value }}">{{ $cat->label() }}</option>
                @endforeach
            </flux:select>
            <flux:select wire:model.live="filterExpiry" class="w-44">
                <option value="">{{ __('All Statuses') }}</option>
                <option value="valid">{{ __('Valid') }}</option>
                <option value="expiring">{{ __('Expiring Soon') }}</option>
                <option value="expired">{{ __('Expired') }}</option>
            </flux:select>
        </div>
    </flux:card>

    {{-- Bulk actions --}}
    @if (count($selectedIds) > 0 && $canWrite)
        <div class="flex items-center gap-3 rounded-lg border border-red-200 bg-red-50 px-4 py-2 dark:border-red-800 dark:bg-red-950">
            <span class="text-sm text-red-700 dark:text-red-300">
                {{ __(':count selected', ['count' => count($selectedIds)]) }}
            </span>
            <flux:button size="sm" variant="danger" wire:click="bulkDeleteSelected" wire:confirm="{{ __('Delete selected documents?') }}">
                {{ __('Delete Selected') }}
            </flux:button>
        </div>
    @endif

    {{-- Table --}}
    <flux:card class="overflow-hidden p-0">
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b border-zinc-200 bg-zinc-50 text-left text-xs font-semibold uppercase tracking-wider text-zinc-500 dark:border-zinc-700 dark:bg-zinc-800 dark:text-zinc-400">
                        @if ($canWrite)
                            <th class="px-4 py-3">
                                <flux:checkbox wire:click="toggleSelectPage" :checked="$this->isPageFullySelected()" />
                            </th>
                        @endif
                        <th class="cursor-pointer px-4 py-3 hover:text-zinc-700" wire:click="sortBy('title')">
                            {{ __('Title') }}
                            @if ($sortColumn === 'title')<span>{{ $sortDirection === 'asc' ? '↑' : '↓' }}</span>@endif
                        </th>
                        <th class="cursor-pointer px-4 py-3 hover:text-zinc-700" wire:click="sortBy('category')">
                            {{ __('Category') }}
                        </th>
                        <th class="px-4 py-3">{{ __('Type') }}</th>
                        <th class="cursor-pointer px-4 py-3 hover:text-zinc-700" wire:click="sortBy('expires_at')">
                            {{ __('Expires') }}
                        </th>
                        <th class="px-4 py-3">{{ __('Uploaded By') }}</th>
                        <th class="cursor-pointer px-4 py-3 hover:text-zinc-700" wire:click="sortBy('created_at')">
                            {{ __('Date') }}
                        </th>
                        <th class="px-4 py-3 text-right">{{ __('Actions') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-zinc-100 dark:divide-zinc-800">
                    @forelse ($this->paginatedDocuments as $doc)
                        @php
                            $rowClass = $doc->isExpired()
                                ? 'bg-red-50 dark:bg-red-950/20'
                                : ($doc->isExpiringSoon() ? 'bg-yellow-50 dark:bg-yellow-950/20' : '');
                        @endphp
                        <tr class="hover:bg-zinc-50 dark:hover:bg-zinc-800/50 {{ $rowClass }}" wire:key="doc-{{ $doc->id }}">
                            @if ($canWrite)
                                <td class="px-4 py-3">
                                    <flux:checkbox wire:model.live="selectedIds" value="{{ $doc->id }}" />
                                </td>
                            @endif
                            <td class="max-w-xs px-4 py-3 font-medium text-zinc-900 dark:text-zinc-100">
                                {{ $doc->title }}
                            </td>
                            <td class="px-4 py-3">
                                <flux:badge color="{{ $doc->category->color() }}" size="sm">
                                    {{ $doc->category->label() }}
                                </flux:badge>
                            </td>
                            <td class="px-4 py-3 text-zinc-500 dark:text-zinc-400">
                                {{ $doc->file_type->label() }}
                            </td>
                            <td class="px-4 py-3">
                                @if ($doc->expires_at)
                                    <span class="{{ $doc->isExpired() ? 'font-semibold text-red-600' : ($doc->isExpiringSoon() ? 'font-semibold text-yellow-600' : 'text-zinc-600 dark:text-zinc-400') }}">
                                        {{ $doc->expires_at->format('d M Y') }}
                                    </span>
                                @else
                                    <span class="text-zinc-400">—</span>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-zinc-600 dark:text-zinc-400">
                                {{ $doc->uploader?->name ?? '—' }}
                            </td>
                            <td class="px-4 py-3 text-zinc-500 dark:text-zinc-400">
                                {{ $doc->created_at->format('d M Y') }}
                            </td>
                            <td class="px-4 py-3 text-right">
                                <div class="flex justify-end gap-1">
                                    <flux:button size="xs" variant="ghost" :href="route('admin.documents.show', $doc->id)" wire:navigate title="{{ __('View') }}">
                                        <flux:icon name="eye" variant="micro" />
                                    </flux:button>
                                    @if ($canWrite)
                                        <flux:button size="xs" variant="ghost" wire:click="startEdit({{ $doc->id }})" title="{{ __('Edit') }}">
                                            <flux:icon name="pencil-square" variant="micro" />
                                        </flux:button>
                                    @endif
                                    @can('delete', $doc)
                                        <flux:button size="xs" variant="ghost" wire:click="delete({{ $doc->id }})" wire:confirm="{{ __('Delete this document?') }}" title="{{ __('Delete') }}">
                                            <flux:icon name="trash" variant="micro" class="text-red-500" />
                                        </flux:button>
                                    @endcan
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="{{ $canWrite ? 8 : 7 }}" class="px-4 py-8 text-center text-zinc-400 dark:text-zinc-500">
                                {{ __('No documents found.') }}
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($this->paginatedDocuments->hasPages())
            <div class="border-t border-zinc-200 px-4 py-3 dark:border-zinc-700">
                {{ $this->paginatedDocuments->links() }}
            </div>
        @endif
    </flux:card>
</div>
