<?php

namespace App\Livewire;

use App\Authorization\LogisticsPermission;
use App\Models\Customer;
use App\Models\Employee;
use App\Models\Order;
use App\Models\Shipment;
use App\Models\Vehicle;
use App\Models\Warehouse;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Component;

class GlobalSearch extends Component
{
    public bool $open = false;

    public string $q = '';

    public function openSearch(): void
    {
        $user = Auth::user();
        if ($user === null) {
            return;
        }

        if (! $user->can(LogisticsPermission::ADMIN) && ! $user->can(LogisticsPermission::VIEW)) {
            return;
        }

        $this->open = true;
    }

    public function closeSearch(): void
    {
        $this->open = false;
        $this->q = '';
    }

    /**
     * @return list<array{label: string, url: string, kind: string}>
     */
    #[Computed]
    public function results(): array
    {
        $user = Auth::user();
        if ($user === null || (! $user->can(LogisticsPermission::ADMIN) && ! $user->can(LogisticsPermission::VIEW))) {
            return [];
        }

        $raw = trim($this->q);
        if (strlen($raw) < 2) {
            return [];
        }

        $term = '%'.addcslashes($raw, '%_\\').'%';
        $out = [];

        foreach (Vehicle::query()->where('plate', 'like', $term)->orderBy('plate')->limit(5)->get() as $v) {
            $out[] = [
                'kind' => 'vehicle',
                'label' => __('Vehicle').': '.$v->plate,
                'url' => route('admin.vehicles.index'),
            ];
        }

        foreach (Customer::query()->where(function ($q) use ($term): void {
            $q->where('legal_name', 'like', $term)->orWhere('trade_name', 'like', $term);
        })->orderBy('legal_name')->limit(5)->get() as $c) {
            $out[] = [
                'kind' => 'customer',
                'label' => __('Customer').': '.$c->legal_name,
                'url' => route('admin.customers.show', $c),
            ];
        }

        $orders = Order::query()
            ->where(function ($q) use ($raw, $term): void {
                $q->where('order_number', 'like', $term);
                if (ctype_digit($raw)) {
                    $q->orWhere('id', (int) $raw);
                }
            })
            ->orderByDesc('id')
            ->limit(5)
            ->get();

        foreach ($orders as $o) {
            $out[] = [
                'kind' => 'order',
                'label' => __('Order').' #'.$o->order_number.' (ID '.$o->id.')',
                'url' => route('admin.orders.show', $o),
            ];
        }

        foreach (Shipment::query()->where(function ($q) use ($raw, $term): void {
            $q->where('public_reference_token', 'like', $term);
            if (ctype_digit($raw)) {
                $q->orWhere('id', (int) $raw);
            }
        })->orderByDesc('id')->limit(5)->get() as $s) {
            $out[] = [
                'kind' => 'shipment',
                'label' => __('Shipment').' #'.$s->id,
                'url' => route('admin.shipments.show', $s),
            ];
        }

        foreach (Warehouse::query()->where(function ($q) use ($term): void {
            $q->where('code', 'like', $term)->orWhere('name', 'like', $term);
        })->orderBy('code')->limit(5)->get() as $w) {
            $out[] = [
                'kind' => 'warehouse',
                'label' => __('Warehouse').': '.$w->code.' — '.$w->name,
                'url' => route('admin.warehouse.index'),
            ];
        }

        foreach (Employee::query()->where(function ($q) use ($term): void {
            $q->where('first_name', 'like', $term)
                ->orWhere('last_name', 'like', $term)
                ->orWhere('national_id', 'like', $term);
        })->orderBy('last_name')->limit(5)->get() as $e) {
            $out[] = [
                'kind' => 'employee',
                'label' => __('Employee').': '.$e->first_name.' '.$e->last_name,
                'url' => route('admin.employees.index'),
            ];
        }

        return array_slice($out, 0, 15);
    }

    public function render(): View
    {
        return view('livewire.global-search');
    }
}
