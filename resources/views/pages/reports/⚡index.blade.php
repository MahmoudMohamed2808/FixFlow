<?php

use App\Models\Asset;
use App\Models\DowntimeRecord;
use App\Models\SparePart;
use App\Models\WorkOrder;
use App\Services\FinancialReportService;
use Illuminate\Support\Carbon;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('التقارير')] class extends Component {
    public string $type = 'summary';

    public string $from = '';

    public string $to = '';

    public ?int $assetId = null;

    public function updatedType(): void
    {
        $this->reset(['assetId']);
    }

    public function exportUrl(): string
    {
        return route('reports.export', [
            'type' => $this->type,
            'from' => $this->from ?: null,
            'to' => $this->to ?: null,
            'asset_id' => $this->assetId,
        ]);
    }

    protected function baseOrders()
    {
        return WorkOrder::query()
            ->when($this->range, fn ($q) => $q->whereBetween('created_at', $this->range))
            ->when($this->assetId, fn ($q) => $q->where('asset_id', $this->assetId));
    }

    protected function completedOrders()
    {
        return $this->baseOrders()->where('status', 'completed');
    }

    #[Computed]
    public function range()
    {
        if ($this->from && $this->to) {
            return [now()->parse($this->from)->startOfDay(), now()->parse($this->to)->endOfDay()];
        }

        return null;
    }

    #[Computed]
    public function assets()
    {
        return Asset::query()->orderBy('name')->get();
    }

    #[Computed]
    public function summary()
    {
        $orders = $this->baseOrders()->get();

        $completed = $orders->where('status', 'completed');

        return [
            'total_orders' => $orders->count(),
            'open_orders' => $orders->whereIn('status', ['open', 'assigned', 'in_progress'])->count(),
            'completed_orders' => $completed->count(),
            'total_cost' => $orders->sum('cost') ?? 0,
        ];
    }

    #[Computed]
    public function monthlyCosts()
    {
        $rows = $this->completedOrders()
            ->selectRaw('strftime("%Y-%m", completed_at) as month, SUM(cost) as total')
            ->groupBy('month')
            ->orderBy('month')
            ->get();

        return $rows
            ->filter(fn ($row) => $row->month)
            ->map(fn ($row) => [
                'month' => $row->month,
                'label' => now()->createFromFormat('!Y-m', $row->month)->translatedFormat('M Y'),
                'total' => (float) ($row->total ?? 0),
            ])
            ->values();
    }

    #[Computed]
    public function costByAsset()
    {
        return $this->completedOrders()
            ->with('asset')
            ->get()
            ->groupBy('asset_id')
            ->map(function ($orders) {
                $orders = $orders->values();

                return [
                    'asset' => $orders->first()->asset?->name ?? __('—'),
                    'orders' => $orders->count(),
                    'cost' => $orders->sum('cost') ?? 0,
                ];
            })
            ->sortByDesc('cost')
            ->values();
    }

    #[Computed]
    public function costByDepartment()
    {
        return $this->completedOrders()
            ->with('asset.location.department')
            ->get()
            ->groupBy(fn (WorkOrder $order) => $order->asset?->location?->department?->id)
            ->map(function ($orders, $departmentId) {
                $orders = $orders->values();

                return [
                    'department' => $orders->first()->asset?->location?->department?->name ?? __('بدون قسم'),
                    'orders' => $orders->count(),
                    'cost' => $orders->sum('cost') ?? 0,
                ];
            })
            ->sortByDesc('cost')
            ->values();
    }

    #[Computed]
    public function downtimeByAsset()
    {
        $records = DowntimeRecord::query()
            ->with('asset')
            ->when($this->assetId, fn ($q) => $q->where('asset_id', $this->assetId))
            ->when($this->range, fn ($q) => $q->whereBetween('start_time', $this->range))
            ->get();

        return $records
            ->groupBy('asset_id')
            ->map(fn ($rows) => [
                'asset' => $rows->first()->asset?->name ?? __('—'),
                'records' => $rows->count(),
                'total_minutes' => $rows->sum('duration_minutes') ?? 0,
            ])
            ->sortByDesc('total_minutes')
            ->values();
    }

    #[Computed]
    public function lowStockParts()
    {
        return SparePart::query()->whereColumn('quantity_in_stock', '<=', 'minimum_quantity')->get();
    }

    /**
     * نطاق التقرير المالي كتواريخ قابلة للتغيير (mutable) كما يتطلب FinancialReportService.
     *
     * @return array{0: Carbon, 1: Carbon}
     */
    protected function financialRange(): array
    {
        if ($this->range) {
            return [
                Carbon::parse($this->range[0]->format('Y-m-d H:i:s'))->startOfDay(),
                Carbon::parse($this->range[1]->format('Y-m-d H:i:s'))->endOfDay(),
            ];
        }

        return [
            Carbon::parse(date('Y-01-01'))->startOfDay(),
            Carbon::parse(date('Y-m-d H:i:s'))->endOfDay(),
        ];
    }

    #[Computed]
    public function financialSummary(): array
    {
        [$from, $to] = $this->financialRange();

        return (new FinancialReportService())->summary($from, $to);
    }

    #[Computed]
    public function topExpensiveAssets()
    {
        [$from, $to] = $this->financialRange();

        return (new FinancialReportService())->expensesByAsset($from, $to, 5);
    }

    #[Computed]
    public function topPayingClients()
    {
        [$from, $to] = $this->financialRange();

        return (new FinancialReportService())->incomeByClient($from, $to, 5);
    }
}; ?>

