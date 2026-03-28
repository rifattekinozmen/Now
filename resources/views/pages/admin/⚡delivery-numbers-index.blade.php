<?php

use App\Enums\DeliveryNumberStatus;
use App\Models\DeliveryNumber;
use App\Models\Order;
use App\Services\Logistics\ExcelImportService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithFileUploads;

new #[Title('PIN pool')] class extends Component
{
    use WithFileUploads;

    public string $pin_code = '';

    public string $sas_no = '';

    public string $assign_pin_code = '';

    public string $assign_order_id = '';

    public mixed $pinImportFile = null;

    public function mount(): void
    {
        Gate::authorize('viewAny', DeliveryNumber::class);
    }

    /**
     * @return \Illuminate\Database\Eloquent\Collection<int, Order>
     */
    public function orderOptions()
    {
        return Order::query()->with('customer')->orderByDesc('id')->limit(200)->get();
    }

    /**
     * @return \Illuminate\Database\Eloquent\Collection<int, DeliveryNumber>
     */
    public function deliveryNumberList()
    {
        return DeliveryNumber::query()->with(['order.customer'])->orderByDesc('id')->limit(200)->get();
    }

    public function addPin(): void
    {
        Gate::authorize('create', DeliveryNumber::class);

        $tenantId = auth()->user()->tenant_id;
        if ($tenantId === null) {
            abort(403);
        }

        $validated = $this->validate([
            'pin_code' => [
                'required',
                'string',
                'max:64',
                Rule::unique('delivery_numbers', 'pin_code')->where('tenant_id', $tenantId),
            ],
            'sas_no' => ['nullable', 'string', 'max:64'],
        ]);

        DeliveryNumber::query()->create([
            'pin_code' => trim($validated['pin_code']),
            'sas_no' => isset($validated['sas_no']) && $validated['sas_no'] !== '' ? trim($validated['sas_no']) : null,
            'status' => DeliveryNumberStatus::Available,
        ]);

        $this->reset('pin_code', 'sas_no');
    }

    public function assignPinToOrder(): void
    {
        $tenantId = auth()->user()->tenant_id;
        if ($tenantId === null) {
            abort(403);
        }

        $validated = $this->validate([
            'assign_pin_code' => ['required', 'string', 'max:64'],
            'assign_order_id' => [
                'required',
                'integer',
                Rule::exists('orders', 'id')->where('tenant_id', $tenantId),
            ],
        ]);

        $pin = trim($validated['assign_pin_code']);

        /** @var DeliveryNumber|null $dn */
        $dn = DeliveryNumber::query()
            ->where('pin_code', $pin)
            ->where('status', DeliveryNumberStatus::Available)
            ->first();

        if ($dn === null) {
            $this->addError('assign_pin_code', __('No available PIN matches this code.'));

            return;
        }

        Gate::authorize('update', $dn);

        /** @var Order $order */
        $order = Order::query()->findOrFail((int) $validated['assign_order_id']);

        DB::transaction(function () use ($dn, $order): void {
            $dn->update([
                'order_id' => $order->id,
                'status' => DeliveryNumberStatus::Assigned,
                'assigned_at' => now(),
            ]);

            if ($order->sas_no === null && $dn->sas_no !== null) {
                $order->update(['sas_no' => $dn->sas_no]);
            }
        });

        $this->reset('assign_pin_code', 'assign_order_id');
    }

    public function deleteAvailable(int $id): void
    {
        $dn = DeliveryNumber::query()->findOrFail($id);
        Gate::authorize('delete', $dn);

        if ($dn->status !== DeliveryNumberStatus::Available) {
            return;
        }

        $dn->delete();
    }

    public function importPinsCsv(ExcelImportService $excel): void
    {
        Gate::authorize('create', DeliveryNumber::class);

        $tenantId = auth()->user()->tenant_id;
        if ($tenantId === null) {
            abort(403);
        }

        $this->validate([
            'pinImportFile' => ['required', 'file', 'mimes:csv,txt', 'max:5120'],
        ]);

        $path = $this->pinImportFile->getRealPath();
        if ($path === false || ! is_readable($path)) {
            session()->flash('error', __('Could not read the uploaded file.'));

            return;
        }

        $result = $excel->importDeliveryPinsFromPath($path, (int) $tenantId);
        $this->reset('pinImportFile');
        session()->flash('pin_import_result', $result);
    }
}; ?>

