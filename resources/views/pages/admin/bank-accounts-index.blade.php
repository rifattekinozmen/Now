<?php

use App\Models\BankAccount;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

new #[Lazy, Title('Bank accounts')] class extends Component
{
    use WithPagination;

    public ?int $editingId = null;

    // Form
    public string $name           = '';
    public string $bankName       = '';
    public string $accountNumber  = '';
    public string $iban           = '';
    public string $currencyCode   = 'TRY';
    public string $openingBalance = '0';
    public string $openedAt       = '';
    public bool   $isActive       = true;
    public string $notes          = '';

    // Filters
    public string $filterSearch   = '';
    public string $filterCurrency = '';
    public string $filterStatus   = '';

    public ?int $confirmingDeleteId = null;

    public function mount(): void
    {
        Gate::authorize('viewAny', BankAccount::class);
    }

    public function updatedFilterSearch(): void { $this->resetPage(); }
    public function updatedFilterCurrency(): void { $this->resetPage(); }
    public function updatedFilterStatus(): void { $this->resetPage(); }

    /**
     * @return array{total:int, active:int, inactive:int}
     */
    #[Computed]
    public function kpiStats(): array
    {
        return [
            'total'    => BankAccount::query()->count(),
            'active'   => BankAccount::query()->where('is_active', true)->count(),
            'inactive' => BankAccount::query()->where('is_active', false)->count(),
        ];
    }

    private function accountQuery(): Builder
    {
        $q = BankAccount::query();

        if ($this->filterSearch !== '') {
            $term = '%'.addcslashes($this->filterSearch, '%_\\').'%';
            $q->where(function (Builder $inner) use ($term): void {
                $inner->where('name', 'like', $term)
                    ->orWhere('bank_name', 'like', $term)
                    ->orWhere('iban', 'like', $term)
                    ->orWhere('account_number', 'like', $term);
            });
        }

        if ($this->filterCurrency !== '') {
            $q->where('currency_code', $this->filterCurrency);
        }

        if ($this->filterStatus === 'active') {
            $q->where('is_active', true);
        } elseif ($this->filterStatus === 'inactive') {
            $q->where('is_active', false);
        }

        return $q->orderBy('name');
    }

    #[Computed]
    public function paginatedAccounts(): LengthAwarePaginator
    {
        return $this->accountQuery()->paginate(20);
    }

    public function startCreate(): void
    {
        Gate::authorize('create', BankAccount::class);
        $this->resetForm();
        $this->editingId = 0;
    }

    public function startEdit(int $id): void
    {
        $account = BankAccount::query()->findOrFail($id);
        Gate::authorize('update', $account);

        $this->editingId      = $id;
        $this->name           = $account->name;
        $this->bankName       = $account->bank_name;
        $this->accountNumber  = $account->account_number ?? '';
        $this->iban           = $account->iban ?? '';
        $this->currencyCode   = $account->currency_code;
        $this->openingBalance = (string) $account->opening_balance;
        $this->openedAt       = $account->opened_at?->format('Y-m-d') ?? '';
        $this->isActive       = $account->is_active;
        $this->notes          = $account->notes ?? '';
    }

    public function cancelForm(): void
    {
        $this->editingId = null;
        $this->resetForm();
    }

    public function save(): void
    {
        $validated = $this->validate([
            'name'           => ['required', 'string', 'max:255'],
            'bankName'       => ['required', 'string', 'max:255'],
            'accountNumber'  => ['nullable', 'string', 'max:64'],
            'iban'           => ['nullable', 'string', 'max:34'],
            'currencyCode'   => ['required', 'string', 'size:3'],
            'openingBalance' => ['required', 'numeric', 'min:0'],
            'openedAt'       => ['nullable', 'date'],
            'isActive'       => ['boolean'],
            'notes'          => ['nullable', 'string', 'max:1000'],
        ]);

        $data = [
            'name'            => $validated['name'],
            'bank_name'       => $validated['bankName'],
            'account_number'  => $validated['accountNumber'] ?: null,
            'iban'            => $validated['iban'] ?: null,
            'currency_code'   => $validated['currencyCode'],
            'opening_balance' => $validated['openingBalance'],
            'opened_at'       => $validated['openedAt'] ?: null,
            'is_active'       => $validated['isActive'],
            'notes'           => $validated['notes'] ?: null,
        ];

        if ($this->editingId && $this->editingId > 0) {
            $account = BankAccount::query()->findOrFail($this->editingId);
            Gate::authorize('update', $account);
            $account->update($data);
        } else {
            Gate::authorize('create', BankAccount::class);
            BankAccount::query()->create($data);
        }

        $this->editingId = null;
        $this->resetForm();
        $this->resetPage();
    }

    public function confirmDelete(int $id): void
    {
        $this->confirmingDeleteId = $id;
        $this->modal('confirm-delete')->show();
    }

    public function executeDelete(): void
    {
        if ($this->confirmingDeleteId) {
            $account = BankAccount::query()->findOrFail($this->confirmingDeleteId);
            Gate::authorize('delete', $account);
            $account->delete();
            $this->confirmingDeleteId = null;
            $this->modal('confirm-delete')->close();
            $this->resetPage();
        }
    }

    private function resetForm(): void
    {
        $this->name           = '';
        $this->bankName       = '';
        $this->accountNumber  = '';
        $this->iban           = '';
        $this->currencyCode   = 'TRY';
        $this->openingBalance = '0';
        $this->openedAt       = '';
        $this->isActive       = true;
        $this->notes          = '';
    }
}; ?>

