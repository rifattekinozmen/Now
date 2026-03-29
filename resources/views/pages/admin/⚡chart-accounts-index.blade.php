<?php

use App\Models\ChartAccount;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Chart of accounts')] class extends Component
{
    public string $code = '';

    public string $name = '';

    public string $type = 'asset';

    public function mount(): void
    {
        Gate::authorize('viewAny', ChartAccount::class);
    }

    public function saveAccount(): void
    {
        Gate::authorize('create', ChartAccount::class);

        $tenantId = auth()->user()?->tenant_id;
        if ($tenantId === null) {
            abort(403);
        }

        $tenantId = (int) $tenantId;

        $validated = $this->validate([
            'code' => ['required', 'string', 'max:32'],
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', 'string', 'in:asset,liability,equity,revenue,expense'],
        ]);

        ChartAccount::query()->create([
            'tenant_id' => $tenantId,
            'code' => $validated['code'],
            'name' => $validated['name'],
            'type' => $validated['type'],
        ]);

        $this->reset('code', 'name');
        $this->type = 'asset';
    }

    /**
     * @return \Illuminate\Database\Eloquent\Collection<int, ChartAccount>
     */
    public function getAccountsProperty(): \Illuminate\Database\Eloquent\Collection
    {
        return ChartAccount::query()->orderBy('code')->get();
    }
}; ?>

<div class="mx-auto flex w-full max-w-5xl flex-col gap-6 p-4 lg:p-8">
    <div class="flex flex-wrap items-center justify-between gap-4">
        <flux:heading size="xl">{{ __('Chart of accounts') }}</flux:heading>
        <flux:button :href="route('admin.finance.index')" variant="ghost" wire:navigate>{{ __('Back to finance summary') }}</flux:button>
    </div>

    @can('create', App\Models\ChartAccount::class)
        <flux:card class="!p-4">
            <flux:heading size="lg" class="mb-4">{{ __('New account') }}</flux:heading>
            <form wire:submit="saveAccount" class="grid gap-4 sm:grid-cols-2">
                <flux:input wire:model="code" :label="__('Code')" required />
                <flux:input wire:model="name" :label="__('Name')" required />
                <div class="sm:col-span-2">
                    <flux:select wire:model="type" :label="__('Type')">
                        <flux:select.option value="asset">{{ __('Asset') }}</flux:select.option>
                        <flux:select.option value="liability">{{ __('Liability') }}</flux:select.option>
                        <flux:select.option value="equity">{{ __('Equity') }}</flux:select.option>
                        <flux:select.option value="revenue">{{ __('Revenue') }}</flux:select.option>
                        <flux:select.option value="expense">{{ __('Expense') }}</flux:select.option>
                    </flux:select>
                </div>
                <div class="sm:col-span-2">
                    <flux:button type="submit" variant="primary">{{ __('Save account') }}</flux:button>
                </div>
            </form>
        </flux:card>
    @endcan

    <flux:card>
        <flux:heading size="lg" class="mb-4">{{ __('Accounts') }}</flux:heading>
        @if ($this->accounts->isEmpty())
            <flux:text class="text-sm text-zinc-600 dark:text-zinc-400">{{ __('No chart accounts yet.') }}</flux:text>
        @else
            <flux:table>
                <flux:table.columns>
                    <flux:table.column>{{ __('Code') }}</flux:table.column>
                    <flux:table.column>{{ __('Name') }}</flux:table.column>
                    <flux:table.column>{{ __('Type') }}</flux:table.column>
                </flux:table.columns>
                <flux:table.rows>
                    @foreach ($this->accounts as $acct)
                        <flux:table.row :key="$acct->id">
                            <flux:table.cell>{{ $acct->code }}</flux:table.cell>
                            <flux:table.cell>{{ $acct->name }}</flux:table.cell>
                            <flux:table.cell>{{ $acct->type }}</flux:table.cell>
                        </flux:table.row>
                    @endforeach
                </flux:table.rows>
            </flux:table>
        @endif
    </flux:card>
</div>
