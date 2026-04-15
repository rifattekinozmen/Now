<?php

use App\Models\ChartAccount;
use App\Models\FiscalOpeningBalance;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Lazy, Title('Fiscal opening balances')] class extends Component
{
    public int $filterFiscalYear;

    public ?int $editingId = null;

    public string $chart_account_id = '';

    public int $entryFiscalYear;

    public string $opening_debit = '0.00';

    public string $opening_credit = '0.00';

    public function mount(): void
    {
        Gate::authorize('viewAny', FiscalOpeningBalance::class);
        $this->filterFiscalYear = (int) now()->year;
        $this->entryFiscalYear = $this->filterFiscalYear;
    }

    public function updatedFilterFiscalYear(mixed $value): void
    {
        $this->filterFiscalYear = max(2000, min(2100, (int) $value));
        if ($this->editingId === null) {
            $this->entryFiscalYear = $this->filterFiscalYear;
        }
    }

    public function startCreate(): void
    {
        Gate::authorize('create', FiscalOpeningBalance::class);
        $this->editingId = null;
        $this->chart_account_id = '';
        $this->entryFiscalYear = $this->filterFiscalYear;
        $this->opening_debit = '0.00';
        $this->opening_credit = '0.00';
    }

    public function startEdit(int $id): void
    {
        $row = FiscalOpeningBalance::query()->with('chartAccount')->findOrFail($id);
        Gate::authorize('update', $row);
        $this->editingId = $row->id;
        $this->chart_account_id = (string) $row->chart_account_id;
        $this->entryFiscalYear = (int) $row->fiscal_year;
        $this->opening_debit = (string) $row->opening_debit;
        $this->opening_credit = (string) $row->opening_credit;
    }

    public function cancelEdit(): void
    {
        $this->editingId = null;
        $this->chart_account_id = '';
        $this->entryFiscalYear = $this->filterFiscalYear;
        $this->opening_debit = '0.00';
        $this->opening_credit = '0.00';
    }

    public function saveEntry(): void
    {
        $user = auth()->user();
        if ($user === null || $user->tenant_id === null) {
            abort(403);
        }

        $tenantId = (int) $user->tenant_id;

        if ($this->editingId !== null) {
            $model = FiscalOpeningBalance::query()->findOrFail($this->editingId);
            Gate::authorize('update', $model);
        } else {
            Gate::authorize('create', FiscalOpeningBalance::class);
        }

        $uniqueChart = Rule::unique('fiscal_opening_balances', 'chart_account_id')
            ->where('tenant_id', $tenantId)
            ->where('fiscal_year', $this->entryFiscalYear);

        if ($this->editingId !== null) {
            $uniqueChart->ignore($this->editingId);
        }

        $validated = $this->validate([
            'chart_account_id' => [
                'required',
                'integer',
                Rule::exists('chart_accounts', 'id')->where('tenant_id', $tenantId),
                $uniqueChart,
            ],
            'entryFiscalYear' => ['required', 'integer', 'min:2000', 'max:2100'],
            'opening_debit' => ['required', 'numeric', 'min:0'],
            'opening_credit' => ['required', 'numeric', 'min:0'],
        ], [], [
            'chart_account_id' => __('Account'),
            'entryFiscalYear' => __('Fiscal year'),
            'opening_debit' => __('Debit'),
            'opening_credit' => __('Credit'),
        ]);

        $payload = [
            'tenant_id' => $tenantId,
            'chart_account_id' => (int) $validated['chart_account_id'],
            'fiscal_year' => $validated['entryFiscalYear'],
            'opening_debit' => number_format((float) $validated['opening_debit'], 2, '.', ''),
            'opening_credit' => number_format((float) $validated['opening_credit'], 2, '.', ''),
        ];

        if ($this->editingId !== null) {
            FiscalOpeningBalance::query()->whereKey($this->editingId)->update([
                'chart_account_id' => $payload['chart_account_id'],
                'fiscal_year' => $payload['fiscal_year'],
                'opening_debit' => $payload['opening_debit'],
                'opening_credit' => $payload['opening_credit'],
            ]);
        } else {
            FiscalOpeningBalance::query()->create($payload);
        }

        $this->cancelEdit();
    }

    public function deleteEntry(int $id): void
    {
        $row = FiscalOpeningBalance::query()->findOrFail($id);
        Gate::authorize('delete', $row);
        $row->delete();
        if ($this->editingId === $id) {
            $this->cancelEdit();
        }
    }

    /**
     * @return \Illuminate\Database\Eloquent\Collection<int, FiscalOpeningBalance>
     */
    #[Computed]
    public function rows(): \Illuminate\Database\Eloquent\Collection
    {
        return FiscalOpeningBalance::query()
            ->with('chartAccount')
            ->where('fiscal_year', $this->filterFiscalYear)
            ->orderBy('chart_account_id')
            ->get();
    }

    /**
     * @return \Illuminate\Database\Eloquent\Collection<int, ChartAccount>
     */
    #[Computed]
    public function accounts(): \Illuminate\Database\Eloquent\Collection
    {
        return ChartAccount::query()->orderBy('code')->get();
    }
}; ?>

