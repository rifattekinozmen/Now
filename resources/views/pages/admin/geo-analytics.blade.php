<?php

use App\Models\Order;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Geo Analytics')] class extends Component
{
    public string $period = '30d';

    public function mount(): void
    {
        Gate::authorize('viewAny', Order::class);
    }

    private function periodCutoff(): \Carbon\CarbonInterface
    {
        return match ($this->period) {
            '7d'  => now()->subDays(7),
            '90d' => now()->subDays(90),
            '12m' => now()->subMonths(12),
            default => now()->subDays(30),
        };
    }

    /**
     * City-level stats grouped from orders → customers → tax_offices.
     *
     * @return Collection<int, object{city: string, order_count: int, total_freight: float, avg_freight: float}>
     */
    #[Computed]
    public function cityStats(): Collection
    {
        $tenantId = auth()->user()->tenant_id;
        $cutoff   = $this->periodCutoff()->toDateTimeString();

        return DB::table('orders as o')
            ->join('customers as c', 'o.customer_id', '=', 'c.id')
            ->join('tax_offices as t', 'c.tax_office_id', '=', 't.id')
            ->select(
                't.city',
                DB::raw('COUNT(o.id) as order_count'),
                DB::raw('COALESCE(SUM(o.freight_amount), 0) as total_freight'),
                DB::raw('COALESCE(AVG(o.freight_amount), 0) as avg_freight'),
            )
            ->where('o.tenant_id', $tenantId)
            ->where('o.ordered_at', '>=', $cutoff)
            ->groupBy('t.city')
            ->orderByDesc('total_freight')
            ->limit(30)
            ->get();
    }

    /**
     * @return array{total_orders: int, total_freight: float, city_count: int}
     */
    #[Computed]
    public function kpi(): array
    {
        $stats = $this->cityStats;

        return [
            'total_orders'  => (int) $stats->sum('order_count'),
            'total_freight' => (float) $stats->sum('total_freight'),
            'city_count'    => $stats->count(),
        ];
    }
}; ?>

<div class="mx-auto flex w-full max-w-7xl flex-col gap-6 p-4 lg:p-8">

    <x-admin.page-header
        :heading="__('Geo Analytics')"
        :description="__('City-based order volume and freight summary across Turkey.')"
    />

    {{-- Analytics tab navigation --}}
    <div class="flex gap-1 border-b border-zinc-200 dark:border-zinc-700">
        <a href="{{ route('admin.analytics.fleet') }}" wire:navigate class="border-b-2 border-transparent px-4 py-2 text-sm font-medium text-zinc-500 hover:text-zinc-700 dark:text-zinc-400 dark:hover:text-zinc-200">{{ __('Fleet') }}</a>
        <a href="{{ route('admin.analytics.operations') }}" wire:navigate class="border-b-2 border-transparent px-4 py-2 text-sm font-medium text-zinc-500 hover:text-zinc-700 dark:text-zinc-400 dark:hover:text-zinc-200">{{ __('Operations') }}</a>
        <a href="{{ route('admin.analytics.cost-centers') }}" wire:navigate class="border-b-2 border-transparent px-4 py-2 text-sm font-medium text-zinc-500 hover:text-zinc-700 dark:text-zinc-400 dark:hover:text-zinc-200">{{ __('Finance P&L') }}</a>
        <a href="{{ route('admin.analytics.geo') }}" wire:navigate class="border-b-2 border-primary px-4 py-2 text-sm font-medium text-primary">{{ __('Geo') }}</a>
    </div>

    {{-- Period selector --}}
    <div class="flex flex-wrap gap-2">
        @foreach (['7d' => __('Last 7 days'), '30d' => __('Last 30 days'), '90d' => __('Last 90 days'), '12m' => __('Last 12 months')] as $value => $label)
            <flux:button
                wire:click="$set('period', '{{ $value }}')"
                variant="{{ $this->period === $value ? 'filled' : 'outline' }}"
                size="sm"
            >{{ $label }}</flux:button>
        @endforeach
    </div>

    {{-- KPI --}}
    <div class="grid gap-3 sm:grid-cols-3">
        <flux:card class="p-4">
            <flux:text class="text-sm text-zinc-500">{{ __('Total orders') }}</flux:text>
            <flux:heading size="lg">{{ number_format($this->kpi['total_orders']) }}</flux:heading>
        </flux:card>
        <flux:card class="p-4">
            <flux:text class="text-sm text-zinc-500">{{ __('Total freight (TRY)') }}</flux:text>
            <flux:heading size="lg">{{ number_format($this->kpi['total_freight'], 0, ',', '.') }}</flux:heading>
        </flux:card>
        <flux:card class="p-4">
            <flux:text class="text-sm text-zinc-500">{{ __('Cities covered') }}</flux:text>
            <flux:heading size="lg">{{ $this->kpi['city_count'] }}</flux:heading>
        </flux:card>
    </div>

    {{-- City heat-map table --}}
    <flux:card class="overflow-hidden p-0">
        <div class="border-b border-zinc-200 px-4 py-3 dark:border-zinc-700">
            <flux:heading size="sm">{{ __('City breakdown') }}</flux:heading>
            <flux:text class="text-xs text-zinc-500">{{ __('Ordered by total freight. Bar width indicates relative share.') }}</flux:text>
        </div>

        @if ($this->cityStats->isEmpty())
            <div class="px-6 py-10 text-center text-sm text-zinc-400">
                {{ __('No geo data yet. Assign tax offices to customers to enable city analytics.') }}
            </div>
        @else
            @php
                $maxFreight = (float) $this->cityStats->max('total_freight') ?: 1;
            @endphp
            <div class="divide-y divide-zinc-100 dark:divide-zinc-800">
                @foreach ($this->cityStats as $i => $row)
                    @php
                        $pct     = $maxFreight > 0 ? round(($row->total_freight / $maxFreight) * 100) : 0;
                        $barColor = match (true) {
                            $pct >= 75 => 'bg-red-500',
                            $pct >= 50 => 'bg-orange-400',
                            $pct >= 25 => 'bg-yellow-400',
                            default    => 'bg-green-400',
                        };
                        $rank = $i + 1;
                    @endphp
                    <div class="flex items-center gap-4 px-4 py-3">
                        {{-- Rank --}}
                        <div class="w-6 shrink-0 text-right text-xs font-semibold text-zinc-400">
                            {{ $rank <= 3 ? ['🥇', '🥈', '🥉'][$rank - 1] : $rank }}
                        </div>

                        {{-- City name --}}
                        <div class="w-36 shrink-0 text-sm font-medium text-zinc-800 dark:text-zinc-200">
                            {{ $row->city }}
                        </div>

                        {{-- Bar --}}
                        <div class="flex-1">
                            <div class="h-3 w-full rounded-full bg-zinc-100 dark:bg-zinc-700">
                                <div
                                    class="h-3 rounded-full transition-all {{ $barColor }}"
                                    style="width: {{ $pct }}%"
                                ></div>
                            </div>
                        </div>

                        {{-- Stats --}}
                        <div class="w-20 shrink-0 text-right text-xs text-zinc-500">
                            {{ number_format($row->total_freight, 0, ',', '.') }} ₺
                        </div>
                        <div class="w-20 shrink-0 text-right text-xs text-zinc-500">
                            {{ __(':n orders', ['n' => $row->order_count]) }}
                        </div>
                        <div class="w-28 shrink-0 text-right text-xs text-zinc-400">
                            {{ __('Avg') }}: {{ number_format($row->avg_freight, 0, ',', '.') }} ₺
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </flux:card>

</div>
