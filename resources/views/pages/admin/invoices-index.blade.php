<?php

use App\Enums\InvoiceStatus;
use App\Livewire\Concerns\RequiresLogisticsAdmin;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Order;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

new #[Lazy, Title('Invoices')] class extends Component
{
    use RequiresLogisticsAdmin;
    use WithPagination;

    // Filters
    public string $filterSearch = '';

    public string $filterStatus = '';

    public string $filterDateFrom = '';

    public string $filterDateTo = '';

    public bool $filtersOpen = false;

    // Form state
    public bool $showForm = false;

    public ?int $editingId = null;

    public ?int $formCustomerId = null;

    public ?int $formOrderId = null;

    public string $formInvoiceNo = '';

    public string $formInvoiceDate = '';

    public string $formDueDate = '';

    public string $formSubtotal = '';

    public string $formTaxAmount = '';

    public string $formTotal = '';

    public string $formCurrencyCode = 'TRY';

    public string $formStatus = 'draft';

    public string $formNotes = '';

    public function mount(): void
    {
        Gate::authorize('viewAny', Invoice::class);
        $this->formInvoiceDate = now()->format('Y-m-d');
        $this->formDueDate = now()->addDays(30)->format('Y-m-d');
    }

    public function updatedFilterSearch(): void { $this->resetPage(); }

    public function updatedFilterStatus(): void { $this->resetPage(); }

    public function updatedFilterDateFrom(): void { $this->resetPage(); }

    public function updatedFilterDateTo(): void { $this->resetPage(); }

    public function updatedFormSubtotal(): void
    {
        $sub = (float) $this->formSubtotal;
        $tax = (float) $this->formTaxAmount;
        $this->formTotal = number_format($sub + $tax, 2, '.', '');
    }

    public function updatedFormTaxAmount(): void
    {
        $sub = (float) $this->formSubtotal;
        $tax = (float) $this->formTaxAmount;
        $this->formTotal = number_format($sub + $tax, 2, '.', '');
    }

    /**
     * @return array{total: int, draft: int, sent: int, paid: int, overdue: int, total_amount: float}
     */
    #[Computed]
    public function stats(): array
    {
        return [
            'total'        => (int) Invoice::query()->count(),
            'draft'        => (int) Invoice::query()->where('status', 'draft')->count(),
            'sent'         => (int) Invoice::query()->where('status', 'sent')->count(),
            'paid'         => (int) Invoice::query()->where('status', 'paid')->count(),
            'overdue'      => (int) Invoice::query()->where('status', 'overdue')->count(),
            'total_amount' => (float) Invoice::query()->sum('total'),
        ];
    }

    /**
     * @return \Illuminate\Database\Eloquent\Collection<int, Customer>
     */
    #[Computed]
    public function customers(): \Illuminate\Database\Eloquent\Collection
    {
        return Customer::query()->orderBy('name')->get();
    }

    /**
     * @return Builder<Invoice>
     */
    private function invoicesQuery(): Builder
    {
        $q = Invoice::query()->with(['customer', 'order']);

        if ($this->filterSearch !== '') {
            $term = '%'.addcslashes($this->filterSearch, '%_\\').'%';
            $q->where(function (Builder $qq) use ($term): void {
                $qq->where('invoice_no', 'like', $term)
                    ->orWhereHas('customer', fn (Builder $c) => $c->where('name', 'like', $term));
            });
        }

        if ($this->filterStatus !== '') {
            $q->where('status', $this->filterStatus);
        }

        if ($this->filterDateFrom !== '') {
            $q->where('invoice_date', '>=', $this->filterDateFrom);
        }

        if ($this->filterDateTo !== '') {
            $q->where('invoice_date', '<=', $this->filterDateTo);
        }

        return $q->orderByDesc('invoice_date');
    }

    #[Computed]
    public function paginatedInvoices(): LengthAwarePaginator
    {
        return $this->invoicesQuery()->paginate(20);
    }

    public function openCreate(): void
    {
        Gate::authorize('create', Invoice::class);
        $this->resetForm();
        $this->editingId = null;
        $this->showForm = true;
    }

    public function saveInvoice(): void
    {
        Gate::authorize('create', Invoice::class);

        $this->validate([
            'formCustomerId'  => 'required|integer|exists:customers,id',
            'formInvoiceDate' => 'required|date',
            'formSubtotal'    => 'required|numeric|min:0',
            'formTaxAmount'   => 'required|numeric|min:0',
            'formTotal'       => 'required|numeric|min:0',
        ]);

        Invoice::create([
            'tenant_id'    => auth()->user()->tenant_id,
            'customer_id'  => $this->formCustomerId,
            'order_id'     => $this->formOrderId ?: null,
            'invoice_no'   => $this->formInvoiceNo ?: null,
            'invoice_date' => $this->formInvoiceDate,
            'due_date'     => $this->formDueDate ?: null,
            'subtotal'     => $this->formSubtotal,
            'tax_amount'   => $this->formTaxAmount,
            'total'        => $this->formTotal,
            'currency_code' => $this->formCurrencyCode,
            'status'       => $this->formStatus,
            'notes'        => $this->formNotes ?: null,
        ]);

        $this->resetForm();
        $this->showForm = false;
        unset($this->paginatedInvoices, $this->stats);
    }

    public function startEdit(int $id): void
    {
        $invoice = Invoice::findOrFail($id);
        Gate::authorize('update', $invoice);

        $this->editingId = $id;
        $this->formCustomerId = $invoice->customer_id;
        $this->formOrderId = $invoice->order_id;
        $this->formInvoiceNo = $invoice->invoice_no ?? '';
        $this->formInvoiceDate = $invoice->invoice_date->format('Y-m-d');
        $this->formDueDate = $invoice->due_date?->format('Y-m-d') ?? '';
        $this->formSubtotal = (string) $invoice->subtotal;
        $this->formTaxAmount = (string) $invoice->tax_amount;
        $this->formTotal = (string) $invoice->total;
        $this->formCurrencyCode = $invoice->currency_code;
        $this->formStatus = $invoice->status->value;
        $this->formNotes = $invoice->notes ?? '';
        $this->showForm = true;
    }

    public function updateInvoice(): void
    {
        $invoice = Invoice::findOrFail($this->editingId);
        Gate::authorize('update', $invoice);

        $this->validate([
            'formCustomerId'  => 'required|integer|exists:customers,id',
            'formInvoiceDate' => 'required|date',
            'formSubtotal'    => 'required|numeric|min:0',
            'formTaxAmount'   => 'required|numeric|min:0',
            'formTotal'       => 'required|numeric|min:0',
        ]);

        $invoice->update([
            'customer_id'   => $this->formCustomerId,
            'order_id'      => $this->formOrderId ?: null,
            'invoice_no'    => $this->formInvoiceNo ?: null,
            'invoice_date'  => $this->formInvoiceDate,
            'due_date'      => $this->formDueDate ?: null,
            'subtotal'      => $this->formSubtotal,
            'tax_amount'    => $this->formTaxAmount,
            'total'         => $this->formTotal,
            'currency_code' => $this->formCurrencyCode,
            'status'        => $this->formStatus,
            'notes'         => $this->formNotes ?: null,
        ]);

        $this->resetForm();
        $this->showForm = false;
        unset($this->paginatedInvoices, $this->stats);
    }

    public function cancelEdit(): void
    {
        $this->resetForm();
        $this->showForm = false;
    }

    public function deleteInvoice(int $id): void
    {
        $invoice = Invoice::findOrFail($id);
        Gate::authorize('delete', $invoice);
        $invoice->delete();
        unset($this->paginatedInvoices, $this->stats);
    }

    public function markStatus(int $id, string $status): void
    {
        $invoice = Invoice::findOrFail($id);
        Gate::authorize('update', $invoice);
        $invoice->update(['status' => $status]);
        unset($this->paginatedInvoices, $this->stats);
    }

    private function resetForm(): void
    {
        $this->editingId = null;
        $this->formCustomerId = null;
        $this->formOrderId = null;
        $this->formInvoiceNo = '';
        $this->formInvoiceDate = now()->format('Y-m-d');
        $this->formDueDate = now()->addDays(30)->format('Y-m-d');
        $this->formSubtotal = '';
        $this->formTaxAmount = '';
        $this->formTotal = '';
        $this->formCurrencyCode = 'TRY';
        $this->formStatus = 'draft';
        $this->formNotes = '';
    }
}; ?>

