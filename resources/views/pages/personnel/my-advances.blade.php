<?php

use App\Enums\AdvanceStatus;
use App\Models\Advance;
use Illuminate\Pagination\LengthAwarePaginator;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

new #[Lazy, Title('My Advances')] class extends Component
{
    use WithPagination;

    public ?int $editingId = null;

    // Form
    public string $amount         = '';
    public string $currency_code  = 'TRY';
    public string $requested_at   = '';
    public string $repayment_date = '';
    public string $reason         = '';

    // Filter
    public string $filterStatus = '';

    public ?int $confirmingDeleteId = null;

    public function mount(): void
    {
        if (! auth()->user()->employee_id) {
            abort(403);
        }
        $this->requested_at   = now()->format('Y-m-d');
        $this->repayment_date = now()->addMonths(3)->format('Y-m-d');
    }

    public function updatedFilterStatus(): void
    {
        $this->resetPage();
    }

    private function employeeId(): int
    {
        return (int) auth()->user()->employee_id;
    }

    /**
     * @return array{pending:int, approved:int, total_approved_try:float}
     */
    #[Computed]
    public function kpiStats(): array
    {
        $eid = $this->employeeId();

        return [
            'pending'           => Advance::query()->where('employee_id', $eid)->where('status', AdvanceStatus::Pending->value)->count(),
            'approved'          => Advance::query()->where('employee_id', $eid)->where('status', AdvanceStatus::Approved->value)->count(),
            'total_approved_try' => (float) Advance::query()
                ->where('employee_id', $eid)
                ->where('status', AdvanceStatus::Approved->value)
                ->where('currency_code', 'TRY')
                ->sum('amount'),
        ];
    }

    #[Computed]
    public function paginatedAdvances(): LengthAwarePaginator
    {
        $q = Advance::query()->where('employee_id', $this->employeeId());

        if ($this->filterStatus !== '') {
            $q->where('status', $this->filterStatus);
        }

        return $q->orderByDesc('requested_at')->paginate(15);
    }

    public function startCreate(): void
    {
        $this->resetForm();
        $this->editingId = 0;
    }

    public function cancelForm(): void
    {
        $this->editingId = null;
        $this->resetForm();
    }

    public function save(): void
    {
        $validated = $this->validate([
            'amount'         => ['required', 'numeric', 'min:1'],
            'currency_code'  => ['required', 'string', 'size:3'],
            'requested_at'   => ['required', 'date'],
            'repayment_date' => ['required', 'date', 'after:requested_at'],
            'reason'         => ['nullable', 'string', 'max:1000'],
        ]);

        if ($this->editingId && $this->editingId > 0) {
            $advance = Advance::query()
                ->where('employee_id', $this->employeeId())
                ->where('status', AdvanceStatus::Pending->value)
                ->findOrFail($this->editingId);

            $advance->update([
                'amount'         => (float) $validated['amount'],
                'currency_code'  => $validated['currency_code'],
                'requested_at'   => $validated['requested_at'],
                'repayment_date' => $validated['repayment_date'],
                'reason'         => filled($validated['reason']) ? $validated['reason'] : null,
            ]);
        } else {
            Advance::query()->create([
                'employee_id'    => $this->employeeId(),
                'status'         => AdvanceStatus::Pending->value,
                'amount'         => (float) $validated['amount'],
                'currency_code'  => $validated['currency_code'],
                'requested_at'   => $validated['requested_at'],
                'repayment_date' => $validated['repayment_date'],
                'reason'         => filled($validated['reason']) ? $validated['reason'] : null,
            ]);
        }

        $this->editingId = null;
        $this->resetForm();
        $this->resetPage();
        session()->flash('saved', __('Advance request submitted.'));
    }

    public function startEdit(int $id): void
    {
        $advance = Advance::query()
            ->where('employee_id', $this->employeeId())
            ->where('status', AdvanceStatus::Pending->value)
            ->findOrFail($id);

        $this->editingId      = $id;
        $this->amount         = (string) $advance->amount;
        $this->currency_code  = $advance->currency_code;
        $this->requested_at   = $advance->requested_at->format('Y-m-d');
        $this->repayment_date = $advance->repayment_date->format('Y-m-d');
        $this->reason         = $advance->reason ?? '';
    }

    public function confirmDelete(int $id): void
    {
        $this->confirmingDeleteId = $id;
        $this->modal('confirm-delete')->show();
    }

    public function executeDelete(): void
    {
        if ($this->confirmingDeleteId) {
            Advance::query()
                ->where('employee_id', $this->employeeId())
                ->where('status', AdvanceStatus::Pending->value)
                ->findOrFail($this->confirmingDeleteId)
                ->delete();
            $this->confirmingDeleteId = null;
            $this->resetPage();
        }
    }

    private function resetForm(): void
    {
        $this->amount         = '';
        $this->currency_code  = 'TRY';
        $this->requested_at   = now()->format('Y-m-d');
        $this->repayment_date = now()->addMonths(3)->format('Y-m-d');
        $this->reason         = '';
    }
}; ?>

