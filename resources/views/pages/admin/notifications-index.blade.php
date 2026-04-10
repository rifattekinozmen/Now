<?php

use App\Models\AppNotification;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

new #[Lazy, Title('Notifications')] class extends Component
{
    use WithPagination;

    public bool $unreadOnly = false;

    public function updatedUnreadOnly(): void { $this->resetPage(); }

    #[Computed]
    public function paginatedNotifications(): LengthAwarePaginator
    {
        $user = Auth::user();
        if (! $user) {
            abort(403);
        }

        $q = AppNotification::query()
            ->forUser($user->id)
            ->orderByDesc('created_at');

        if ($this->unreadOnly) {
            $q->unread();
        }

        return $q->paginate(25);
    }

    #[Computed]
    public function unreadCount(): int
    {
        $user = Auth::user();
        if (! $user) {
            return 0;
        }

        return AppNotification::query()->forUser($user->id)->unread()->count();
    }

    public function markRead(int $id): void
    {
        $user = Auth::user();
        if (! $user) {
            abort(403);
        }

        $notification = AppNotification::query()
            ->forUser($user->id)
            ->findOrFail($id);

        $notification->markRead();

        if ($notification->url) {
            $this->redirect($notification->url);
        }
    }

    public function markAllRead(): void
    {
        $user = Auth::user();
        if (! $user) {
            abort(403);
        }

        AppNotification::query()
            ->forUser($user->id)
            ->unread()
            ->update(['is_read' => true, 'read_at' => now()]);

        $this->resetPage();
    }
}; ?>

<div class="mx-auto flex w-full max-w-7xl flex-col gap-6 p-4 lg:p-8">

    <x-admin.page-header
        :heading="__('Notifications')"
        :description="__('All notifications') . ' — ' . $this->unreadCount . ' ' . __('unread')"
    >
        <x-slot name="actions">
            @if ($this->unreadCount > 0)
                <flux:button type="button" variant="ghost" wire:click="markAllRead">
                    {{ __('Mark all as read') }}
                </flux:button>
            @endif
        </x-slot>
    </x-admin.page-header>

    {{-- Filter --}}
    <div class="flex items-center gap-3">
        <flux:button
            type="button"
            variant="{{ ! $unreadOnly ? 'primary' : 'ghost' }}"
            wire:click="$set('unreadOnly', false)"
            size="sm"
        >
            {{ __('All notifications') }}
        </flux:button>
        <flux:button
            type="button"
            variant="{{ $unreadOnly ? 'primary' : 'ghost' }}"
            wire:click="$set('unreadOnly', true)"
            size="sm"
        >
            {{ __('Unread only') }}
            @if ($this->unreadCount > 0)
                <flux:badge color="red" size="sm" class="ms-1">{{ $this->unreadCount }}</flux:badge>
            @endif
        </flux:button>
    </div>

    {{-- Notifications list --}}
    <flux:card class="divide-y divide-zinc-100 p-0 dark:divide-zinc-800">
        @forelse ($this->paginatedNotifications as $notification)
            <div
                class="flex cursor-pointer items-start gap-3 px-4 py-3 transition-colors hover:bg-zinc-50 dark:hover:bg-zinc-800/50 {{ ! $notification->is_read ? 'bg-blue-50/50 dark:bg-blue-900/10' : '' }}"
                wire:click="markRead({{ $notification->id }})"
            >
                <div class="mt-0.5 flex-shrink-0">
                    @if (! $notification->is_read)
                        <span class="inline-block h-2 w-2 rounded-full bg-blue-500"></span>
                    @else
                        <span class="inline-block h-2 w-2 rounded-full bg-transparent"></span>
                    @endif
                </div>
                <div class="min-w-0 flex-1">
                    <flux:text class="font-medium {{ ! $notification->is_read ? 'text-foreground' : 'text-zinc-500' }}">
                        {{ $notification->title }}
                    </flux:text>
                    @if ($notification->body)
                        <flux:text class="mt-0.5 text-sm text-zinc-500">{{ $notification->body }}</flux:text>
                    @endif
                    <flux:text class="mt-1 text-xs text-zinc-400">
                        {{ $notification->created_at?->diffForHumans() }}
                    </flux:text>
                </div>
                @if ($notification->url)
                    <flux:icon icon="arrow-top-right-on-square" class="mt-1 h-4 w-4 flex-shrink-0 text-zinc-400" />
                @endif
            </div>
        @empty
            <div class="py-12 text-center text-zinc-500">
                {{ __('No notifications yet.') }}
            </div>
        @endforelse
    </flux:card>

    <div>{{ $this->paginatedNotifications->links() }}</div>
</div>
