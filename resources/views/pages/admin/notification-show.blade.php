<?php

use App\Models\AppNotification;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Lazy;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Notification')] class extends Component
{
    public AppNotification $notification;

    public function mount(AppNotification $notification): void
    {
        $user = Auth::user();
        if (! $user || $notification->user_id !== $user->id) {
            abort(403);
        }

        $notification->markRead();
    }

    public function deleteAndReturn(): void
    {
        $user = Auth::user();
        if (! $user || $this->notification->user_id !== $user->id) {
            abort(403);
        }

        $this->notification->delete();
        $this->redirect(route('admin.notifications.index'), navigate: true);
    }
}; ?>

<div class="mx-auto flex w-full max-w-7xl flex-col gap-6 p-4 lg:p-8">

    <div class="flex items-center gap-3">
        <flux:button :href="route('admin.notifications.index')" variant="ghost" wire:navigate icon="arrow-left" size="sm">
            {{ __('Notifications') }}
        </flux:button>
    </div>

    <flux:card class="!p-6">
        {{-- Header --}}
        <div class="mb-6 flex items-start justify-between gap-4">
            <div class="flex items-start gap-3">
                @php
                    $iconColor = match ($notification->type) {
                        'warning' => 'text-amber-500',
                        'error'   => 'text-red-500',
                        'success' => 'text-emerald-500',
                        default   => 'text-blue-500',
                    };
                    $iconName = match ($notification->type) {
                        'warning' => 'exclamation-triangle',
                        'error'   => 'x-circle',
                        'success' => 'check-circle',
                        default   => 'information-circle',
                    };
                @endphp
                <flux:icon :icon="$iconName" class="mt-0.5 size-6 shrink-0 {{ $iconColor }}" />
                <div>
                    <flux:heading size="lg">{{ $notification->title }}</flux:heading>
                    <flux:text class="mt-1 text-sm text-zinc-500">
                        {{ $notification->created_at->diffForHumans() }}
                        <span class="mx-1">·</span>
                        {{ $notification->created_at->format('d M Y, H:i') }}
                    </flux:text>
                </div>
            </div>
            <flux:badge
                variant="outline"
                color="{{ match ($notification->type) { 'warning' => 'yellow', 'error' => 'red', 'success' => 'green', default => 'blue' } }}"
                size="sm"
                class="shrink-0"
            >
                {{ ucfirst($notification->type ?? 'info') }}
            </flux:badge>
        </div>

        {{-- Body --}}
        @if ($notification->body)
            <div class="mb-6 rounded-lg border border-zinc-200 bg-zinc-50 p-4 text-sm leading-relaxed text-zinc-700 dark:border-zinc-700 dark:bg-zinc-800/60 dark:text-zinc-300">
                {!! nl2br(e($notification->body)) !!}
            </div>
        @endif

        {{-- Extra data --}}
        @if (! empty($notification->data))
            <div class="mb-6">
                <flux:heading size="sm" class="mb-2 text-zinc-500">{{ __('Details') }}</flux:heading>
                <div class="divide-y divide-zinc-100 rounded-lg border border-zinc-200 text-sm dark:divide-zinc-800 dark:border-zinc-700">
                    @foreach ($notification->data as $key => $value)
                        <div class="flex items-center gap-4 px-4 py-2">
                            <span class="w-40 shrink-0 font-medium text-zinc-500">{{ ucwords(str_replace('_', ' ', $key)) }}</span>
                            <span class="text-zinc-700 dark:text-zinc-200">{{ is_array($value) ? json_encode($value) : $value }}</span>
                        </div>
                    @endforeach
                </div>
            </div>
        @endif

        {{-- Link --}}
        @if ($notification->url)
            <div class="mb-6">
                <flux:button :href="$notification->url" variant="filled" wire:navigate icon="arrow-top-right-on-square">
                    {{ __('Go to related page') }}
                </flux:button>
            </div>
        @endif

        {{-- Read info --}}
        <div class="flex items-center justify-between border-t border-zinc-100 pt-4 dark:border-zinc-800">
            <flux:text class="text-xs text-zinc-400">
                @if ($notification->read_at)
                    {{ __('Read at :time', ['time' => $notification->read_at->format('d M Y, H:i')]) }}
                @else
                    {{ __('Marked as read now') }}
                @endif
            </flux:text>

            <flux:button wire:click="deleteAndReturn" variant="ghost" size="sm" icon="trash" class="text-red-500 hover:text-red-700">
                {{ __('Delete') }}
            </flux:button>
        </div>
    </flux:card>
</div>
