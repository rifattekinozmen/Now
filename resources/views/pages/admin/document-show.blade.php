<?php

use App\Enums\DocumentCategory;
use App\Enums\DocumentFileType;
use App\Models\Document;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\WithFileUploads;

new #[Title('Document')] class extends Component
{
    use WithFileUploads;

    #[Locked]
    public int $documentId;

    public string $title = '';
    public string $category = '';
    public string $notes = '';
    public ?string $expires_at = null;
    public bool $editing = false;
    public $newFile = null;

    public function mount(Document $document): void
    {
        Gate::authorize('view', $document);
        $this->documentId = $document->id;
        $this->fill([
            'title' => $document->title,
            'category' => $document->category?->value ?? 'other',
            'notes' => $document->notes ?? '',
            'expires_at' => $document->expires_at?->format('Y-m-d'),
        ]);
    }

    public function document(): Document
    {
        return Document::query()->with(['uploader:id,name', 'documentable'])->findOrFail($this->documentId);
    }

    public function startEdit(): void
    {
        Gate::authorize('update', $this->document());
        $this->editing = true;
    }

    public function cancelEdit(): void
    {
        $this->editing = false;
        $this->newFile = null;
        $doc = $this->document();
        $this->title = $doc->title;
        $this->category = $doc->category?->value ?? 'other';
        $this->notes = $doc->notes ?? '';
        $this->expires_at = $doc->expires_at?->format('Y-m-d');
    }

    public function save(): void
    {
        $doc = $this->document();
        Gate::authorize('update', $doc);

        $validated = $this->validate([
            'title'      => ['required', 'string', 'max:255'],
            'category'   => ['required', 'string'],
            'notes'      => ['nullable', 'string', 'max:2000'],
            'expires_at' => ['nullable', 'date'],
            'newFile'    => ['nullable', 'file', 'max:20480'],
        ]);

        $data = [
            'title'      => $validated['title'],
            'category'   => $validated['category'],
            'notes'      => $validated['notes'] ?: null,
            'expires_at' => $validated['expires_at'] ?: null,
        ];

        if ($this->newFile !== null) {
            if ($doc->file_path) {
                Storage::disk('local')->delete($doc->file_path);
            }
            $path = $this->newFile->store('documents', 'local');
            $data['file_path'] = $path;
            $data['file_size'] = $this->newFile->getSize();
            $data['file_type'] = DocumentFileType::fromMime($this->newFile->getMimeType() ?? '')->value;
        }

        $doc->update($data);
        $this->editing = false;
        $this->newFile = null;
        $this->dispatch('$refresh');
    }

    public function delete(): void
    {
        $doc = $this->document();
        Gate::authorize('delete', $doc);
        if ($doc->file_path) {
            Storage::disk('local')->delete($doc->file_path);
        }
        $doc->delete();
        $this->redirect(route('admin.documents.index'), navigate: true);
    }

    public function downloadUrl(): ?string
    {
        $doc = $this->document();
        if (! $doc->file_path) {
            return null;
        }

        return route('admin.documents.download', $doc->id);
    }
}; ?>

