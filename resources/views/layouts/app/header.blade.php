<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        @include('partials.head')
    </head>
    <body class="min-h-screen antialiased">
        <div class="min-h-screen bg-white dark:bg-zinc-800">
        <flux:header container class="border-b border-zinc-200 bg-zinc-50 dark:border-zinc-700 dark:bg-zinc-900">
            <flux:sidebar.toggle class="lg:hidden mr-2" icon="bars-2" inset="left" />

            <x-app-logo href="{{ route('dashboard') }}" wire:navigate />

            <flux:navbar class="-mb-px max-lg:hidden">
                <flux:navbar.item icon="layout-grid" :href="route('dashboard')" :current="request()->routeIs('dashboard')" wire:navigate>
                    {{ __('Dashboard') }}
                </flux:navbar.item>
            </flux:navbar>

            <flux:spacer />

            <flux:navbar class="me-1.5 space-x-0.5 rtl:space-x-reverse py-0!">
                <flux:tooltip :content="__('Search') . ' (Ctrl+K)'" position="bottom">
                    <flux:navbar.item class="!h-10 [&>div>svg]:size-5" icon="magnifying-glass" href="#" :label="__('Search')" />
                </flux:tooltip>
                @canany([\App\Authorization\LogisticsPermission::ADMIN, \App\Authorization\LogisticsPermission::VIEW])
                    <flux:modal.trigger name="quick-actions">
                        <flux:tooltip :content="__('Quick actions')" position="bottom">
                            <flux:navbar.item class="!h-10 [&>div>svg]:size-5" icon="bolt" :label="__('Quick actions')" />
                        </flux:tooltip>
                    </flux:modal.trigger>
                @endcanany
            </flux:navbar>

            {{-- Quick Actions Modal --}}
            @canany([\App\Authorization\LogisticsPermission::ADMIN, \App\Authorization\LogisticsPermission::VIEW])
                <flux:modal name="quick-actions" class="md:w-[480px]">
                    <div class="space-y-4 p-2">
                        <flux:heading size="lg">⚡ {{ __('Quick actions') }}</flux:heading>
                        <div class="grid grid-cols-3 gap-3">
                            @can(\App\Authorization\LogisticsPermission::ADMIN)
                                <a href="{{ route('admin.orders.index') }}" wire:navigate
                                   class="flex flex-col items-center gap-2 rounded-xl border border-zinc-200 p-4 text-sm font-medium text-zinc-700 transition hover:border-primary hover:bg-primary/5 dark:border-zinc-700 dark:text-zinc-300">
                                    <flux:icon name="clipboard-document-list" class="size-6 text-primary" />
                                    {{ __('New order') }}
                                </a>
                                <a href="{{ route('admin.customers.index') }}" wire:navigate
                                   class="flex flex-col items-center gap-2 rounded-xl border border-zinc-200 p-4 text-sm font-medium text-zinc-700 transition hover:border-primary hover:bg-primary/5 dark:border-zinc-700 dark:text-zinc-300">
                                    <flux:icon name="users" class="size-6 text-primary" />
                                    {{ __('New customer') }}
                                </a>
                                <a href="{{ route('admin.shipments.index') }}" wire:navigate
                                   class="flex flex-col items-center gap-2 rounded-xl border border-zinc-200 p-4 text-sm font-medium text-zinc-700 transition hover:border-primary hover:bg-primary/5 dark:border-zinc-700 dark:text-zinc-300">
                                    <flux:icon name="cube" class="size-6 text-primary" />
                                    {{ __('New shipment') }}
                                </a>
                                <a href="{{ route('admin.fuel-intakes.index') }}" wire:navigate
                                   class="flex flex-col items-center gap-2 rounded-xl border border-zinc-200 p-4 text-sm font-medium text-zinc-700 transition hover:border-primary hover:bg-primary/5 dark:border-zinc-700 dark:text-zinc-300">
                                    <flux:icon name="bolt" class="size-6 text-amber-500" />
                                    {{ __('Fuel intakes') }}
                                </a>
                                <a href="{{ route('admin.finance.vouchers.index') }}" wire:navigate
                                   class="flex flex-col items-center gap-2 rounded-xl border border-zinc-200 p-4 text-sm font-medium text-zinc-700 transition hover:border-primary hover:bg-primary/5 dark:border-zinc-700 dark:text-zinc-300">
                                    <flux:icon name="document-check" class="size-6 text-green-600" />
                                    {{ __('New voucher') }}
                                </a>
                                <a href="{{ route('admin.trip-expenses.index') }}" wire:navigate
                                   class="flex flex-col items-center gap-2 rounded-xl border border-zinc-200 p-4 text-sm font-medium text-zinc-700 transition hover:border-primary hover:bg-primary/5 dark:border-zinc-700 dark:text-zinc-300">
                                    <flux:icon name="receipt-percent" class="size-6 text-orange-500" />
                                    {{ __('Trip expenses') }}
                                </a>
                            @else
                                <a href="{{ route('admin.orders.index') }}" wire:navigate
                                   class="flex flex-col items-center gap-2 rounded-xl border border-zinc-200 p-4 text-sm font-medium text-zinc-700 transition hover:border-primary hover:bg-primary/5 dark:border-zinc-700 dark:text-zinc-300">
                                    <flux:icon name="clipboard-document-list" class="size-6 text-primary" />
                                    {{ __('Orders') }}
                                </a>
                                <a href="{{ route('admin.shipments.index') }}" wire:navigate
                                   class="flex flex-col items-center gap-2 rounded-xl border border-zinc-200 p-4 text-sm font-medium text-zinc-700 transition hover:border-primary hover:bg-primary/5 dark:border-zinc-700 dark:text-zinc-300">
                                    <flux:icon name="cube" class="size-6 text-primary" />
                                    {{ __('Shipments') }}
                                </a>
                                <a href="{{ route('admin.customers.index') }}" wire:navigate
                                   class="flex flex-col items-center gap-2 rounded-xl border border-zinc-200 p-4 text-sm font-medium text-zinc-700 transition hover:border-primary hover:bg-primary/5 dark:border-zinc-700 dark:text-zinc-300">
                                    <flux:icon name="users" class="size-6 text-primary" />
                                    {{ __('Customers') }}
                                </a>
                            @endcan
                        </div>
                    </div>
                </flux:modal>
            @endcanany

            <x-desktop-user-menu />
        </flux:header>

        <!-- Mobile Menu -->
        <flux:sidebar collapsible="mobile" sticky class="lg:hidden border-e border-zinc-200 bg-zinc-50 dark:border-zinc-700 dark:bg-zinc-900">
            <flux:sidebar.header>
                <x-app-logo :sidebar="true" href="{{ route('dashboard') }}" wire:navigate />
                <flux:sidebar.collapse class="in-data-flux-sidebar-on-desktop:not-in-data-flux-sidebar-collapsed-desktop:-mr-2" />
            </flux:sidebar.header>

            <flux:sidebar.nav>
                <flux:sidebar.group :heading="__('Platform')">
                    <flux:sidebar.item icon="home" :href="route('dashboard')" :current="request()->routeIs('dashboard')" wire:navigate>
                        {{ __('Dashboard') }}
                    </flux:sidebar.item>
                    @canany([\App\Authorization\LogisticsPermission::ADMIN, \App\Authorization\LogisticsPermission::VIEW])
                        <flux:sidebar.item icon="users" :href="route('admin.customers.index')" :current="request()->routeIs('admin.customers.*')" wire:navigate>
                            {{ __('Customers') }}
                        </flux:sidebar.item>
                        <flux:sidebar.item icon="truck" :href="route('admin.vehicles.index')" :current="request()->routeIs('admin.vehicles.*')" wire:navigate>
                            {{ __('Vehicles') }}
                        </flux:sidebar.item>
                        <flux:sidebar.item icon="clipboard-document-list" :href="route('admin.orders.index')" :current="request()->routeIs('admin.orders.*')" wire:navigate>
                            {{ __('Orders') }}
                        </flux:sidebar.item>
                        <flux:sidebar.item icon="cube" :href="route('admin.shipments.index')" :current="request()->routeIs('admin.shipments.*')" wire:navigate>
                            {{ __('Shipments') }}
                        </flux:sidebar.item>
                        <flux:sidebar.item icon="hashtag" :href="route('admin.delivery-numbers.index')" :current="request()->routeIs('admin.delivery-numbers.*')" wire:navigate>
                            {{ __('PIN pool') }}
                        </flux:sidebar.item>
                        <flux:sidebar.item icon="calculator" :href="route('admin.finance.index')" :current="request()->routeIs('admin.finance.*')" wire:navigate>
                            {{ __('Finance summary') }}
                        </flux:sidebar.item>
                    @endcanany
                </flux:sidebar.group>
            </flux:sidebar.nav>

            <flux:spacer />

            <flux:sidebar.nav>
                <flux:sidebar.item icon="folder-git-2" href="https://github.com/laravel/livewire-starter-kit" target="_blank">
                    {{ __('Repository') }}
                </flux:sidebar.item>
                <flux:sidebar.item icon="book-open-text" href="https://laravel.com/docs/starter-kits#livewire" target="_blank">
                    {{ __('Documentation') }}
                </flux:sidebar.item>
            </flux:sidebar.nav>
        </flux:sidebar>

        {{ $slot }}

        @fluxScripts
        </div>
    </body>
</html>
