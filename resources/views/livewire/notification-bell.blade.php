<div>
    @if ($this->unreadCount > 0)
        <flux:tooltip :content="__('Notifications') . ' (' . $this->unreadCount . ')'">
            <flux:button
                variant="ghost"
                icon="bell"
                :href="route('admin.notifications.index')"
                wire:navigate
                class="relative"
            >
                <span class="absolute -right-1 -top-1 flex h-4 w-4 items-center justify-center rounded-full bg-red-500 text-[10px] font-bold text-white">
                    {{ $this->unreadCount > 9 ? '9+' : $this->unreadCount }}
                </span>
            </flux:button>
        </flux:tooltip>
    @else
        <flux:button variant="ghost" icon="bell" :href="route('admin.notifications.index')" wire:navigate />
    @endif
</div>