<x-layouts::app :title="__('PIN pool')">
    <div class="mx-auto flex w-full max-w-6xl flex-col gap-6 p-4 lg:p-8">
        <flux:heading size="xl">{{ __('PIN pool') }}</flux:heading>

        @if (session()->has('pin_import_result'))
            @php($r = session('pin_import_result'))
            <flux:callout variant="success" icon="check-circle" class="mb-2">
                {{ __('Imported: :c, skipped (already in pool): :s', ['c' => $r['created'], 's' => $r['skipped']]) }}
            </flux:callout>
            @if ($r['errors'] !== [])
                <flux:callout variant="danger" icon="exclamation-triangle">
                    <flux:callout.heading>{{ __('Import row errors') }}</flux:callout.heading>
                    <flux:callout.text>
                        <ul class="list-inside list-disc text-sm">
                            @foreach (array_slice($r['errors'], 0, 15) as $err)
                                <li>{{ __('Row :row: :msg', ['row' => $err['row'], 'msg' => $err['message']]) }}</li>
                            @endforeach
                        </ul>
                    </flux:callout.text>
                </flux:callout>
            @endif
        @endif
        @if (session()->has('error'))
            <flux:callout variant="danger" icon="exclamation-triangle">{{ session('error') }}</flux:callout>
        @endif

        <flux:card>
            <flux:heading size="lg" class="mb-4">{{ __('Bulk import (CSV)') }}</flux:heading>
            <flux:text class="mb-4 text-sm text-zinc-600 dark:text-zinc-400">
                {{ __('First row: headers') }} <code class="rounded bg-zinc-100 px-1 dark:bg-zinc-700">pin_code</code> {{ __('or') }} <code class="rounded bg-zinc-100 px-1 dark:bg-zinc-700">PIN Kodu</code>;
                {{ __('optional') }} <code class="rounded bg-zinc-100 px-1 dark:bg-zinc-700">sas_no</code> / <code class="rounded bg-zinc-100 px-1 dark:bg-zinc-700">SAS</code>.
            </flux:text>
            <div class="flex max-w-xl flex-col gap-4">
                <flux:field :label="__('CSV file')">
                    <input
                        type="file"
                        wire:model="pinImportFile"
                        accept=".csv,text/csv,text/plain"
                        class="block w-full text-sm text-zinc-600 file:me-4 file:rounded-lg file:border-0 file:bg-zinc-100 file:px-4 file:py-2 file:text-sm file:font-medium file:text-zinc-900 dark:text-zinc-400 dark:file:bg-zinc-700 dark:file:text-zinc-100"
                    />
                </flux:field>
                <flux:button type="button" variant="filled" wire:click="importPinsCsv" wire:loading.attr="disabled">
                    {{ __('Import CSV') }}
                </flux:button>
            </div>
        </flux:card>

        <flux:card>
            <flux:heading size="lg" class="mb-4">{{ __('Add PIN') }}</flux:heading>
            <form wire:submit="addPin" class="flex max-w-xl flex-col gap-4">
                <flux:field :label="__('PIN code')">
                    <flux:input wire:model="pin_code" required maxlength="64" />
                </flux:field>
                <flux:field :label="__('SAS no. (optional)')">
                    <flux:input wire:model="sas_no" maxlength="64" />
                </flux:field>
                <flux:button type="submit" variant="primary">{{ __('Save PIN') }}</flux:button>
            </form>
        </flux:card>

        <flux:card>
            <flux:heading size="lg" class="mb-4">{{ __('Assign PIN to order') }}</flux:heading>
            <form wire:submit="assignPinToOrder" class="flex max-w-xl flex-col gap-4">
                <flux:field :label="__('PIN code')">
                    <flux:input wire:model="assign_pin_code" required maxlength="64" />
                </flux:field>
                <div>
                    <flux:field :label="__('Order')">
                        <select
                            wire:model="assign_order_id"
                            required
                            class="w-full rounded-lg border border-zinc-200 bg-white px-3 py-2 text-sm text-zinc-900 dark:border-zinc-600 dark:bg-zinc-800 dark:text-zinc-100"
                        >
                            <option value="">{{ __('Select…') }}</option>
                            @foreach ($this->orderOptions() as $o)
                                <option value="{{ $o->id }}">{{ $o->order_number }} — {{ $o->customer?->legal_name ?? '—' }}</option>
                            @endforeach
                        </select>
                    </flux:field>
                </div>
                <flux:button type="submit" variant="filled">{{ __('Assign') }}</flux:button>
            </form>
        </flux:card>

        <flux:card>
            <flux:heading size="lg" class="mb-4">{{ __('Recent PINs') }}</flux:heading>
            <flux:table>
                <flux:table.columns>
                    <flux:table.column>{{ __('PIN') }}</flux:table.column>
                    <flux:table.column>{{ __('SAS') }}</flux:table.column>
                    <flux:table.column>{{ __('Status') }}</flux:table.column>
                    <flux:table.column>{{ __('Order') }}</flux:table.column>
                    <flux:table.column></flux:table.column>
                </flux:table.columns>
                <flux:table.rows>
                    @forelse ($this->deliveryNumberList() as $row)
                        <flux:table.row :key="$row->id">
                            <flux:table.cell>{{ $row->pin_code }}</flux:table.cell>
                            <flux:table.cell>{{ $row->sas_no ?? '—' }}</flux:table.cell>
                            <flux:table.cell>{{ $row->status->value }}</flux:table.cell>
                            <flux:table.cell>{{ $row->order?->order_number ?? '—' }}</flux:table.cell>
                            <flux:table.cell>
                                @if ($row->status === \App\Enums\DeliveryNumberStatus::Available)
                                    <flux:button type="button" size="sm" variant="ghost" wire:click="deleteAvailable({{ $row->id }})" wire:confirm="{{ __('Delete this PIN?') }}">
                                        {{ __('Delete') }}
                                    </flux:button>
                                @endif
                            </flux:table.cell>
                        </flux:table.row>
                    @empty
                        <flux:table.row>
                            <flux:table.cell>{{ __('No PINs yet.') }}</flux:table.cell>
                            <flux:table.cell></flux:table.cell>
                            <flux:table.cell></flux:table.cell>
                            <flux:table.cell></flux:table.cell>
                            <flux:table.cell></flux:table.cell>
                        </flux:table.row>
                    @endforelse
                </flux:table.rows>
            </flux:table>
        </flux:card>
    </div>
</x-layouts::app>
