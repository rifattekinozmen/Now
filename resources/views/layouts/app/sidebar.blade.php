<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        @include('partials.head')
    </head>
    <body class="min-h-screen antialiased">
        <div class="flex min-h-screen w-full bg-zinc-100 dark:bg-zinc-950">

        <div class="sticky top-0 h-dvh shrink-0 z-20">
        @persist('app-sidebar')
        <flux:sidebar :collapsible="true" class="border-r border-zinc-200 bg-white dark:border-zinc-800 dark:bg-zinc-900 w-64 shrink-0" style="display:flex !important; flex-direction:column !important; height:100dvh !important; overflow:hidden !important; padding:0 !important; gap:0 !important;">

            {{-- Logo + Collapse --}}
            <flux:sidebar.header class="flex h-14 shrink-0 items-center justify-between border-b border-zinc-100 px-4 dark:border-zinc-800">
                <x-app-logo :sidebar="true" href="{{ route('dashboard') }}" wire:navigate />
                <flux:sidebar.collapse class="in-data-flux-sidebar-on-desktop:not-in-data-flux-sidebar-collapsed-desktop:-mr-2 text-zinc-400 hover:text-zinc-600 dark:text-zinc-500 dark:hover:text-zinc-300" />
            </flux:sidebar.header>

            {{-- Scrollable nav --}}
            <div
                x-data="{
                    ops: localStorage.getItem('sb-ops') !== '0',
                    fin: localStorage.getItem('sb-fin') !== '0',
                    hr:  localStorage.getItem('sb-hr')  !== '0',
                    toggle(k) { this[k] = !this[k]; localStorage.setItem('sb-' + k, this[k] ? '1' : '0'); }
                }"
                style="overflow-y: auto; min-height: 0; flex: 1 1 0%;"
                class="px-2 py-3"
            >

                <flux:sidebar.item
                    icon="home"
                    :href="route('dashboard')"
                    wire:navigate
                    wire:current.exact="bg-zinc-100 font-semibold text-zinc-900 dark:bg-zinc-800 dark:text-zinc-100"
                >
                    {{ __('Dashboard') }}
                </flux:sidebar.item>

                <flux:sidebar.item
                    icon="bell"
                    :href="route('admin.notifications.index')"
                    wire:navigate
                    wire:current.exact="bg-zinc-100 font-semibold text-zinc-900 dark:bg-zinc-800 dark:text-zinc-100"
                >
                    {{ __('Notifications') }}
                </flux:sidebar.item>

                @cache('sidebar-menu-v4-' . auth()->id(), 3600)
                @canany([\App\Authorization\LogisticsPermission::ADMIN, \App\Authorization\LogisticsPermission::VIEW])

                {{-- ═══════════════════════════════════════ --}}
                {{-- OPERATIONS --}}
                {{-- ═══════════════════════════════════════ --}}

                {{-- Expanded: toggle header --}}
                <div class="mt-4 in-data-flux-sidebar-collapsed-desktop:hidden">
                    <button @click="toggle('ops')" class="flex w-full items-center justify-between rounded-md px-2 py-1.5 hover:bg-zinc-100 dark:hover:bg-zinc-800">
                        <span class="text-xs font-bold uppercase tracking-wider text-zinc-500 dark:text-zinc-400">{{ __('Operations') }}</span>
                        <svg :class="ops ? 'rotate-0' : '-rotate-90'" class="size-4 shrink-0 text-zinc-500 transition-transform duration-200 dark:text-zinc-400" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="m19 9-7 7-7-7" /></svg>
                    </button>
                </div>

                {{-- Expanded: items --}}
                <div x-show="ops" class="in-data-flux-sidebar-collapsed-desktop:hidden">
                    <flux:sidebar.item icon="users" :href="route('admin.customers.index')" wire:navigate wire:current="bg-zinc-100 font-semibold text-zinc-900 dark:bg-zinc-800 dark:text-zinc-100">{{ __('Customers') }}</flux:sidebar.item>
                    <flux:sidebar.item icon="clipboard-document-list" :href="route('admin.orders.index')" wire:navigate wire:current="bg-zinc-100 font-semibold text-zinc-900 dark:bg-zinc-800 dark:text-zinc-100">{{ __('Orders') }}</flux:sidebar.item>
                    <flux:sidebar.item icon="cube" :href="route('admin.shipments.index')" wire:navigate wire:current="bg-zinc-100 font-semibold text-zinc-900 dark:bg-zinc-800 dark:text-zinc-100">{{ __('Shipments') }}</flux:sidebar.item>
                    <flux:sidebar.item icon="hashtag" :href="route('admin.delivery-numbers.index')" wire:navigate wire:current="bg-zinc-100 font-semibold text-zinc-900 dark:bg-zinc-800 dark:text-zinc-100">{{ __('PIN Pool') }}</flux:sidebar.item>
                    <flux:sidebar.item icon="truck" :href="route('admin.vehicles.index')" wire:navigate wire:current="bg-zinc-100 font-semibold text-zinc-900 dark:bg-zinc-800 dark:text-zinc-100">{{ __('Vehicles') }}</flux:sidebar.item>
                    <flux:sidebar.item icon="bolt" :href="route('admin.fuel-intakes.index')" wire:navigate wire:current="bg-zinc-100 font-semibold text-zinc-900 dark:bg-zinc-800 dark:text-zinc-100">{{ __('Fuel Intakes') }}</flux:sidebar.item>
                    <flux:sidebar.item icon="currency-dollar" :href="route('admin.fuel-prices.index')" wire:navigate wire:current="bg-zinc-100 font-semibold text-zinc-900 dark:bg-zinc-800 dark:text-zinc-100">{{ __('Fuel Prices') }}</flux:sidebar.item>
                    <flux:sidebar.item icon="archive-box" :href="route('admin.warehouse.index')" wire:navigate wire:current="bg-zinc-100 font-semibold text-zinc-900 dark:bg-zinc-800 dark:text-zinc-100">{{ __('Warehouse') }}</flux:sidebar.item>
                    <flux:sidebar.item icon="wrench-screwdriver" :href="route('admin.maintenance.index')" wire:navigate wire:current="bg-zinc-100 font-semibold text-zinc-900 dark:bg-zinc-800 dark:text-zinc-100">{{ __('Maintenance') }}</flux:sidebar.item>
                    <flux:sidebar.item icon="chart-bar-square" :href="route('admin.analytics.fleet')" wire:navigate wire:current="bg-zinc-100 font-semibold text-zinc-900 dark:bg-zinc-800 dark:text-zinc-100">{{ __('Fleet Analytics') }}</flux:sidebar.item>
                    <flux:sidebar.item icon="chart-bar" :href="route('admin.analytics.operations')" wire:navigate wire:current="bg-zinc-100 font-semibold text-zinc-900 dark:bg-zinc-800 dark:text-zinc-100">{{ __('Ops Analytics') }}</flux:sidebar.item>
                    <flux:sidebar.item icon="document-text" :href="route('admin.pricing-conditions.index')" wire:navigate wire:current="bg-zinc-100 font-semibold text-zinc-900 dark:bg-zinc-800 dark:text-zinc-100">{{ __('Pricing') }}</flux:sidebar.item>
                    <flux:sidebar.item icon="receipt-percent" :href="route('admin.trip-expenses.index')" wire:navigate wire:current="bg-zinc-100 font-semibold text-zinc-900 dark:bg-zinc-800 dark:text-zinc-100">{{ __('Trip Expenses') }}</flux:sidebar.item>
                </div>

                {{-- Collapsed: Operations fly-out dropdown --}}
                <div class="hidden in-data-flux-sidebar-collapsed-desktop:flex justify-center mt-3">
                    <flux:dropdown position="right" align="start">
                        <flux:button icon="squares-2x2" variant="ghost" size="sm" square />
                        <flux:menu>
                            <flux:menu.heading>{{ __('Operations') }}</flux:menu.heading>
                            <flux:menu.item :href="route('admin.customers.index')" icon="users" wire:navigate>{{ __('Customers') }}</flux:menu.item>
                            <flux:menu.item :href="route('admin.orders.index')" icon="clipboard-document-list" wire:navigate>{{ __('Orders') }}</flux:menu.item>
                            <flux:menu.item :href="route('admin.shipments.index')" icon="cube" wire:navigate>{{ __('Shipments') }}</flux:menu.item>
                            <flux:menu.item :href="route('admin.delivery-numbers.index')" icon="hashtag" wire:navigate>{{ __('PIN Pool') }}</flux:menu.item>
                            <flux:menu.item :href="route('admin.vehicles.index')" icon="truck" wire:navigate>{{ __('Vehicles') }}</flux:menu.item>
                            <flux:menu.item :href="route('admin.fuel-intakes.index')" icon="bolt" wire:navigate>{{ __('Fuel Intakes') }}</flux:menu.item>
                            <flux:menu.item :href="route('admin.fuel-prices.index')" icon="currency-dollar" wire:navigate>{{ __('Fuel Prices') }}</flux:menu.item>
                            <flux:menu.item :href="route('admin.warehouse.index')" icon="archive-box" wire:navigate>{{ __('Warehouse') }}</flux:menu.item>
                            <flux:menu.item :href="route('admin.maintenance.index')" icon="wrench-screwdriver" wire:navigate>{{ __('Maintenance') }}</flux:menu.item>
                            <flux:menu.item :href="route('admin.analytics.fleet')" icon="chart-bar-square" wire:navigate>{{ __('Fleet Analytics') }}</flux:menu.item>
                            <flux:menu.item :href="route('admin.analytics.operations')" icon="chart-bar" wire:navigate>{{ __('Ops Analytics') }}</flux:menu.item>
                            <flux:menu.item :href="route('admin.pricing-conditions.index')" icon="document-text" wire:navigate>{{ __('Pricing') }}</flux:menu.item>
                            <flux:menu.item :href="route('admin.trip-expenses.index')" icon="receipt-percent" wire:navigate>{{ __('Trip Expenses') }}</flux:menu.item>
                        </flux:menu>
                    </flux:dropdown>
                </div>

                {{-- ═══════════════════════════════════════ --}}
                {{-- FINANCE --}}
                {{-- ═══════════════════════════════════════ --}}

                {{-- Expanded: toggle header --}}
                <div class="mt-4 in-data-flux-sidebar-collapsed-desktop:hidden">
                    <button @click="toggle('fin')" class="flex w-full items-center justify-between rounded-md px-2 py-1.5 hover:bg-zinc-100 dark:hover:bg-zinc-800">
                        <span class="text-xs font-bold uppercase tracking-wider text-zinc-500 dark:text-zinc-400">{{ __('Finance') }}</span>
                        <svg :class="fin ? 'rotate-0' : '-rotate-90'" class="size-4 shrink-0 text-zinc-500 transition-transform duration-200 dark:text-zinc-400" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="m19 9-7 7-7-7" /></svg>
                    </button>
                </div>

                {{-- Expanded: items --}}
                <div x-show="fin" class="in-data-flux-sidebar-collapsed-desktop:hidden">
                    <flux:sidebar.item icon="calculator" :href="route('admin.finance.index')" wire:navigate wire:current.exact="bg-zinc-100 font-semibold text-zinc-900 dark:bg-zinc-800 dark:text-zinc-100">{{ __('Finance Summary') }}</flux:sidebar.item>
                    <flux:sidebar.item icon="chart-bar" :href="route('admin.finance.reports')" wire:navigate wire:current.exact="bg-zinc-100 font-semibold text-zinc-900 dark:bg-zinc-800 dark:text-zinc-100">{{ __('Finance Reports') }}</flux:sidebar.item>
                    <flux:sidebar.item icon="calendar-days" :href="route('admin.finance.payment-due-calendar')" wire:navigate wire:current.exact="bg-zinc-100 font-semibold text-zinc-900 dark:bg-zinc-800 dark:text-zinc-100">{{ __('Payment Calendar') }}</flux:sidebar.item>
                    <flux:sidebar.item icon="document-text" :href="route('admin.finance.bank-statement-csv')" wire:navigate wire:current.exact="bg-zinc-100 font-semibold text-zinc-900 dark:bg-zinc-800 dark:text-zinc-100">{{ __('Bank Import') }}</flux:sidebar.item>
                    <flux:sidebar.item icon="rectangle-stack" :href="route('admin.finance.chart-accounts.index')" wire:navigate wire:current.exact="bg-zinc-100 font-semibold text-zinc-900 dark:bg-zinc-800 dark:text-zinc-100">{{ __('Chart of Accounts') }}</flux:sidebar.item>
                    <flux:sidebar.item icon="document-duplicate" :href="route('admin.finance.journal-entries.index')" wire:navigate wire:current.exact="bg-zinc-100 font-semibold text-zinc-900 dark:bg-zinc-800 dark:text-zinc-100">{{ __('Journal Entries') }}</flux:sidebar.item>
                    <flux:sidebar.item icon="table-cells" :href="route('admin.finance.trial-balance')" wire:navigate wire:current.exact="bg-zinc-100 font-semibold text-zinc-900 dark:bg-zinc-800 dark:text-zinc-100">{{ __('Trial Balance') }}</flux:sidebar.item>
                    <flux:sidebar.item icon="scale" :href="route('admin.finance.balance-sheet')" wire:navigate wire:current.exact="bg-zinc-100 font-semibold text-zinc-900 dark:bg-zinc-800 dark:text-zinc-100">{{ __('Balance Sheet') }}</flux:sidebar.item>
                    <flux:sidebar.item icon="calendar" :href="route('admin.finance.fiscal-opening-balances.index')" wire:navigate wire:current.exact="bg-zinc-100 font-semibold text-zinc-900 dark:bg-zinc-800 dark:text-zinc-100">{{ __('Opening Balances') }}</flux:sidebar.item>
                    <flux:sidebar.item icon="banknotes" :href="route('admin.finance.cash-registers.index')" wire:navigate wire:current.exact="bg-zinc-100 font-semibold text-zinc-900 dark:bg-zinc-800 dark:text-zinc-100">{{ __('Cash Registers') }}</flux:sidebar.item>
                    <flux:sidebar.item icon="document-check" :href="route('admin.finance.vouchers.index')" wire:navigate wire:current.exact="bg-zinc-100 font-semibold text-zinc-900 dark:bg-zinc-800 dark:text-zinc-100">{{ __('Vouchers') }}</flux:sidebar.item>
                    <flux:sidebar.item icon="credit-card" :href="route('admin.finance.current-accounts.index')" wire:navigate wire:current.exact="bg-zinc-100 font-semibold text-zinc-900 dark:bg-zinc-800 dark:text-zinc-100">{{ __('Current Accounts') }}</flux:sidebar.item>
                </div>

                {{-- Collapsed: Finance fly-out dropdown --}}
                <div class="hidden in-data-flux-sidebar-collapsed-desktop:flex justify-center mt-2">
                    <flux:dropdown position="right" align="start">
                        <flux:button icon="calculator" variant="ghost" size="sm" square />
                        <flux:menu>
                            <flux:menu.heading>{{ __('Finance') }}</flux:menu.heading>
                            <flux:menu.item :href="route('admin.finance.index')" icon="calculator" wire:navigate>{{ __('Finance Summary') }}</flux:menu.item>
                            <flux:menu.item :href="route('admin.finance.reports')" icon="chart-bar" wire:navigate>{{ __('Finance Reports') }}</flux:menu.item>
                            <flux:menu.item :href="route('admin.finance.payment-due-calendar')" icon="calendar-days" wire:navigate>{{ __('Payment Calendar') }}</flux:menu.item>
                            <flux:menu.item :href="route('admin.finance.bank-statement-csv')" icon="document-text" wire:navigate>{{ __('Bank Import') }}</flux:menu.item>
                            <flux:menu.item :href="route('admin.finance.chart-accounts.index')" icon="rectangle-stack" wire:navigate>{{ __('Chart of Accounts') }}</flux:menu.item>
                            <flux:menu.item :href="route('admin.finance.journal-entries.index')" icon="document-duplicate" wire:navigate>{{ __('Journal Entries') }}</flux:menu.item>
                            <flux:menu.item :href="route('admin.finance.trial-balance')" icon="table-cells" wire:navigate>{{ __('Trial Balance') }}</flux:menu.item>
                            <flux:menu.item :href="route('admin.finance.balance-sheet')" icon="scale" wire:navigate>{{ __('Balance Sheet') }}</flux:menu.item>
                            <flux:menu.item :href="route('admin.finance.fiscal-opening-balances.index')" icon="calendar" wire:navigate>{{ __('Opening Balances') }}</flux:menu.item>
                            <flux:menu.item :href="route('admin.finance.cash-registers.index')" icon="banknotes" wire:navigate>{{ __('Cash Registers') }}</flux:menu.item>
                            <flux:menu.item :href="route('admin.finance.vouchers.index')" icon="document-check" wire:navigate>{{ __('Vouchers') }}</flux:menu.item>
                            <flux:menu.item :href="route('admin.finance.current-accounts.index')" icon="credit-card" wire:navigate>{{ __('Current Accounts') }}</flux:menu.item>
                        </flux:menu>
                    </flux:dropdown>
                </div>

                {{-- ═══════════════════════════════════════ --}}
                {{-- HR --}}
                {{-- ═══════════════════════════════════════ --}}

                {{-- Expanded: toggle header --}}
                <div class="mt-4 in-data-flux-sidebar-collapsed-desktop:hidden">
                    <button @click="toggle('hr')" class="flex w-full items-center justify-between rounded-md px-2 py-1.5 hover:bg-zinc-100 dark:hover:bg-zinc-800">
                        <span class="text-xs font-bold uppercase tracking-wider text-zinc-500 dark:text-zinc-400">{{ __('HR') }}</span>
                        <svg :class="hr ? 'rotate-0' : '-rotate-90'" class="size-4 shrink-0 text-zinc-500 transition-transform duration-200 dark:text-zinc-400" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="m19 9-7 7-7-7" /></svg>
                    </button>
                </div>

                {{-- Expanded: items --}}
                <div x-show="hr" class="in-data-flux-sidebar-collapsed-desktop:hidden">
                    <flux:sidebar.item icon="user-group" :href="route('admin.employees.index')" wire:navigate wire:current="bg-zinc-100 font-semibold text-zinc-900 dark:bg-zinc-800 dark:text-zinc-100">{{ __('Employees') }}</flux:sidebar.item>
                    <flux:sidebar.item icon="calendar-days" :href="route('admin.hr.leaves.index')" wire:navigate wire:current.exact="bg-zinc-100 font-semibold text-zinc-900 dark:bg-zinc-800 dark:text-zinc-100">{{ __('Leave Requests') }}</flux:sidebar.item>
                    <flux:sidebar.item icon="banknotes" :href="route('admin.hr.advances.index')" wire:navigate wire:current.exact="bg-zinc-100 font-semibold text-zinc-900 dark:bg-zinc-800 dark:text-zinc-100">{{ __('Advances') }}</flux:sidebar.item>
                    <flux:sidebar.item icon="document-text" :href="route('admin.hr.payroll.index')" wire:navigate wire:current.exact="bg-zinc-100 font-semibold text-zinc-900 dark:bg-zinc-800 dark:text-zinc-100">{{ __('Payroll') }}</flux:sidebar.item>
                    <flux:sidebar.item icon="calendar-days" :href="route('admin.hr.attendance.index')" wire:navigate wire:current.exact="bg-zinc-100 font-semibold text-zinc-900 dark:bg-zinc-800 dark:text-zinc-100">{{ __('Attendance') }}</flux:sidebar.item>
                </div>

                {{-- Collapsed: HR fly-out dropdown --}}
                <div class="hidden in-data-flux-sidebar-collapsed-desktop:flex justify-center mt-2">
                    <flux:dropdown position="right" align="start">
                        <flux:button icon="user-group" variant="ghost" size="sm" square />
                        <flux:menu>
                            <flux:menu.heading>{{ __('HR') }}</flux:menu.heading>
                            <flux:menu.item :href="route('admin.employees.index')" icon="user-group" wire:navigate>{{ __('Employees') }}</flux:menu.item>
                            <flux:menu.item :href="route('admin.hr.leaves.index')" icon="calendar-days" wire:navigate>{{ __('Leave Requests') }}</flux:menu.item>
                            <flux:menu.item :href="route('admin.hr.advances.index')" icon="banknotes" wire:navigate>{{ __('Advances') }}</flux:menu.item>
                            <flux:menu.item :href="route('admin.hr.payroll.index')" icon="document-text" wire:navigate>{{ __('Payroll') }}</flux:menu.item>
                            <flux:menu.item :href="route('admin.hr.attendance.index')" icon="calendar-days" wire:navigate>{{ __('Attendance') }}</flux:menu.item>
                        </flux:menu>
                    </flux:dropdown>
                </div>

                @endcanany
                @endcache

            </div>

            {{-- User profile: fixed at bottom --}}
            <div class="hidden shrink-0 border-t border-zinc-100 p-2 dark:border-zinc-800 lg:block">
                <x-desktop-user-menu :name="auth()->user()->name" />
            </div>

        </flux:sidebar>
        @endpersist
        </div>

        <div class="flex min-w-0 flex-1 flex-col">

            {{-- Desktop top bar --}}
            <div class="sticky top-0 z-10 hidden h-14 shrink-0 items-center border-b border-zinc-200 bg-white dark:border-zinc-800 dark:bg-zinc-900 lg:flex">
                <livewire:global-search />
                @auth
                    @canany([\App\Authorization\LogisticsPermission::ADMIN, \App\Authorization\LogisticsPermission::VIEW])
                        <div class="shrink-0 px-2">
                            <livewire:notification-bell wire:poll.5m />
                        </div>
                    @endcanany
                @endauth
            </div>

            {{-- Mobile header --}}
            <flux:header class="lg:hidden">
                <flux:sidebar.toggle class="lg:hidden" icon="bars-2" inset="left" />

                <flux:spacer />

                @auth
                    @canany([\App\Authorization\LogisticsPermission::ADMIN, \App\Authorization\LogisticsPermission::VIEW])
                        <livewire:notification-bell wire:poll.5m />
                    @endcanany
                @endauth

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

        {{-- Sidebar collapse state persistence via localStorage --}}
        <script>
            (function () {
                var STORAGE_KEY = 'sidebar-desktop-collapsed';

                function initSidebarMemory() {
                    var sidebar = document.querySelector('[data-flux-sidebar]');
                    if (!sidebar) return;

                    if (localStorage.getItem(STORAGE_KEY) === '1') {
                        var btn = document.querySelector('[data-flux-sidebar-collapse]');
                        if (btn && !sidebar.hasAttribute('data-flux-sidebar-collapsed-desktop')) {
                            btn.click();
                        }
                    }

                    var observer = new MutationObserver(function () {
                        var collapsed = sidebar.hasAttribute('data-flux-sidebar-collapsed-desktop');
                        localStorage.setItem(STORAGE_KEY, collapsed ? '1' : '0');
                    });
                    observer.observe(sidebar, {
                        attributes: true,
                        attributeFilter: ['data-flux-sidebar-collapsed-desktop'],
                    });
                }

                if (document.readyState === 'loading') {
                    document.addEventListener('DOMContentLoaded', initSidebarMemory);
                } else {
                    initSidebarMemory();
                }
            })();
        </script>
        </div>
    </body>
</html>
