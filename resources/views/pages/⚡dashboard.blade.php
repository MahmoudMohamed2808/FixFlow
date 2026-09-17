<?php

use App\Models\Asset;
use App\Models\SparePart;
use App\Models\WorkOrder;
use App\Services\FinancialReportService;
use Illuminate\Support\Carbon;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('لوحة التحكم')] class extends Component {
    #[Computed]
    public function workOrderCounts(): array
    {
        return [
            'total' => WorkOrder::count(),
            'open' => WorkOrder::where('status', 'open')->count(),
            'in_progress' => WorkOrder::where('status', 'in_progress')->count(),
            'completed' => WorkOrder::where('status', 'completed')->count(),
        ];
    }

    #[Computed]
    public function assetCounts(): array
    {
        return [
            'total' => Asset::count(),
            'operational' => Asset::where('status', 'operational')->count(),
            'under_maintenance' => Asset::where('status', 'under_maintenance')->count(),
            'broken' => Asset::where('status', 'broken')->count(),
        ];
    }

    #[Computed]
    public function lowStockParts()
    {
        return SparePart::all()->filter->isBelowMinimum();
    }

    #[Computed]
    public function overdueSchedules()
    {
        return \App\Models\PreventiveSchedule::query()
            ->where('is_active', true)
            ->whereDate('next_due_date', '<', now())
            ->with('asset')
            ->orderBy('next_due_date')
            ->take(5)
            ->get();
    }

    #[Computed]
    public function mostFaultyAssets()
    {
        return Asset::query()
            ->withCount(['workOrders as fault_count' => fn ($q) => $q->where('type', 'corrective')])
            ->orderByDesc('fault_count')
            ->take(5)
            ->get();
    }

    #[Computed]
    public function recentWorkOrders()
    {
        return WorkOrder::query()
            ->with('asset')
            ->latest()
            ->take(6)
            ->get();
    }

    #[Computed]
    public function financialOverview(): array
    {
        return (new FinancialReportService())->summary(
            Carbon::parse(date('Y-01-01'))->startOfDay(),
            Carbon::parse(date('Y-m-d H:i:s'))->endOfDay(),
        );
    }
}; ?>