<div class="mx-auto flex w-full max-w-7xl flex-col gap-6 p-4 lg:p-8">

    <x-admin.page-header
        :heading="$this->document()->title"
        :description="$this->document()->category?->name ?? ''"
    >
        <x-slot name="actions">
            @can('update', $this->document())
                <flux:button variant="outline" wire:click="startEdit" icon="pencil">{{ __('Edit') }}</flux:button>
            @endcan
            @can('delete', $this->document())
                <flux:button variant="danger" wire:click="delete"
                    wire:confirm="{{ __('Delete this document? This action cannot be undone.') }}"
                    icon="trash">{{ __('Delete') }}</flux:button>
            @endcan
            <flux:button :href="route('admin.documents.index')" variant="ghost" wire:navigate>{{ __('Back') }}</flux:button>
        </x-slot>
    </x-admin.page-header>

    @if ($editing)
        <flux:card class="p-4">
            <flux:heading size="base" class="mb-4">{{ __('Edit document') }}</flux:heading>
            <form wire:submit="save" class="grid gap-4 sm:grid-cols-2">
                <flux:input wire:model="title" :label="__('Title')" required class="sm:col-span-2" />
                <flux:select wire:model="category" :label="__('Category')">
                    @foreach (DocumentCategory::cases() as $cat)
                        <option value="{{ $cat->value }}">{{ $cat->name }}</option>
                    @endforeach
                </flux:select>
                <flux:input wire:model="expires_at" type="date" :label="__('Expiry date')" />
                <flux:textarea wire:model="notes" :label="__('Notes')" rows="3" class="sm:col-span-2" />
                <div class="sm:col-span-2">
                    <flux:label>{{ __('Replace file (optional)') }}</flux:label>
                    <input type="file" wire:model="newFile" class="mt-1 block w-full text-sm text-zinc-600 dark:text-zinc-400" />
                    @error('newFile') <p class="mt-1 text-xs text-red-500">{{ $message }}</p> @enderror
                </div>
                <div class="flex gap-2 sm:col-span-2">
                    <flux:button type="submit" variant="primary">{{ __('Save') }}</flux:button>
                    <flux:button type="button" variant="ghost" wire:click="cancelEdit">{{ __('Cancel') }}</flux:button>
                </div>
            </form>
        </flux:card>
    @endif

    <div class="grid gap-4 sm:grid-cols-2">
        <flux:card class="p-4">
            <flux:heading size="base" class="mb-3">{{ __('Document details') }}</flux:heading>
            <dl class="space-y-2 text-sm">
                <div class="flex justify-between">
                    <dt class="text-zinc-500">{{ __('Title') }}</dt>
                    <dd class="font-medium">{{ $this->document()->title }}</dd>
                </div>
                <div class="flex justify-between">
                    <dt class="text-zinc-500">{{ __('Category') }}</dt>
                    <dd>
                        <flux:badge color="blue" size="sm">{{ $this->document()->category?->name ?? '—' }}</flux:badge>
                    </dd>
                </div>
                <div class="flex justify-between">
                    <dt class="text-zinc-500">{{ __('File type') }}</dt>
                    <dd>{{ $this->document()->file_type?->value ?? '—' }}</dd>
                </div>
                <div class="flex justify-between">
                    <dt class="text-zinc-500">{{ __('File size') }}</dt>
                    <dd>{{ $this->document()->file_size ? number_format($this->document()->file_size / 1024, 1).' KB' : '—' }}</dd>
                </div>
                <div class="flex justify-between">
                    <dt class="text-zinc-500">{{ __('Expires at') }}</dt>
                    <dd class="{{ $this->document()->isExpired() ? 'text-red-500 font-semibold' : ($this->document()->isExpiringSoon() ? 'text-amber-600 font-semibold' : '') }}">
                        {{ $this->document()->expires_at?->format('d.m.Y') ?? __('No expiry') }}
                        @if($this->document()->isExpired())
                            <flux:badge color="red" size="sm" class="ms-1">{{ __('Expired') }}</flux:badge>
                        @elseif($this->document()->isExpiringSoon())
                            <flux:badge color="yellow" size="sm" class="ms-1">{{ __('Expiring soon') }}</flux:badge>
                        @endif
                    </dd>
                </div>
                <div class="flex justify-between">
                    <dt class="text-zinc-500">{{ __('Uploaded by') }}</dt>
                    <dd>{{ $this->document()->uploader?->name ?? '—' }}</dd>
                </div>
                <div class="flex justify-between">
                    <dt class="text-zinc-500">{{ __('Uploaded at') }}</dt>
                    <dd>{{ $this->document()->created_at?->format('d.m.Y H:i') }}</dd>
                </div>
            </dl>

            @if ($this->document()->file_path)
                <div class="mt-4">
                    <flux:button
                        :href="route('admin.documents.download', $this->document()->id)"
                        variant="primary"
                        icon="arrow-down-tray"
                        size="sm"
                    >
                        {{ __('Download') }}
                    </flux:button>
                </div>
            @endif
        </flux:card>

        <flux:card class="p-4">
            <flux:heading size="base" class="mb-3">{{ __('Attached to') }}</flux:heading>
            @if ($this->document()->documentable)
                <dl class="space-y-2 text-sm">
                    <div class="flex justify-between">
                        <dt class="text-zinc-500">{{ __('Type') }}</dt>
                        <dd class="font-medium">{{ class_basename($this->document()->documentable_type) }}</dd>
                    </div>
                    <div class="flex justify-between">
                        <dt class="text-zinc-500">{{ __('ID') }}</dt>
                        <dd>#{{ $this->document()->documentable_id }}</dd>
                    </div>
                    @if (method_exists($this->document()->documentable, 'getDisplayName'))
                        <div class="flex justify-between">
                            <dt class="text-zinc-500">{{ __('Name') }}</dt>
                            <dd>{{ $this->document()->documentable->getDisplayName() }}</dd>
                        </div>
                    @endif
                </dl>
            @else
                <flux:text class="text-zinc-500 text-sm">{{ __('Not attached to any record.') }}</flux:text>
            @endif

            @if ($this->document()->notes)
                <div class="mt-4 border-t border-zinc-100 pt-4 dark:border-zinc-800">
                    <flux:text class="text-xs font-medium text-zinc-500 mb-1">{{ __('Notes') }}</flux:text>
                    <flux:text class="text-sm">{{ $this->document()->notes }}</flux:text>
                </div>
            @endif
        </flux:card>
    </div>

</div>
