<?php

use App\Models\Customer;
use App\Models\Document;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

new #[Lazy, Title('My Documents')] class extends Component
{
    use WithPagination;

    public string $filterSearch = '';

    public function mount(): void
    {
        if (! auth()->user()?->customer_id) {
            abort(403);
        }
    }

    public function updatedFilterSearch(): void { $this->resetPage(); }

    private function customerId(): int
    {
        return (int) auth()->user()->customer_id;
    }

    /**
     * @return array{total: int, expiring_soon: int, expired: int}
     */
    #[Computed]
    public function kpiStats(): array
    {
        $cid = $this->customerId();
        $base = Document::query()
            ->where('documentable_type', Customer::class)
            ->where('documentable_id', $cid);

        return [
            'total'        => (int) $base->count(),
            'expiring_soon' => (int) (clone $base)
                ->whereNotNull('expires_at')
                ->where('expires_at', '>', now())
                ->where('expires_at', '<=', now()->addDays(30))
                ->count(),
            'expired' => (int) (clone $base)
                ->whereNotNull('expires_at')
                ->where('expires_at', '<', now())
                ->count(),
        ];
    }

    #[Computed]
    public function paginatedDocuments(): \Illuminate\Pagination\LengthAwarePaginator
    {
        $cid = $this->customerId();
        $q = Document::query()
            ->where('documentable_type', Customer::class)
            ->where('documentable_id', $cid)
            ->orderByDesc('created_at');

        if ($this->filterSearch !== '') {
            $term = '%'.addcslashes($this->filterSearch, '%_\\').'%';
            $q->where(function ($qq) use ($term): void {
                $qq->where('title', 'like', $term)
                    ->orWhere('category', 'like', $term);
            });
        }

        return $q->paginate(20);
    }
}; ?>

<div class="flex flex-col gap-6">
    <flux:heading size="xl">{{ __('My Documents') }}</flux:heading>

    {{-- KPI Cards --}}
    <div class="grid gap-4 sm:grid-cols-3">
        <flux:card>
            <flux:heading>{{ __('Total') }}</flux:heading>
            <p class="mt-1 text-2xl font-bold">{{ $this->kpiStats['total'] }}</p>
        </flux:card>
        <flux:card>
            <flux:heading>{{ __('Expiring in 30 days') }}</flux:heading>
            <p class="mt-1 text-2xl font-bold text-yellow-600">{{ $this->kpiStats['expiring_soon'] }}</p>
        </flux:card>
        <flux:card>
            <flux:heading>{{ __('Expired') }}</flux:heading>
            <p class="mt-1 text-2xl font-bold text-red-600">{{ $this->kpiStats['expired'] }}</p>
        </flux:card>
    </div>

    {{-- Search --}}
    <flux:input wire:model.live.debounce.400ms="filterSearch" :label="__('Search title / category')" />

    {{-- Table --}}
    <flux:card>
        <flux:table>
            <flux:table.columns>
                <flux:table.column>{{ __('Title') }}</flux:table.column>
                <flux:table.column>{{ __('Category') }}</flux:table.column>
                <flux:table.column>{{ __('File type') }}</flux:table.column>
                <flux:table.column>{{ __('Expires at') }}</flux:table.column>
                <flux:table.column>{{ __('Upload date') }}</flux:table.column>
                <flux:table.column>{{ __('Download') }}</flux:table.column>
            </flux:table.columns>
            <flux:table.rows>
                @forelse ($this->paginatedDocuments as $doc)
                    <flux:table.row :key="$doc->id">
                        <flux:table.cell class="font-medium">{{ $doc->title }}</flux:table.cell>
                        <flux:table.cell>{{ $doc->category ?? '—' }}</flux:table.cell>
                        <flux:table.cell>{{ strtoupper($doc->file_type ?? '—') }}</flux:table.cell>
                        <flux:table.cell>
                            @if ($doc->expires_at)
                                <span class="{{ $doc->expires_at->isPast() ? 'text-red-600' : ($doc->expires_at->diffInDays() <= 30 ? 'text-yellow-600' : '') }}">
                                    {{ $doc->expires_at->format('d M Y') }}
                                </span>
                            @else
                                —
                            @endif
                        </flux:table.cell>
                        <flux:table.cell>{{ $doc->created_at->format('d M Y') }}</flux:table.cell>
                        <flux:table.cell>
                            <a href="{{ route('admin.documents.download', $doc) }}"
                               class="inline-flex items-center gap-1 text-sm text-blue-600 hover:underline"
                               target="_blank">
                                ⬇ {{ __('Download') }}
                            </a>
                        </flux:table.cell>
                    </flux:table.row>
                @empty
                    <flux:table.row>
                        <flux:table.cell colspan="6" class="text-center text-zinc-500">
                            {{ __('No documents found.') }}
                        </flux:table.cell>
                    </flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>
        <div class="mt-4">
            {{ $this->paginatedDocuments->links() }}
        </div>
    </flux:card>
</div>