<div class="mx-auto flex w-full max-w-7xl flex-col gap-6 p-4 lg:p-8">
    <x-admin.page-header :heading="__('Invoices')">
        <x-slot name="actions">
            <flux:button variant="primary" wire:click="openCreate" icon="plus">
                {{ __('New Invoice') }}
            </flux:button>
        </x-slot>
    </x-admin.page-header>

    {{-- KPI Cards --}}
    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <flux:card>
            <flux:heading>{{ __('Total Invoices') }}</flux:heading>
            <p class="mt-1 text-2xl font-bold">{{ $this->stats['total'] }}</p>
        </flux:card>
        <flux:card>
            <flux:heading>{{ __('Draft / Sent') }}</flux:heading>
            <p class="mt-1 text-2xl font-bold text-zinc-600">
                {{ $this->stats['draft'] }} / {{ $this->stats['sent'] }}
            </p>
        </flux:card>
        <flux:card>
            <flux:heading>{{ __('Paid / Overdue') }}</flux:heading>
            <p class="mt-1 text-2xl font-bold">
                <span class="text-green-600">{{ $this->stats['paid'] }}</span>
                /
                <span class="text-red-600">{{ $this->stats['overdue'] }}</span>
            </p>
        </flux:card>
        <flux:card>
            <flux:heading>{{ __('Total Amount') }}</flux:heading>
            <p class="mt-1 text-2xl font-bold">{{ number_format($this->stats['total_amount'], 0) }} TRY</p>
        </flux:card>
    </div>

    {{-- Create / Edit Form --}}
    @if ($showForm)
        <flux:card>
            <flux:heading class="mb-4">{{ $editingId ? __('Edit Invoice') : __('New Invoice') }}</flux:heading>
            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                <flux:select wire:model="formCustomerId" :label="__('Customer')" required>
                    <flux:select.option value="">{{ __('Select customer') }}</flux:select.option>
                    @foreach ($this->customers as $customer)
                        <flux:select.option :value="$customer->id">{{ $customer->name }}</flux:select.option>
                    @endforeach
                </flux:select>

                <flux:input wire:model="formInvoiceNo" :label="__('Invoice No')" placeholder="INV-0001" />
                <flux:input wire:model="formInvoiceDate" :label="__('Invoice Date')" type="date" required />
                <flux:input wire:model="formDueDate" :label="__('Due Date')" type="date" />

                <flux:input wire:model.live="formSubtotal" :label="__('Subtotal')" type="number" step="0.01" min="0" />
                <flux:input wire:model.live="formTaxAmount" :label="__('Tax Amount')" type="number" step="0.01" min="0" />
                <flux:input wire:model="formTotal" :label="__('Total')" type="number" step="0.01" min="0" />

                <flux:select wire:model="formCurrencyCode" :label="__('Currency')">
                    <flux:select.option value="TRY">TRY</flux:select.option>
                    <flux:select.option value="USD">USD</flux:select.option>
                    <flux:select.option value="EUR">EUR</flux:select.option>
                </flux:select>

                <flux:select wire:model="formStatus" :label="__('Status')">
                    @foreach (InvoiceStatus::cases() as $s)
                        <flux:select.option :value="$s->value">{{ $s->label() }}</flux:select.option>
                    @endforeach
                </flux:select>

                <div class="sm:col-span-2 lg:col-span-3">
                    <flux:textarea wire:model="formNotes" :label="__('Notes')" rows="2" />
                </div>
            </div>
            <div class="mt-4 flex gap-2">
                @if ($editingId)
                    <flux:button variant="primary" wire:click="updateInvoice">{{ __('Save Changes') }}</flux:button>
                @else
                    <flux:button variant="primary" wire:click="saveInvoice">{{ __('Create Invoice') }}</flux:button>
                @endif
                <flux:button wire:click="cancelEdit">{{ __('Cancel') }}</flux:button>
            </div>
        </flux:card>
    @endif

    {{-- Filter bar --}}
    <flux:card class="p-4">
        <div class="flex flex-wrap items-center justify-between gap-2">
            <flux:input
                wire:model.live.debounce.400ms="filterSearch"
                :placeholder="__('Search invoice no / customer')"
                icon="magnifying-glass"
                class="max-w-full min-w-0 flex-1 sm:max-w-md"
            />
            <flux:button variant="ghost" wire:click="$toggle('filtersOpen')" icon="{{ $filtersOpen ? 'chevron-up' : 'chevron-down' }}">
                {{ __('Filters') }}
            </flux:button>
        </div>
        @if ($filtersOpen)
            <div class="mt-3 flex flex-wrap gap-3">
                <flux:select wire:model.live="filterStatus" :label="__('Status')">
                    <flux:select.option value="">{{ __('All statuses') }}</flux:select.option>
                    @foreach (InvoiceStatus::cases() as $s)
                        <flux:select.option :value="$s->value">{{ $s->label() }}</flux:select.option>
                    @endforeach
                </flux:select>
                <flux:input wire:model.live="filterDateFrom" :label="__('From')" type="date" />
                <flux:input wire:model.live="filterDateTo" :label="__('To')" type="date" />
            </div>
        @endif
    </flux:card>

    {{-- Table --}}
    <flux:card>
        <flux:table>
            <flux:table.columns>
                <flux:table.column>{{ __('Invoice No') }}</flux:table.column>
                <flux:table.column>{{ __('Customer') }}</flux:table.column>
                <flux:table.column>{{ __('Date') }}</flux:table.column>
                <flux:table.column>{{ __('Due Date') }}</flux:table.column>
                <flux:table.column>{{ __('Total') }}</flux:table.column>
                <flux:table.column>{{ __('Status') }}</flux:table.column>
                <flux:table.column>{{ __('Actions') }}</flux:table.column>
            </flux:table.columns>
            <flux:table.rows>
                @forelse ($this->paginatedInvoices as $invoice)
                    <flux:table.row :key="$invoice->id">
                        <flux:table.cell class="font-mono text-sm">
                            {{ $invoice->invoice_no ?? '—' }}
                        </flux:table.cell>
                        <flux:table.cell>{{ $invoice->customer?->name ?? '—' }}</flux:table.cell>
                        <flux:table.cell>{{ $invoice->invoice_date->format('d M Y') }}</flux:table.cell>
                        <flux:table.cell>
                            @if ($invoice->due_date)
                                <span @class(['text-red-600' => $invoice->due_date->isPast() && $invoice->status !== \App\Enums\InvoiceStatus::Paid])>
                                    {{ $invoice->due_date->format('d M Y') }}
                                </span>
                            @else
                                —
                            @endif
                        </flux:table.cell>
                        <flux:table.cell class="font-semibold">
                            {{ number_format((float) $invoice->total, 2) }} {{ $invoice->currency_code }}
                        </flux:table.cell>
                        <flux:table.cell>
                            <flux:badge :color="$invoice->status->color()" size="sm">
                                {{ $invoice->status->label() }}
                            </flux:badge>
                        </flux:table.cell>
                        <flux:table.cell>
                            <div class="flex gap-1">
                                @if ($invoice->status === \App\Enums\InvoiceStatus::Draft)
                                    <flux:button size="sm" wire:click="markStatus({{ $invoice->id }}, 'sent')">
                                        {{ __('Send') }}
                                    </flux:button>
                                @endif
                                @if ($invoice->status === \App\Enums\InvoiceStatus::Sent)
                                    <flux:button size="sm" variant="primary" wire:click="markStatus({{ $invoice->id }}, 'paid')">
                                        {{ __('Mark Paid') }}
                                    </flux:button>
                                @endif
                                <flux:button size="sm" icon="pencil" wire:click="startEdit({{ $invoice->id }})" />
                                <flux:button size="sm" variant="danger" icon="trash"
                                    wire:click="deleteInvoice({{ $invoice->id }})"
                                    wire:confirm="{{ __('Delete this invoice?') }}" />
                            </div>
                        </flux:table.cell>
                    </flux:table.row>
                @empty
                    <flux:table.row>
                        <flux:table.cell colspan="7" class="text-center text-zinc-500">
                            {{ __('No invoices found.') }}
                        </flux:table.cell>
                    </flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>
        <div class="mt-4">
            {{ $this->paginatedInvoices->links() }}
        </div>
    </flux:card>
</div>