<div class="mx-auto flex w-full max-w-7xl flex-col gap-6 p-4 lg:p-8">

    <div class="flex flex-wrap items-start justify-between gap-4">
        <div>
            <flux:heading size="xl">{{ __('My Advance Requests') }}</flux:heading>
            <flux:text class="mt-1 text-zinc-500">{{ __('Request and track salary advances.') }}</flux:text>
        </div>
        <div class="flex items-center gap-2">
            <flux:link :href="route('personnel.dashboard')" wire:navigate variant="ghost">
                ← {{ __('Dashboard') }}
            </flux:link>
            <flux:button type="button" variant="primary" wire:click="startCreate" icon="plus">
                {{ __('New request') }}
            </flux:button>
        </div>
    </div>

    @if (session('saved'))
        <flux:callout variant="success" icon="check-circle">
            <flux:callout.text>{{ session('saved') }}</flux:callout.text>
        </flux:callout>
    @endif

    {{-- KPI --}}
    <div class="grid gap-3 sm:grid-cols-3">
        <flux:card class="p-4">
            <flux:text class="text-sm text-zinc-500 dark:text-zinc-400">{{ __('Pending') }}</flux:text>
            <flux:heading size="lg" class="{{ $this->kpiStats['pending'] > 0 ? 'text-yellow-500' : '' }}">
                {{ $this->kpiStats['pending'] }}
            </flux:heading>
        </flux:card>
        <flux:card class="p-4">
            <flux:text class="text-sm text-zinc-500 dark:text-zinc-400">{{ __('Approved') }}</flux:text>
            <flux:heading size="lg" class="text-green-600">{{ $this->kpiStats['approved'] }}</flux:heading>
        </flux:card>
        <flux:card class="p-4">
            <flux:text class="text-sm text-zinc-500 dark:text-zinc-400">{{ __('Total approved (TRY)') }}</flux:text>
            <flux:heading size="lg">{{ number_format($this->kpiStats['total_approved_try'], 2) }} ₺</flux:heading>
        </flux:card>
    </div>

    {{-- Create / Edit Form --}}
    @if ($editingId !== null)
        <flux:card class="p-4">
            <flux:heading size="lg" class="mb-4">
                {{ $editingId > 0 ? __('Edit request') : __('New advance request') }}
            </flux:heading>
            <form wire:submit="save" class="grid gap-4 sm:grid-cols-2">
                <flux:input wire:model="amount" type="text" :label="__('Amount')" required />
                <flux:select wire:model="currency_code" :label="__('Currency')">
                    <option value="TRY">TRY</option>
                    <option value="USD">USD</option>
                    <option value="EUR">EUR</option>
                </flux:select>
                <flux:input wire:model="requested_at" type="date" :label="__('Requested date')" required />
                <flux:input wire:model="repayment_date" type="date" :label="__('Repayment date')" required />
                <flux:textarea wire:model="reason" :label="__('Reason (optional)')" rows="2" class="sm:col-span-2" />
                <div class="flex flex-wrap gap-2 sm:col-span-2">
                    <flux:button type="submit" variant="primary">{{ __('Submit request') }}</flux:button>
                    <flux:button type="button" variant="ghost" wire:click="cancelForm">{{ __('Cancel') }}</flux:button>
                </div>
            </form>
        </flux:card>
    @endif

    {{-- Filter + Table --}}
    <flux:card class="p-4">
        <div class="mb-4 flex flex-wrap items-end gap-3">
            <flux:select wire:model.live="filterStatus" :label="__('Status')" class="max-w-[180px]">
                <option value="">{{ __('All') }}</option>
                @foreach (\App\Enums\AdvanceStatus::cases() as $as)
                    <option value="{{ $as->value }}">{{ $as->label() }}</option>
                @endforeach
            </flux:select>
        </div>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-zinc-200 text-sm dark:divide-zinc-700">
                <thead>
                    <tr class="text-start text-zinc-500 dark:text-zinc-400">
                        <th class="py-2 pe-3 font-medium text-end">{{ __('Amount') }}</th>
                        <th class="py-2 pe-3 font-medium">{{ __('Requested') }}</th>
                        <th class="py-2 pe-3 font-medium">{{ __('Repayment') }}</th>
                        <th class="py-2 pe-3 font-medium">{{ __('Status') }}</th>
                        <th class="py-2 pe-3 font-medium">{{ __('Reason') }}</th>
                        <th class="py-2 text-end font-medium">{{ __('Actions') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-zinc-100 dark:divide-zinc-800">
                    @forelse ($this->paginatedAdvances as $advance)
                        <tr>
                            <td class="py-2 pe-3 text-end font-mono text-xs font-semibold">
                                {{ number_format((float) $advance->amount, 2) }} {{ $advance->currency_code }}
                            </td>
                            <td class="py-2 pe-3 whitespace-nowrap text-xs">{{ $advance->requested_at->format('d M Y') }}</td>
                            <td class="py-2 pe-3 whitespace-nowrap text-xs">{{ $advance->repayment_date->format('d M Y') }}</td>
                            <td class="py-2 pe-3">
                                <flux:badge color="{{ $advance->status->color() }}" size="sm">{{ $advance->status->label() }}</flux:badge>
                            </td>
                            <td class="py-2 pe-3 max-w-[200px] truncate text-xs text-zinc-500">
                                {{ $advance->reason ?? '—' }}
                            </td>
                            <td class="py-2 text-end">
                                @if ($advance->status->isPending())
                                    <div class="flex justify-end gap-1">
                                        <flux:button size="sm" variant="ghost" wire:click="startEdit({{ $advance->id }})">
                                            {{ __('Edit') }}
                                        </flux:button>
                                        <flux:button size="sm" variant="danger" wire:click="confirmDelete({{ $advance->id }})">
                                            {{ __('Cancel') }}
                                        </flux:button>
                                    </div>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="py-8 text-center text-zinc-500">
                                {{ __('No advance requests yet.') }}
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="mt-4">{{ $this->paginatedAdvances->links() }}</div>
    </flux:card>

    <flux:modal name="confirm-delete" class="min-w-[22rem]">
        <div class="space-y-6">
            <div>
                <flux:heading size="lg">{{ __('Cancel advance request?') }}</flux:heading>
                <flux:text class="mt-2">{{ __('This will permanently delete the pending request.') }}</flux:text>
            </div>
            <div class="flex justify-end gap-2">
                <flux:modal.close>
                    <flux:button variant="ghost">{{ __('Go back') }}</flux:button>
                </flux:modal.close>
                <flux:button variant="danger" wire:click="executeDelete">{{ __('Delete') }}</flux:button>
            </div>
        </div>
    </flux:modal>
</div>