<div class="flex h-full w-full flex-1 flex-col gap-6">
    <div class="flex items-center justify-between">
        <div>
            <flux:heading>{{ __('التقارير') }}</flux:heading>
            <flux:subheading>{{ __('تقارير الصيانة والتكاليف') }}</flux:subheading>
        </div>
    </div>

    <flux:card class="no-print">
        <div class="flex flex-col gap-3 md:flex-row md:items-end">
            <flux:field>
                <flux:label>{{ __('نوع التقرير') }}</flux:label>
                <flux:select wire:model.live="type" class="md:w-56">
                    <flux:select.option value="summary">{{ __('ملخص عام') }}</flux:select.option>
                    <flux:select.option value="asset">{{ __('التكلفة لكل أصل') }}</flux:select.option>
                    <flux:select.option value="monthly">{{ __('التكلفة الشهرية') }}</flux:select.option>
                    <flux:select.option value="department">{{ __('التكلفة لكل قسم') }}</flux:select.option>
                    <flux:select.option value="downtime">{{ __('وقت التعطل') }}</flux:select.option>
                    <flux:select.option value="inventory">{{ __('المخزون المنخفض') }}</flux:select.option>
                    <flux:select.option value="financial">{{ __('المالي (مصروف/مكتسب)') }}</flux:select.option>
                </flux:select>
            </flux:field>

            <flux:field>
                <flux:label>{{ __('من') }}</flux:label>
                <flux:input type="date" wire:model.live="from" />
            </flux:field>

            <flux:field>
                <flux:label>{{ __('إلى') }}</flux:label>
                <flux:input type="date" wire:model.live="to" />
            </flux:field>

            <flux:field>
                <flux:label>{{ __('الأصل') }}</flux:label>
                <flux:select wire:model.live="assetId" class="md:w-56">
                    <flux:select.option value="">{{ __('كل الأصول') }}</flux:select.option>
                    @foreach ($this->assets as $asset)
                        <flux:select.option value="{{ $asset->id }}">{{ $asset->name }}</flux:select.option>
                    @endforeach
                </flux:select>
            </flux:field>

            <a href="{{ $this->exportUrl() }}">
                <flux:button variant="primary" icon="arrow-down-tray">{{ __('تصدير CSV') }}</flux:button>
            </a>
        </div>
    </flux:card>

    @if ($type === 'summary')
        <div class="grid auto-rows-min gap-4 md:grid-cols-4">
            <flux:card>
                <flux:heading size="lg">{{ __('إجمالي أوامر الشغل') }}</flux:heading>
                <flux:badge size="md" color="primary" class="mt-2">{{ $this->summary['total_orders'] }}</flux:badge>
            </flux:card>
            <flux:card>
                <flux:heading size="lg">{{ __('أوامر مفتوحة') }}</flux:heading>
                <flux:badge size="md" color="warning" class="mt-2">{{ $this->summary['open_orders'] }}</flux:badge>
            </flux:card>
            <flux:card>
                <flux:heading size="lg">{{ __('أوامر مكتملة') }}</flux:heading>
                <flux:badge size="md" color="success" class="mt-2">{{ $this->summary['completed_orders'] }}</flux:badge>
            </flux:card>
            <flux:card>
                <flux:heading size="lg">{{ __('إجمالي التكلفة') }}</flux:heading>
                <flux:badge size="md" color="danger" class="mt-2">{{ number_format($this->summary['total_cost'], 2) }}</flux:badge>
            </flux:card>
        </div>
    @endif

    @if ($type === 'monthly')
        <flux:card class="overflow-hidden">
            <flux:heading size="lg">{{ __('التكلفة الشهرية') }}</flux:heading>
            <flux:separator class="mt-3" />
            <flux:table class="mt-3">
                <flux:table.columns>
                    <flux:table.column>{{ __('الشهر') }}</flux:table.column>
                    <flux:table.column align="end">{{ __('التكلفة') }}</flux:table.column>
                </flux:table.columns>
                <flux:table.rows>
                    @forelse ($this->monthlyCosts as $row)
                        <flux:table.row :key="$row['month']" wire:key="mc-{{ $row['month'] }}">
                            <flux:table.cell class="font-medium">{{ $row['label'] }}</flux:table.cell>
                            <flux:table.cell align="end">{{ number_format($row['total'], 2) }}</flux:table.cell>
                        </flux:table.row>
                    @empty
                        <flux:table.row>
                            <flux:table.cell colspan="2" class="!text-center">{{ __('لا توجد بيانات في النطاق المحدد.') }}</flux:table.cell>
                        </flux:table.row>
                    @endforelse
                </flux:table.rows>
            </flux:table>
        </flux:card>
    @endif

    @if ($type === 'asset')
        <flux:card class="overflow-hidden">
            <flux:heading size="lg">{{ __('التكلفة لكل أصل') }}</flux:heading>
            <flux:separator class="mt-3" />
            <flux:table class="mt-3">
                <flux:table.columns>
                    <flux:table.column>{{ __('الأصل') }}</flux:table.column>
                    <flux:table.column>{{ __('أوامر الشغل') }}</flux:table.column>
                    <flux:table.column align="end">{{ __('التكلفة') }}</flux:table.column>
                </flux:table.columns>
                <flux:table.rows>
                    @forelse ($this->costByAsset as $row)
                        <flux:table.row :key="'a-'.$row['asset']" wire:key="ca-{{ $row['asset'] }}">
                            <flux:table.cell class="font-medium">{{ $row['asset'] }}</flux:table.cell>
                            <flux:table.cell>{{ $row['orders'] }}</flux:table.cell>
                            <flux:table.cell align="end">{{ number_format($row['cost'], 2) }}</flux:table.cell>
                        </flux:table.row>
                    @empty
                        <flux:table.row>
                            <flux:table.cell colspan="3" class="!text-center">{{ __('لا توجد بيانات.') }}</flux:table.cell>
                        </flux:table.row>
                    @endforelse
                </flux:table.rows>
            </flux:table>
        </flux:card>
    @endif

    @if ($type === 'department')
        <flux:card class="overflow-hidden">
            <flux:heading size="lg">{{ __('التكلفة لكل قسم') }}</flux:heading>
            <flux:separator class="mt-3" />
            <flux:table class="mt-3">
                <flux:table.columns>
                    <flux:table.column>{{ __('القسم') }}</flux:table.column>
                    <flux:table.column>{{ __('أوامر الشغل') }}</flux:table.column>
                    <flux:table.column align="end">{{ __('التكلفة') }}</flux:table.column>
                </flux:table.columns>
                <flux:table.rows>
                    @forelse ($this->costByDepartment as $row)
                        <flux:table.row :key="'d-'.$row['department']" wire:key="cd-{{ $row['department'] }}">
                            <flux:table.cell class="font-medium">{{ $row['department'] }}</flux:table.cell>
                            <flux:table.cell>{{ $row['orders'] }}</flux:table.cell>
                            <flux:table.cell align="end">{{ number_format($row['cost'], 2) }}</flux:table.cell>
                        </flux:table.row>
                    @empty
                        <flux:table.row>
                            <flux:table.cell colspan="3" class="!text-center">{{ __('لا توجد بيانات.') }}</flux:table.cell>
                        </flux:table.row>
                    @endforelse
                </flux:table.rows>
            </flux:table>
        </flux:card>
    @endif

    @if ($type === 'downtime')
        <flux:card class="overflow-hidden">
            <flux:heading size="lg">{{ __('وقت التعطل لكل أصل') }}</flux:heading>
            <flux:separator class="mt-3" />
            <flux:table class="mt-3">
                <flux:table.columns>
                    <flux:table.column>{{ __('الأصل') }}</flux:table.column>
                    <flux:table.column>{{ __('عدد مرات التوقف') }}</flux:table.column>
                    <flux:table.column align="end">{{ __('إجمالي الدقائق') }}</flux:table.column>
                </flux:table.columns>
                <flux:table.rows>
                    @forelse ($this->downtimeByAsset as $row)
                        <flux:table.row :key="'dt-'.$row['asset']" wire:key="dd-{{ $row['asset'] }}">
                            <flux:table.cell class="font-medium">{{ $row['asset'] }}</flux:table.cell>
                            <flux:table.cell>{{ $row['records'] }}</flux:table.cell>
                            <flux:table.cell align="end">{{ number_format($row['total_minutes']) }}</flux:table.cell>
                        </flux:table.row>
                    @empty
                        <flux:table.row>
                            <flux:table.cell colspan="3" class="!text-center">{{ __('لا توجد سجلات توقف.') }}</flux:table.cell>
                        </flux:table.row>
                    @endforelse
                </flux:table.rows>
            </flux:table>
        </flux:card>
    @endif

    @if ($type === 'inventory')
        <flux:card class="overflow-hidden">
            <flux:heading size="lg">{{ __('المخزون المنخفض') }}</flux:heading>
            <flux:subheading>{{ __('قطع الغيار عند أو أقل من الحد الأدنى') }}</flux:subheading>
            <flux:separator class="mt-3" />
            <flux:table class="mt-3">
                <flux:table.columns>
                    <flux:table.column>{{ __('الكود') }}</flux:table.column>
                    <flux:table.column>{{ __('القطعة') }}</flux:table.column>
                    <flux:table.column>{{ __('الكمية') }}</flux:table.column>
                    <flux:table.column>{{ __('الحد الأدنى') }}</flux:table.column>
                </flux:table.columns>
                <flux:table.rows>
                    @forelse ($this->lowStockParts as $part)
                        <flux:table.row :key="$part->id" wire:key="low-{{ $part->id }}">
                            <flux:table.cell><span class="font-mono">{{ $part->code }}</span></flux:table.cell>
                            <flux:table.cell class="font-medium">{{ $part->name }}</flux:table.cell>
                            <flux:table.cell><flux:badge size="sm" color="danger">{{ $part->quantity_in_stock }}</flux:badge></flux:table.cell>
                            <flux:table.cell>{{ $part->minimum_quantity }}</flux:table.cell>
                        </flux:table.row>
                    @empty
                        <flux:table.row>
                            <flux:table.cell colspan="4" class="!text-center">{{ __('لا توجد قطع منخفضة المخزون.') }}</flux:table.cell>
                        </flux:table.row>
                    @endforelse
                </flux:table.rows>
            </flux:table>
        </flux:card>
    @endif

    @if ($type === 'financial')
        <div class="grid auto-rows-min gap-4 md:grid-cols-4">
            <flux:card>
                <flux:heading size="lg">{{ __('إجمالي المصروف') }}</flux:heading>
                <flux:badge size="md" color="danger" class="mt-2">{{ number_format($this->financialSummary['total_expenses'], 2) }}</flux:badge>
            </flux:card>
            <flux:card>
                <flux:heading size="lg">{{ __('إجمالي المكتسب') }}</flux:heading>
                <flux:badge size="md" color="success" class="mt-2">{{ number_format($this->financialSummary['total_income'], 2) }}</flux:badge>
            </flux:card>
            <flux:card>
                <flux:heading size="lg">{{ __('صافي الربح') }}</flux:heading>
                <flux:badge size="md" :color="$this->financialSummary['net_profit'] >= 0 ? 'success' : 'danger'" class="mt-2">{{ number_format($this->financialSummary['net_profit'], 2) }}</flux:badge>
            </flux:card>
            <flux:card>
                <flux:heading size="lg">{{ __('هامش الربح') }}</flux:heading>
                <flux:badge size="md" color="info" class="mt-2">{{ $this->financialSummary['profit_margin_percent'] }}%</flux:badge>
            </flux:card>
        </div>

        <div class="grid gap-4 lg:grid-cols-2">
            <flux:card class="overflow-hidden">
                <flux:heading size="lg">{{ __('أكثر الأصول تكلفة') }}</flux:heading>
                <flux:separator class="mt-3" />
                <flux:table class="mt-3">
                    <flux:table.columns>
                        <flux:table.column>{{ __('الأصل') }}</flux:table.column>
                        <flux:table.column align="end">{{ __('التكلفة') }}</flux:table.column>
                    </flux:table.columns>
                    <flux:table.rows>
                        @forelse ($this->topExpensiveAssets as $row)
                            <flux:table.row :key="'fa-'.$row->asset_id" wire:key="fa-{{ $row->asset_id }}">
                                <flux:table.cell class="font-medium">{{ $row->asset?->name ?? '—' }}</flux:table.cell>
                                <flux:table.cell align="end">{{ number_format((float) $row->total_cost, 2) }}</flux:table.cell>
                            </flux:table.row>
                        @empty
                            <flux:table.row>
                                <flux:table.cell colspan="2" class="!text-center">{{ __('لا توجد بيانات.') }}</flux:table.cell>
                            </flux:table.row>
                        @endforelse
                    </flux:table.rows>
                </flux:table>
            </flux:card>

            <flux:card class="overflow-hidden">
                <flux:heading size="lg">{{ __('أكثر العملاء دخلًا') }}</flux:heading>
                <flux:separator class="mt-3" />
                <flux:table class="mt-3">
                    <flux:table.columns>
                        <flux:table.column>{{ __('العميل') }}</flux:table.column>
                        <flux:table.column align="end">{{ __('الإيراد') }}</flux:table.column>
                    </flux:table.columns>
                    <flux:table.rows>
                        @forelse ($this->topPayingClients as $row)
                            <flux:table.row :key="'fc-'.$row->client_id" wire:key="fc-{{ $row->client_id }}">
                                <flux:table.cell class="font-medium">{{ $row->client?->company_name ?? $row->client?->name ?? '—' }}</flux:table.cell>
                                <flux:table.cell align="end">{{ number_format((float) $row->total_income, 2) }}</flux:table.cell>
                            </flux:table.row>
                        @empty
                            <flux:table.row>
                                <flux:table.cell colspan="2" class="!text-center">{{ __('لا توجد فواتير مدفوعة.') }}</flux:table.cell>
                            </flux:table.row>
                        @endforelse
                    </flux:table.rows>
                </flux:table>
            </flux:card>
        </div>
    @endif
</div>