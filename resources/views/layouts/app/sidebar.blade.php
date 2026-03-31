<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        @include('partials.head')
    </head>
    <body class="min-h-screen antialiased">
        {{-- Livewire 4 (debug): single element root under <body> for full-page components --}}
        <div class="flex min-h-screen w-full bg-background dark:bg-zinc-800">
        {{-- @persist: navigasyonda sidebar DOM yeniden boyanmaz (daha hızlı). :current + wire:current.ignore menüyü kilitliyordu; bunun yerine wire:current ile Livewire yolu eşleştirir. --}}
        @persist('app-sidebar')
        <flux:sidebar sticky :collapsible="true" class="border-e border-border-app bg-card dark:border-zinc-700">
            <flux:sidebar.header>
                <x-app-logo :sidebar="true" href="{{ route('dashboard') }}" wire:navigate />
                <flux:sidebar.collapse class="in-data-flux-sidebar-on-desktop:not-in-data-flux-sidebar-collapsed-desktop:-mr-2" />
            </flux:sidebar.header>

            <flux:sidebar.nav>
                <flux:sidebar.group :heading="__('Platform')" class="grid">
                    <flux:sidebar.item icon="home" :href="route('dashboard')" wire:navigate wire:current.exact="font-medium">
                        {{ __('Dashboard') }}
                    </flux:sidebar.item>
                    <flux:sidebar.item icon="bell" :href="route('admin.notifications.index')" wire:navigate wire:current.exact="font-medium">
                        {{ __('Notifications') }}
                    </flux:sidebar.item>
                </flux:sidebar.group>

                @canany([\App\Authorization\LogisticsPermission::ADMIN, \App\Authorization\LogisticsPermission::VIEW])

                    {{-- Lojistik Operasyon --}}
                    <flux:sidebar.group :heading="__('Operations')" expandable class="grid">
                        <flux:sidebar.item icon="users" :href="route('admin.customers.index')" wire:navigate wire:current="font-medium">
                            {{ __('Customers') }}
                        </flux:sidebar.item>
                        <flux:sidebar.item icon="clipboard-document-list" :href="route('admin.orders.index')" wire:navigate wire:current="font-medium">
                            {{ __('Orders') }}
                        </flux:sidebar.item>
                        <flux:sidebar.item icon="cube" :href="route('admin.shipments.index')" wire:navigate wire:current="font-medium">
                            {{ __('Shipments') }}
                        </flux:sidebar.item>
                        <flux:sidebar.item icon="hashtag" :href="route('admin.delivery-numbers.index')" wire:navigate wire:current="font-medium">
                            {{ __('PIN pool') }}
                        </flux:sidebar.item>
                        <flux:sidebar.item icon="truck" :href="route('admin.vehicles.index')" wire:navigate wire:current="font-medium">
                            {{ __('Vehicles') }}
                        </flux:sidebar.item>
                        <flux:sidebar.item icon="bolt" :href="route('admin.fuel-intakes.index')" wire:navigate wire:current.exact="font-medium">
                            {{ __('Fuel intakes') }}
                        </flux:sidebar.item>
                        <flux:sidebar.item icon="currency-dollar" :href="route('admin.fuel-prices.index')" wire:navigate wire:current.exact="font-medium">
                            {{ __('Fuel prices') }}
                        </flux:sidebar.item>
                        <flux:sidebar.item icon="archive-box" :href="route('admin.warehouse.index')" wire:navigate wire:current="font-medium">
                            {{ __('Warehouse') }}
                        </flux:sidebar.item>
                        <flux:sidebar.item icon="wrench-screwdriver" :href="route('admin.maintenance.index')" wire:navigate wire:current.exact="font-medium">
                            {{ __('Maintenance') }}
                        </flux:sidebar.item>
                        <flux:sidebar.item icon="chart-bar-square" :href="route('admin.analytics.fleet')" wire:navigate wire:current="font-medium">
                            {{ __('Fleet analytics') }}
                        </flux:sidebar.item>
                        <flux:sidebar.item icon="chart-bar" :href="route('admin.analytics.operations')" wire:navigate wire:current="font-medium">
                            {{ __('Operations analytics') }}
                        </flux:sidebar.item>
                    </flux:sidebar.group>

                    {{-- Finans --}}
                    <flux:sidebar.group :heading="__('Finance')" expandable class="grid">
                        <flux:sidebar.item icon="calculator" :href="route('admin.finance.index')" wire:navigate wire:current.exact="font-medium">
                            {{ __('Finance summary') }}
                        </flux:sidebar.item>
                        <flux:sidebar.item icon="chart-bar" :href="route('admin.finance.reports')" wire:navigate wire:current.exact="font-medium">
                            {{ __('Finance reports') }}
                        </flux:sidebar.item>
                        <flux:sidebar.item icon="calendar-days" :href="route('admin.finance.payment-due-calendar')" wire:navigate wire:current.exact="font-medium">
                            {{ __('Payment due calendar') }}
                        </flux:sidebar.item>
                        <flux:sidebar.item icon="document-text" :href="route('admin.finance.bank-statement-csv')" wire:navigate wire:current.exact="font-medium">
                            {{ __('Bank statement import') }}
                        </flux:sidebar.item>
                        <flux:sidebar.item icon="rectangle-stack" :href="route('admin.finance.chart-accounts.index')" wire:navigate wire:current.exact="font-medium">
                            {{ __('Chart of accounts') }}
                        </flux:sidebar.item>
                        <flux:sidebar.item icon="document-duplicate" :href="route('admin.finance.journal-entries.index')" wire:navigate wire:current.exact="font-medium">
                            {{ __('Journal entries') }}
                        </flux:sidebar.item>
                        <flux:sidebar.item icon="table-cells" :href="route('admin.finance.trial-balance')" wire:navigate wire:current.exact="font-medium">
                            {{ __('Trial balance') }}
                        </flux:sidebar.item>
                        <flux:sidebar.item icon="scale" :href="route('admin.finance.balance-sheet')" wire:navigate wire:current.exact="font-medium">
                            {{ __('Balance sheet summary') }}
                        </flux:sidebar.item>
                        <flux:sidebar.item icon="calendar" :href="route('admin.finance.fiscal-opening-balances.index')" wire:navigate wire:current.exact="font-medium">
                            {{ __('Fiscal opening balances') }}
                        </flux:sidebar.item>
                        <flux:sidebar.item icon="banknotes" :href="route('admin.finance.cash-registers.index')" wire:navigate wire:current.exact="font-medium">
                            {{ __('Cash registers') }}
                        </flux:sidebar.item>
                        <flux:sidebar.item icon="document-check" :href="route('admin.finance.vouchers.index')" wire:navigate wire:current.exact="font-medium">
                            {{ __('Vouchers') }}
                        </flux:sidebar.item>
                    </flux:sidebar.group>

                    {{-- İnsan Kaynakları --}}
                    <flux:sidebar.group :heading="__('HR')" expandable class="grid">
                        <flux:sidebar.item icon="user-group" :href="route('admin.employees.index')" wire:navigate wire:current="font-medium">
                            {{ __('Employees') }}
                        </flux:sidebar.item>
                        <flux:sidebar.item icon="calendar-days" :href="route('admin.hr.leaves.index')" wire:navigate wire:current.exact="font-medium">
                            {{ __('Leave requests') }}
                        </flux:sidebar.item>
                        <flux:sidebar.item icon="banknotes" :href="route('admin.hr.advances.index')" wire:navigate wire:current.exact="font-medium">
                            {{ __('Advances') }}
                        </flux:sidebar.item>
                        <flux:sidebar.item icon="document-text" :href="route('admin.hr.payroll.index')" wire:navigate wire:current.exact="font-medium">
                            {{ __('Payroll') }}
                        </flux:sidebar.item>
                        <flux:sidebar.item icon="calendar-days" :href="route('admin.hr.attendance.index')" wire:navigate wire:current.exact="font-medium">
                            {{ __('Attendance') }}
                        </flux:sidebar.item>
                    </flux:sidebar.group>

                @endcanany
            </flux:sidebar.nav>

            <flux:spacer />

            <x-desktop-user-menu class="hidden lg:block" :name="auth()->user()->name" />
        </flux:sidebar>
        @endpersist

        <div class="flex min-h-0 min-w-0 flex-1 flex-col">
        <livewire:global-search />
        @auth
            @canany([\App\Authorization\LogisticsPermission::ADMIN, \App\Authorization\LogisticsPermission::VIEW])
                <livewire:notification-bell wire:poll.60s />
            @endcanany
        @endauth
        <!-- Mobile User Menu -->
        <flux:header class="lg:hidden">
            <flux:sidebar.toggle class="lg:hidden" icon="bars-2" inset="left" />

            <flux:spacer />

            <flux:dropdown position="top" align="end">
                <flux:profile
                    :initials="auth()->user()->initials()"
                    icon-trailing="chevron-down"
                />

                <flux:menu>
                    <flux:menu.radio.group>
                        <div class="p-0 text-sm font-normal">
                            <div class="flex items-center gap-2 px-1 py-1.5 text-start text-sm">
                                <flux:avatar
                                    :name="auth()->user()->name"
                                    :initials="auth()->user()->initials()"
                                />

                                <div class="grid flex-1 text-start text-sm leading-tight">
                                    <flux:heading class="truncate">{{ auth()->user()->name }}</flux:heading>
                                    <flux:text class="truncate">{{ auth()->user()->email }}</flux:text>
                                </div>
                            </div>
                        </div>
                    </flux:menu.radio.group>

                    <flux:menu.separator />

                    <flux:menu.heading>{{ __('Language') }}</flux:menu.heading>
                    <flux:menu.radio.group>
                        <flux:menu.item :href="route('locale.switch', ['locale' => 'tr'])" icon="language">
                            {{ __('Turkish') }}
                        </flux:menu.item>
                        <flux:menu.item :href="route('locale.switch', ['locale' => 'en'])" icon="language">
                            {{ __('English') }}
                        </flux:menu.item>
                    </flux:menu.radio.group>

                    <flux:menu.separator />

                    <flux:menu.radio.group>
                        <flux:menu.item :href="route('profile.edit')" icon="cog" wire:navigate>
                            {{ __('Settings') }}
                        </flux:menu.item>
                    </flux:menu.radio.group>

                    <flux:menu.separator />

                    <form method="POST" action="{{ route('logout') }}" class="w-full">
                        @csrf
                        <flux:menu.item
                            as="button"
                            type="submit"
                            icon="arrow-right-start-on-rectangle"
                            class="w-full cursor-pointer"
                            data-test="logout-button"
                        >
                            {{ __('Log out') }}
                        </flux:menu.item>
                    </form>
                </flux:menu>
            </flux:dropdown>
        </flux:header>

        {{ $slot }}
        </div>

        @fluxScripts
        </div>
    </body>
</html>
