<?php

use App\Enums\BankTransactionType;
use App\Models\BankAccount;
use App\Models\BankTransaction;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Lazy, Title('Bank Dashboard')] class extends Component
{
    public function mount(): void
    {
        Gate::authorize('viewAny', BankAccount::class);
    }

    /**
     * @return array{try_balance:float, usd_balance:float, eur_balance:float, unreconciled:int, active_accounts:int}
     */
    #[Computed]
    public function kpiStats(): array
    {
        $accounts = BankAccount::query()
            ->withSum(['transactions as credits_sum' => fn ($q) => $q->where('transaction_type', 'credit')], 'amount')
            ->withSum(['transactions as debits_sum' => fn ($q) => $q->where('transaction_type', 'debit')], 'amount')
            ->get();

        $balance = fn (string $currency) => $accounts->where('currency_code', $currency)->sum(
            fn ($a) => (float) $a->opening_balance + (float) ($a->credits_sum ?? 0) - (float) ($a->debits_sum ?? 0)
        );

        return [
            'try_balance' => $balance('TRY'),
            'usd_balance' => $balance('USD'),
            'eur_balance' => $balance('EUR'),
            'unreconciled' => BankTransaction::query()->where('is_reconciled', false)->count(),
            'active_accounts' => $accounts->where('is_active', true)->count(),
        ];
    }

    /**
     * @return \Illuminate\Database\Eloquent\Collection<int, BankTransaction>
     */
    #[Computed]
    public function recentTransactions(): \Illuminate\Database\Eloquent\Collection
    {
        return BankTransaction::query()
            ->with('bankAccount:id,name,currency_code')
            ->orderByDesc('transaction_date')
            ->orderByDesc('id')
            ->limit(10)
            ->get();
    }

    /**
     * @return array{credits:float, debits:float, net:float, month:string}
     */
    #[Computed]
    public function monthlySummary(): array
    {
        $from = Carbon::now()->startOfMonth();
        $to = Carbon::now()->endOfMonth();

        $credits = (float) BankTransaction::query()
            ->where('transaction_type', BankTransactionType::Credit->value)
            ->whereBetween('transaction_date', [$from, $to])
            ->sum('amount');

        $debits = (float) BankTransaction::query()
            ->where('transaction_type', BankTransactionType::Debit->value)
            ->whereBetween('transaction_date', [$from, $to])
            ->sum('amount');

        return [
            'credits' => $credits,
            'debits' => $debits,
            'net' => $credits - $debits,
            'month' => $from->translatedFormat('F Y'),
        ];
    }

    /**
     * @return \Illuminate\Database\Eloquent\Collection<int, BankAccount>
     */
    #[Computed]
    public function accountBalances(): \Illuminate\Database\Eloquent\Collection
    {
        return BankAccount::query()
            ->where('is_active', true)
            ->withSum(['transactions as credits_sum' => fn ($q) => $q->where('transaction_type', 'credit')], 'amount')
            ->withSum(['transactions as debits_sum' => fn ($q) => $q->where('transaction_type', 'debit')], 'amount')
            ->orderBy('name')
            ->get();
    }
}; ?>