<div class="flex h-full w-full flex-1 flex-col gap-6">
    <div>
        <flux:heading>{{ __('لوحة التحكم') }}</flux:heading>
        <flux:subheading>{{ __('نظرة عامة على أنشطة الصيانة') }}</flux:subheading>
    </div>

    <div class="grid auto-rows-min gap-4 md:grid-cols-3">
        <flux:card>
            <div class="flex items-center justify-between">
                <flux:heading size="lg">{{ __('أوامر الشغل المفتوحة') }}</flux:heading>
                <flux:badge size="sm" color="warning">{{ $this->workOrderCounts['open'] }}</flux:badge>
            </div>
            <flux:text>{{ __('إجمالي أوامر الشغل') }}: {{ $this->workOrderCounts['total'] }}</flux:text>
        </flux:card>

        <flux:card>
            <div class="flex items-center justify-between">
                <flux:heading size="lg">{{ __('أصول قيد الصيانة') }}</flux:heading>
                <flux:badge size="sm" color="info">{{ $this->assetCounts['under_maintenance'] }}</flux:badge>
            </div>
            <flux:text>{{ __('إجمالي الأصول') }}: {{ $this->assetCounts['total'] }}</flux:text>
        </flux:card>

        <flux:card>
            <div class="flex items-center justify-between">
                <flux:heading size="lg">{{ __('قطع غيار منخفضة') }}</flux:heading>
                <flux:badge size="sm" color="danger">{{ $this->lowStockParts->count() }}</flux:badge>
            </div>
            <flux:text>{{ __('تحتاج إلى طلب') }}</flux:text>
        </flux:card>
    </div>

    @can('invoices.view')
        <flux:card>
            <div class="flex items-center justify-between">
                <div>
                    <flux:heading size="lg">{{ __('الملخص المالي') }}</flux:heading>
                    <flux:subheading>{{ __('السنة الحالية: المصروف مقابل المكتسب') }}</flux:subheading>
                </div>
                <a href="{{ route('reports.index') }}" wire:navigate>
                    <flux:button variant="ghost" size="sm" icon="chart-bar">{{ __('التقرير الكامل') }}</flux:button>
                </a>
            </div>
            <flux:separator class="mt-3" />
            <div class="mt-4 grid grid-cols-2 gap-4 md:grid-cols-4">
                <div>
                    <flux:text size="xs">{{ __('إجمالي المصروف') }}</flux:text>
                    <flux:badge size="md" color="danger" class="mt-1">{{ number_format($this->financialOverview['total_expenses'], 2) }}</flux:badge>
                </div>
                <div>
                    <flux:text size="xs">{{ __('إجمالي المكتسب') }}</flux:text>
                    <flux:badge size="md" color="success" class="mt-1">{{ number_format($this->financialOverview['total_income'], 2) }}</flux:badge>
                </div>
                <div>
                    <flux:text size="xs">{{ __('صافي الربح') }}</flux:text>
                    <flux:badge size="md" :color="$this->financialOverview['net_profit'] >= 0 ? 'success' : 'danger'" class="mt-1">{{ number_format($this->financialOverview['net_profit'], 2) }}</flux:badge>
                </div>
                <div>
                    <flux:text size="xs">{{ __('هامش الربح') }}</flux:text>
                    <flux:badge size="md" color="info" class="mt-1">{{ $this->financialOverview['profit_margin_percent'] }}%</flux:badge>
                </div>
            </div>
        </flux:card>
    @endcan

    <div class="grid gap-4 md:grid-cols-2">
        <flux:card>
            <flux:heading size="lg">{{ __('أوامر الشغل حسب الحالة') }}</flux:heading>
            <flux:separator class="mt-3" />
            <div class="mt-4 space-y-3">
                <div class="flex items-center justify-between">
                    <flux:text>{{ __('مفتوحة') }}</flux:text>
                    <flux:badge size="sm" color="warning">{{ $this->workOrderCounts['open'] }}</flux:badge>
                </div>
                <div class="flex items-center justify-between">
                    <flux:text>{{ __('قيد التنفيذ') }}</flux:text>
                    <flux:badge size="sm" color="info">{{ $this->workOrderCounts['in_progress'] }}</flux:badge>
                </div>
                <div class="flex items-center justify-between">
                    <flux:text>{{ __('مكتملة') }}</flux:text>
                    <flux:badge size="sm" color="success">{{ $this->workOrderCounts['completed'] }}</flux:badge>
                </div>
            </div>
        </flux:card>

        <flux:card>
            <flux:heading size="lg">{{ __('حالة الأصول') }}</flux:heading>
            <flux:separator class="mt-3" />
            <div class="mt-4 space-y-3">
                <div class="flex items-center justify-between">
                    <flux:text>{{ __('تشغيلي') }}</flux:text>
                    <flux:badge size="sm" color="success">{{ $this->assetCounts['operational'] }}</flux:badge>
                </div>
                <div class="flex items-center justify-between">
                    <flux:text>{{ __('قيد الصيانة') }}</flux:text>
                    <flux:badge size="sm" color="info">{{ $this->assetCounts['under_maintenance'] }}</flux:badge>
                </div>
                <div class="flex items-center justify-between">
                    <flux:text>{{ __('معطوب') }}</flux:text>
                    <flux:badge size="sm" color="danger">{{ $this->assetCounts['broken'] }}</flux:badge>
                </div>
            </div>
        </flux:card>
    </div>

    <div class="grid auto-rows-min gap-4 md:grid-cols-2">
        <flux:card>
            <flux:heading size="lg">{{ __('صيانة وقائية متأخرة') }}</flux:heading>
            <flux:subheading>{{ __('جداول تجاوز موعدها ومازالت نشطة') }}</flux:subheading>
            <flux:separator class="mt-3" />
            <div class="mt-4 space-y-2">
                @forelse ($this->overdueSchedules as $schedule)
                    <div class="flex items-center justify-between gap-3">
                        <div class="flex min-w-0 items-center gap-3">
                            <flux:badge size="sm" color="danger">{{ $schedule->next_due_date->translatedFormat('d M Y') }}</flux:badge>
                            <flux:text class="truncate">{{ $schedule->title }}</flux:text>
                        </div>
                        <flux:text class="truncate text-end">{{ $schedule->asset?->name }}</flux:text>
                    </div>
                @empty
                    <flux:text>{{ __('لا توجد صيانات وقائية متأخرة.') }}</flux:text>
                @endforelse
            </div>
        </flux:card>

        <flux:card>
            <flux:heading size="lg">{{ __('الأصول الأكثر أعطالًا') }}</flux:heading>
            <flux:subheading>{{ __('حسب عدد الأوامر الإصلاحية') }}</flux:subheading>
            <flux:separator class="mt-3" />
            <div class="mt-4 space-y-2">
                @forelse ($this->mostFaultyAssets as $asset)
                    <div class="flex items-center justify-between gap-3">
                        <flux:text class="truncate">{{ $asset->name }}</flux:text>
                        <flux:badge size="sm" color="danger">{{ $asset->fault_count }}</flux:badge>
                    </div>
                @empty
                    <flux:text>{{ __('لا توجد أعطال مسجلة.') }}</flux:text>
                @endforelse
            </div>
        </flux:card>
    </div>

    <flux:card class="overflow-hidden">
        <flux:heading size="lg">{{ __('آخر أوامر الشغل') }}</flux:heading>
        <flux:separator class="mt-3" />
        <flux:table class="mt-3">
            <flux:table.columns>
                <flux:table.column>{{ __('الكود') }}</flux:table.column>
                <flux:table.column>{{ __('الأصل') }}</flux:table.column>
                <flux:table.column>{{ __('النوع') }}</flux:table.column>
                <flux:table.column>{{ __('الأولوية') }}</flux:table.column>
                <flux:table.column>{{ __('الحالة') }}</flux:table.column>
            </flux:table.columns>
            <flux:table.rows>
                @forelse ($this->recentWorkOrders as $order)
                    <flux:table.row :key="$order->id" wire:key="recent-{{ $order->id }}">
                        <flux:table.cell><span class="font-mono">{{ $order->code }}</span></flux:table.cell>
                        <flux:table.cell>{{ $order->asset?->name }}</flux:table.cell>
                        <flux:table.cell>
                            <flux:badge size="sm" :color="$order->type === 'preventive' ? 'info' : 'warning'">
                                {{ $order->type === 'preventive' ? __('وقائية') : __('إصلاحية') }}
                            </flux:badge>
                        </flux:table.cell>
                        <flux:table.cell>{{ __($order->priority) }}</flux:table.cell>
                        <flux:table.cell>
                            <flux:badge size="sm" :color="$order->status === 'completed' ? 'success' : ($order->status === 'cancelled' ? 'secondary' : 'info')">
                                {{ $order->status === 'open' ? __('مفتوحة') : ($order->status === 'assigned' ? __('تم التعيين') : ($order->status === 'in_progress' ? __('قيد التنفيذ') : ($order->status === 'completed' ? __('مكتملة') : __('ملغاة')))) }}
                            </flux:badge>
                        </flux:table.cell>
                    </flux:table.row>
                @empty
                    <flux:table.row>
                        <flux:table.cell colspan="5" class="!text-center">{{ __('لا توجد أوامر شغل بعد.') }}</flux:table.cell>
                    </flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>
    </flux:card>
</div>