<div class="mx-auto flex w-full max-w-7xl flex-col gap-6 p-4 lg:p-8">
    <x-admin.page-header :heading="__('Fiscal opening balances')">
        <x-slot name="actions">
            <flux:button :href="route('admin.finance.index')" variant="ghost" wire:navigate>{{ __('Finance summary') }}</flux:button>
            <flux:button :href="route('admin.finance.balance-sheet')" variant="ghost" wire:navigate>{{ __('Balance sheet summary') }}</flux:button>
        </x-slot>
    </x-admin.page-header>

    <flux:callout variant="warning" icon="exclamation-triangle">
        <flux:callout.heading>{{ __('Operational reference only') }}</flux:callout.heading>
        <flux:callout.text>
            {{ __('Opening balances are merged into the balance sheet summary when enabled there. Not statutory reporting.') }}
        </flux:callout.text>
    </flux:callout>

    <x-admin.filter-bar :label="__('Filter by fiscal year')">
        <flux:input wire:model.live="filterFiscalYear" type="number" min="2000" max="2100" :label="__('Fiscal year')" class="max-w-xs" />
    </x-admin.filter-bar>

    @can('create', App\Models\FiscalOpeningBalance::class)
        <flux:card class="!p-4">
            <flux:heading size="lg" class="mb-4">{{ $this->editingId ? __('Edit opening balance') : __('New opening balance') }}</flux:heading>
            <form wire:submit="saveEntry" class="grid gap-4 sm:grid-cols-2">
                <flux:select wire:model="chart_account_id" :label="__('Account')" required>
                    <option value="">{{ __('Select account') }}</option>
                    @foreach ($this->accounts as $acct)
                        <option value="{{ $acct->id }}">{{ $acct->code }} — {{ $acct->name }}</option>
                    @endforeach
                </flux:select>
                <flux:input wire:model="entryFiscalYear" type="number" min="2000" max="2100" :label="__('Fiscal year')" required />
                <flux:input wire:model="opening_debit" type="text" inputmode="decimal" :label="__('Debit (opening)')" required />
                <flux:input wire:model="opening_credit" type="text" inputmode="decimal" :label="__('Credit (opening)')" required />
                <div class="flex flex-wrap gap-2 sm:col-span-2">
                    <flux:button type="submit" variant="primary">{{ __('Save') }}</flux:button>
                    @if ($this->editingId !== null)
                        <flux:button type="button" wire:click="cancelEdit" variant="ghost">{{ __('Cancel') }}</flux:button>
                    @endif
                </div>
            </form>
        </flux:card>
    @endcan

    <flux:card>
        <flux:heading size="lg" class="mb-4">{{ __('Opening balances') }} ({{ $this->filterFiscalYear }})</flux:heading>
        @if ($this->rows->isEmpty())
            <flux:text class="text-sm text-zinc-600 dark:text-zinc-400">{{ __('No fiscal opening balances for this year.') }}</flux:text>
        @else
            <flux:table>
                <flux:table.columns>
                    <flux:table.column>{{ __('Account') }}</flux:table.column>
                    <flux:table.column align="end">{{ __('Debit') }}</flux:table.column>
                    <flux:table.column align="end">{{ __('Credit') }}</flux:table.column>
                    <flux:table.column>{{ __('Actions') }}</flux:table.column>
                </flux:table.columns>
                <flux:table.rows>
                    @foreach ($this->rows as $row)
                        <flux:table.row wire:key="fob-{{ $row->id }}">
                            <flux:table.cell>
                                {{ $row->chartAccount?->code }}
                                —
                                {{ $row->chartAccount?->name }}
                            </flux:table.cell>
                            <flux:table.cell align="end">{{ $row->opening_debit }}</flux:table.cell>
                            <flux:table.cell align="end">{{ $row->opening_credit }}</flux:table.cell>
                            <flux:table.cell>
                                <div class="flex flex-wrap gap-2">
                                    @can('update', $row)
                                        <flux:button size="sm" variant="ghost" wire:click="startEdit({{ $row->id }})">{{ __('Edit') }}</flux:button>
                                    @endcan
                                    @can('delete', $row)
                                        <flux:button size="sm" variant="ghost" wire:click="deleteEntry({{ $row->id }})" wire:confirm="{{ __('Delete this opening balance?') }}">{{ __('Delete') }}</flux:button>
                                    @endcan
                                </div>
                            </flux:table.cell>
                        </flux:table.row>
                    @endforeach
                </flux:table.rows>
            </flux:table>
        @endif
    </flux:card>
</div>