<div class="mx-auto flex w-full max-w-7xl flex-col gap-6 p-4 lg:p-8">

    <x-admin.page-header
        :heading="__('Bank Dashboard')"
        :description="__('Overview of bank accounts, balances and recent transactions.')"
    >
        <x-slot name="actions">
            <flux:button :href="route('admin.finance.bank-accounts.index')" variant="outline" icon="building-library" wire:navigate>
                {{ __('Bank Accounts') }}
            </flux:button>
            <flux:button :href="route('admin.finance.bank-transactions.index')" variant="outline" icon="arrows-right-left" wire:navigate>
                {{ __('Transactions') }}
            </flux:button>
        </x-slot>
    </x-admin.page-header>

    {{-- KPI Cards --}}
    <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
        <flux:card class="p-4">
            <flux:text class="text-sm text-zinc-500 dark:text-zinc-400">{{ __('TRY Balance') }}</flux:text>
            <flux:heading size="lg" class="{{ $this->kpiStats['try_balance'] >= 0 ? 'text-blue-600' : 'text-red-500' }}">
                ₺ {{ number_format($this->kpiStats['try_balance'], 2) }}
            </flux:heading>
        </flux:card>
        <flux:card class="p-4">
            <flux:text class="text-sm text-zinc-500 dark:text-zinc-400">{{ __('USD Balance') }}</flux:text>
            <flux:heading size="lg" class="{{ $this->kpiStats['usd_balance'] >= 0 ? 'text-green-600' : 'text-red-500' }}">
                $ {{ number_format($this->kpiStats['usd_balance'], 2) }}
            </flux:heading>
        </flux:card>
        <flux:card class="p-4">
            <flux:text class="text-sm text-zinc-500 dark:text-zinc-400">{{ __('Active accounts') }}</flux:text>
            <flux:heading size="lg">{{ $this->kpiStats['active_accounts'] }}</flux:heading>
        </flux:card>
        <flux:card class="p-4">
            <flux:text class="text-sm text-zinc-500 dark:text-zinc-400">{{ __('Unreconciled') }}</flux:text>
            <flux:heading size="lg" class="{{ $this->kpiStats['unreconciled'] > 0 ? 'text-amber-600' : 'text-zinc-500' }}">
                {{ $this->kpiStats['unreconciled'] }}
            </flux:heading>
        </flux:card>
    </div>

    <div class="grid gap-6 lg:grid-cols-2">

        {{-- Monthly Summary --}}
        <flux:card class="p-4">
            <flux:heading size="base" class="mb-4">
                {{ __('Monthly summary') }} — {{ $this->monthlySummary['month'] }}
            </flux:heading>
            <div class="space-y-3">
                <div class="flex items-center justify-between">
                    <div class="flex items-center gap-2">
                        <span class="h-3 w-3 rounded-full bg-green-500"></span>
                        <flux:text class="text-sm">{{ __('Credits (Incoming)') }}</flux:text>
                    </div>
                    <flux:text class="font-mono font-semibold text-green-600">
                        + {{ number_format($this->monthlySummary['credits'], 2) }}
                    </flux:text>
                </div>
                <div class="flex items-center justify-between">
                    <div class="flex items-center gap-2">
                        <span class="h-3 w-3 rounded-full bg-red-500"></span>
                        <flux:text class="text-sm">{{ __('Debits (Outgoing)') }}</flux:text>
                    </div>
                    <flux:text class="font-mono font-semibold text-red-500">
                        - {{ number_format($this->monthlySummary['debits'], 2) }}
                    </flux:text>
                </div>
                <div class="border-t border-zinc-200 pt-3 dark:border-zinc-700">
                    <div class="flex items-center justify-between">
                        <flux:text class="font-medium">{{ __('Net') }}</flux:text>
                        <flux:heading size="base" class="{{ $this->monthlySummary['net'] >= 0 ? 'text-blue-600' : 'text-red-500' }}">
                            {{ number_format($this->monthlySummary['net'], 2) }}
                        </flux:heading>
                    </div>
                </div>
            </div>
        </flux:card>

        {{-- Account Balances --}}
        <flux:card class="p-4">
            <flux:heading size="base" class="mb-4">{{ __('Account balances') }}</flux:heading>
            @forelse ($this->accountBalances as $account)
                @php
                    $balance = (float) $account->opening_balance
                        + (float) ($account->credits_sum ?? 0)
                        - (float) ($account->debits_sum ?? 0);
                @endphp
                <div class="flex items-center justify-between py-2 border-b border-zinc-100 last:border-0 dark:border-zinc-800">
                    <div>
                        <flux:text class="font-medium text-sm">{{ $account->name }}</flux:text>
                        <flux:text class="text-xs text-zinc-500">{{ $account->bank_name }}</flux:text>
                    </div>
                    <div class="text-end">
                        <span class="font-mono text-sm font-semibold {{ $balance >= 0 ? 'text-blue-600 dark:text-blue-400' : 'text-red-500' }}">
                            {{ number_format($balance, 2) }}
                        </span>
                        <flux:badge color="zinc" size="sm" class="ms-1">{{ $account->currency_code }}</flux:badge>
                    </div>
                </div>
            @empty
                <flux:text class="text-zinc-500">{{ __('No active accounts.') }}</flux:text>
            @endforelse
        </flux:card>

    </div>

    {{-- Recent Transactions --}}
    <flux:card class="p-4">
        <div class="mb-4 flex items-center justify-between">
            <flux:heading size="base">{{ __('Recent transactions') }}</flux:heading>
            <flux:button size="sm" variant="ghost" :href="route('admin.finance.bank-transactions.index')" wire:navigate>
                {{ __('View all') }}
            </flux:button>
        </div>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-zinc-200 text-sm dark:divide-zinc-700">
                <thead>
                    <tr class="text-start text-zinc-500 dark:text-zinc-400">
                        <th class="py-2 pe-3 font-medium">{{ __('Date') }}</th>
                        <th class="py-2 pe-3 font-medium">{{ __('Account') }}</th>
                        <th class="py-2 pe-3 font-medium">{{ __('Description') }}</th>
                        <th class="py-2 pe-3 font-medium">{{ __('Type') }}</th>
                        <th class="py-2 text-end font-medium">{{ __('Amount') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-zinc-100 dark:divide-zinc-800">
                    @forelse ($this->recentTransactions as $tx)
                        <tr>
                            <td class="py-2 pe-3 text-zinc-500">{{ $tx->transaction_date->format('d.m.Y') }}</td>
                            <td class="py-2 pe-3 font-medium">{{ $tx->bankAccount?->name ?? '—' }}</td>
                            <td class="py-2 pe-3 text-zinc-600 dark:text-zinc-400 max-w-[200px] truncate">
                                {{ $tx->description ?: ($tx->reference_no ?: '—') }}
                            </td>
                            <td class="py-2 pe-3">
                                <flux:badge
                                    color="{{ $tx->transaction_type === App\Enums\BankTransactionType::Credit ? 'green' : 'red' }}"
                                    size="sm"
                                >
                                    {{ $tx->transaction_type->label() }}
                                </flux:badge>
                            </td>
                            <td class="py-2 text-end font-mono font-semibold {{ $tx->transaction_type === App\Enums\BankTransactionType::Credit ? 'text-green-600' : 'text-red-500' }}">
                                {{ $tx->transaction_type === App\Enums\BankTransactionType::Credit ? '+' : '-' }}{{ number_format((float) $tx->amount, 2) }}
                                <span class="text-xs font-normal text-zinc-500">{{ $tx->currency_code }}</span>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="py-8 text-center text-zinc-500">{{ __('No transactions yet.') }}</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </flux:card>

</div>