<div class="mx-auto flex w-full max-w-7xl flex-col gap-6 p-4 lg:p-8">

    <x-admin.page-header
        :heading="__('Bank accounts')"
        :description="__('Manage your bank accounts and balances.')"
    >
        <x-slot name="actions">
            @can('create', \App\Models\BankAccount::class)
                <flux:button type="button" variant="primary" wire:click="startCreate" icon="plus">
                    {{ __('New account') }}
                </flux:button>
            @endcan
            <flux:button :href="route('admin.finance.index')" variant="outline" wire:navigate>
                {{ __('Back to finance') }}
            </flux:button>
        </x-slot>
    </x-admin.page-header>

    {{-- KPI --}}
    <div class="grid gap-3 sm:grid-cols-3">
        <flux:card class="p-4">
            <flux:text class="text-sm text-zinc-500 dark:text-zinc-400">{{ __('Total accounts') }}</flux:text>
            <flux:heading size="lg">{{ $this->kpiStats['total'] }}</flux:heading>
        </flux:card>
        <flux:card class="p-4">
            <flux:text class="text-sm text-zinc-500 dark:text-zinc-400">{{ __('Active') }}</flux:text>
            <flux:heading size="lg" class="text-green-600">{{ $this->kpiStats['active'] }}</flux:heading>
        </flux:card>
        <flux:card class="p-4">
            <flux:text class="text-sm text-zinc-500 dark:text-zinc-400">{{ __('Inactive') }}</flux:text>
            <flux:heading size="lg" class="text-zinc-400">{{ $this->kpiStats['inactive'] }}</flux:heading>
        </flux:card>
    </div>

    {{-- Create / Edit Form --}}
    @if ($editingId !== null)
        <flux:card class="p-4">
            <flux:heading size="lg" class="mb-4">
                {{ $editingId > 0 ? __('Edit bank account') : __('New bank account') }}
            </flux:heading>
            <form wire:submit="save" class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                <flux:input wire:model="name" :label="__('Account name')" required />
                <flux:input wire:model="bankName" :label="__('Bank name')" required />
                <flux:select wire:model="currencyCode" :label="__('Currency')">
                    <option value="TRY">TRY — Türk Lirası</option>
                    <option value="USD">USD — US Dollar</option>
                    <option value="EUR">EUR — Euro</option>
                    <option value="GBP">GBP — British Pound</option>
                </flux:select>
                <flux:input wire:model="accountNumber" :label="__('Account number')" />
                <flux:input wire:model="iban" :label="__('IBAN')" />
                <flux:input wire:model="openingBalance" type="number" step="0.01" min="0" :label="__('Opening balance')" />
                <flux:input wire:model="openedAt" type="date" :label="__('Opened at')" />
                <div class="flex items-center gap-2 pt-6">
                    <flux:checkbox wire:model="isActive" id="isActive" />
                    <label for="isActive" class="text-sm text-zinc-700 dark:text-zinc-300">{{ __('Active') }}</label>
                </div>
                <flux:textarea wire:model="notes" :label="__('Notes')" rows="2" class="sm:col-span-2 lg:col-span-3" />
                <div class="flex flex-wrap gap-2 sm:col-span-2 lg:col-span-3">
                    <flux:button type="submit" variant="primary">{{ __('Save') }}</flux:button>
                    <flux:button type="button" variant="ghost" wire:click="cancelForm">{{ __('Cancel') }}</flux:button>
                </div>
            </form>
        </flux:card>
    @endif

    {{-- Filters --}}
    <div class="flex flex-wrap items-end gap-3">
        <flux:input wire:model.live="filterSearch" :label="__('Search')" :placeholder="__('Name, bank, IBAN…')" class="max-w-[240px]" />
        <flux:select wire:model.live="filterCurrency" :label="__('Currency')" class="max-w-[160px]">
            <option value="">{{ __('All currencies') }}</option>
            <option value="TRY">TRY</option>
            <option value="USD">USD</option>
            <option value="EUR">EUR</option>
            <option value="GBP">GBP</option>
        </flux:select>
        <flux:select wire:model.live="filterStatus" :label="__('Status')" class="max-w-[160px]">
            <option value="">{{ __('All') }}</option>
            <option value="active">{{ __('Active') }}</option>
            <option value="inactive">{{ __('Inactive') }}</option>
        </flux:select>
        @if ($filterSearch !== '' || $filterCurrency !== '' || $filterStatus !== '')
            <div class="flex items-end">
                <flux:button variant="ghost" size="sm"
                    wire:click="$set('filterSearch', ''); $set('filterCurrency', ''); $set('filterStatus', '')">
                    {{ __('Clear') }}
                </flux:button>
            </div>
        @endif
    </div>

    {{-- Table --}}
    <flux:card class="p-4">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-zinc-200 text-sm dark:divide-zinc-700">
                <thead>
                    <tr class="text-start text-zinc-500 dark:text-zinc-400">
                        <th class="py-2 pe-3 font-medium">{{ __('Account name') }}</th>
                        <th class="py-2 pe-3 font-medium">{{ __('Bank') }}</th>
                        <th class="py-2 pe-3 font-medium">{{ __('IBAN / Account No') }}</th>
                        <th class="py-2 pe-3 text-end font-medium">{{ __('Opening balance') }}</th>
                        <th class="py-2 pe-3 font-medium">{{ __('Currency') }}</th>
                        <th class="py-2 pe-3 font-medium">{{ __('Status') }}</th>
                        <th class="py-2 text-end font-medium">{{ __('Actions') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-zinc-100 dark:divide-zinc-800">
                    @forelse ($this->paginatedAccounts as $account)
                        <tr class="{{ $account->is_active ? '' : 'opacity-60' }}">
                            <td class="py-2 pe-3 font-medium">{{ $account->name }}</td>
                            <td class="py-2 pe-3 text-zinc-600 dark:text-zinc-400">{{ $account->bank_name }}</td>
                            <td class="py-2 pe-3 font-mono text-xs text-zinc-500">
                                {{ $account->iban ?? $account->account_number ?? '—' }}
                            </td>
                            <td class="py-2 pe-3 text-end font-mono font-semibold">
                                {{ number_format((float) $account->opening_balance, 2) }}
                            </td>
                            <td class="py-2 pe-3">
                                <flux:badge color="zinc" size="sm">{{ $account->currency_code }}</flux:badge>
                            </td>
                            <td class="py-2 pe-3">
                                @if ($account->is_active)
                                    <flux:badge color="green" size="sm">{{ __('Active') }}</flux:badge>
                                @else
                                    <flux:badge color="zinc" size="sm">{{ __('Inactive') }}</flux:badge>
                                @endif
                            </td>
                            <td class="py-2 text-end">
                                @can('update', $account)
                                    <div class="flex justify-end gap-1">
                                        <flux:button size="sm" variant="ghost" wire:click="startEdit({{ $account->id }})">
                                            {{ __('Edit') }}
                                        </flux:button>
                                        <flux:button size="sm" variant="danger" wire:click="confirmDelete({{ $account->id }})">
                                            {{ __('Delete') }}
                                        </flux:button>
                                    </div>
                                @endcan
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="py-8 text-center text-zinc-500">
                                {{ __('No bank accounts found.') }}
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="mt-4">{{ $this->paginatedAccounts->links() }}</div>
    </flux:card>

    <flux:modal name="confirm-delete" class="min-w-[22rem]">
        <div class="space-y-6">
            <div>
                <flux:heading size="lg">{{ __('Delete bank account?') }}</flux:heading>
                <flux:text class="mt-2">{{ __('This action cannot be undone.') }}</flux:text>
            </div>
            <div class="flex justify-end gap-2">
                <flux:modal.close>
                    <flux:button variant="ghost">{{ __('Cancel') }}</flux:button>
                </flux:modal.close>
                <flux:button variant="danger" wire:click="executeDelete">{{ __('Delete') }}</flux:button>
            </div>
        </div>
    </flux:modal>
</div>
