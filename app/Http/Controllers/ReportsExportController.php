<?php

namespace App\Http\Controllers;

use App\Models\DowntimeRecord;
use App\Models\SparePart;
use App\Models\WorkOrder;
use App\Services\FinancialReportService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportsExportController extends Controller
{
    /**
     * Stream a CSV export of the selected report.
     */
    public function __invoke(Request $request): StreamedResponse
    {
        $type = $request->string('type', 'asset')->toString();
        $from = $request->input('from');
        $to = $request->input('to');
        $assetId = $request->integer('asset_id');

        $range = $from && $to
            ? [now()->parse($from)->startOfDay(), now()->parse($to)->endOfDay()]
            : null;

        $rows = match ($type) {
            'summary' => $this->summary($range, $assetId),
            'department' => $this->costByDepartment($range, $assetId),
            'monthly' => $this->monthlyCosts($range, $assetId),
            'downtime' => $this->downtimeByAsset($range, $assetId),
            'inventory' => $this->lowStock(),
            'financial' => $this->financialSummary($range),
            default => $this->costByAsset($range, $assetId),
        };

        $filename = 'fixflow-'.$type.'-'.now()->format('Y-m-d-His').'.csv';

        return response()->streamDownload(function () use ($rows) {
            $out = fopen('php://output', 'w');
            foreach ($rows as $row) {
                fputcsv($out, array_map(fn ($v) => is_string($v) ? "\u{FEFF}".$v : $v, $row));
            }
            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /**
     * @return array<int, array<int, int|string>>
     */
    protected function summary(?array $range, int $assetId): array
    {
        // يطابق منطق ملخص صفحة التقارير (النطاق على created_at).
        $orders = WorkOrder::query()
            ->when($range, fn ($q) => $q->whereBetween('created_at', $range))
            ->when($assetId, fn ($q) => $q->where('asset_id', $assetId))
            ->get();

        return [
            [__('البيان'), __('القيمة')],
            [__('إجمالي أوامر الشغل'), $orders->count()],
            [__('أوامر مفتوحة'), $orders->whereIn('status', ['open', 'assigned', 'in_progress'])->count()],
            [__('أوامر مكتملة'), $orders->where('status', 'completed')->count()],
            [__('إجمالي التكلفة'), $orders->sum('cost') ?? 0],
        ];
    }

    /**
     * @return array<int, array<int, int|string>>
     */
    protected function costByAsset(?array $range, int $assetId): array
    {
        $rows = $this->completedOrders($range, $assetId)
            ->with('asset')
            ->get()
            ->groupBy('asset_id')
            ->map(fn ($orders) => [
                $orders->first()->asset?->name ?? __('—'),
                $orders->count(),
                $orders->sum('cost') ?? 0,
            ]);

        return [[__('الأصل'), __('أوامر الشغل'), __('التكلفة')], ...$rows->values()->all()];
    }

    /**
     * @return array<int, array<int, int|string>>
     */
    protected function costByDepartment(?array $range, int $assetId): array
    {
        $rows = $this->completedOrders($range, $assetId)
            ->with('asset.location.department')
            ->get()
            ->groupBy(fn (WorkOrder $order) => $order->asset?->location?->department?->id)
            ->map(fn ($orders) => [
                $orders->first()->asset?->location?->department?->name ?? __('بدون قسم'),
                $orders->count(),
                $orders->sum('cost') ?? 0,
            ]);

        return [[__('القسم'), __('أوامر الشغل'), __('التكلفة')], ...$rows->values()->all()];
    }

    /**
     * @return array<int, array<int, int|string>>
     */
    protected function monthlyCosts(?array $range, int $assetId): array
    {
        $rows = $this->completedOrders($range, $assetId)
            ->selectRaw('strftime("%Y-%m", completed_at) as month, SUM(cost) as total')
            ->groupBy('month')
            ->orderBy('month')
            ->get()
            ->filter(fn ($row) => $row->month)
            ->map(fn ($row) => [now()->createFromFormat('!Y-m', $row->month)->translatedFormat('M Y'), (float) ($row->total ?? 0)]);

        return [[__('الشهر'), __('التكلفة')], ...$rows->values()->all()];
    }

    /**
     * @return array<int, array<int, int|string>>
     */
    protected function downtimeByAsset(?array $range, int $assetId): array
    {
        $rows = DowntimeRecord::query()
            ->with('asset')
            ->when($assetId, fn ($q) => $q->where('asset_id', $assetId))
            ->when($range, fn ($q) => $q->whereBetween('start_time', $range))
            ->get()
            ->groupBy('asset_id')
            ->map(fn ($rows) => [
                $rows->first()->asset?->name ?? __('—'),
                $rows->count(),
                $rows->sum('duration_minutes') ?? 0,
            ]);

        return [[__('الأصل'), __('عدد مرات التوقف'), __('إجمالي الدقائق')], ...$rows->values()->all()];
    }

    /**
     * @return array<int, array<int, int|string>>
     */
    protected function lowStock(): array
    {
        $rows = SparePart::query()
            ->whereColumn('quantity_in_stock', '<=', 'minimum_quantity')
            ->get()
            ->map(fn ($part) => [$part->code, $part->name, $part->quantity_in_stock, $part->minimum_quantity])
            ->values();

        return [[__('الكود'), __('القطعة'), __('الكمية'), __('الحد الأدنى')], ...$rows->all()];
    }

    /**
     * @return array<int, array<int, int|string>>
     */
    protected function financialSummary(?array $range): array
    {
        // FinancialReportService يتطلب تواريخ mutable؛ النطاق القادم من الطلب قد يكون immutable.
        $from = $range
            ? Carbon::parse($range[0]->format('Y-m-d H:i:s'))->startOfDay()
            : Carbon::parse(date('Y-01-01'))->startOfDay();
        $to = $range
            ? Carbon::parse($range[1]->format('Y-m-d H:i:s'))->endOfDay()
            : Carbon::parse(date('Y-m-d H:i:s'))->endOfDay();

        $service = new FinancialReportService;
        $summary = $service->summary($from, $to);

        $rows = [
            [__('البيان'), __('القيمة')],
            [__('إجمالي المصروف'), $summary['total_expenses']],
            [__('إجمالي المكتسب'), $summary['total_income']],
            [__('صافي الربح'), $summary['net_profit']],
            [$summary['from'].' → '.$summary['to'], __('هامش الربح').': '.$summary['profit_margin_percent'].'%'],
            [],
            [__('أكثر الأصول تكلفة')],
            [__('الأصل'), __('التكلفة')],
        ];

        foreach ($service->expensesByAsset($from, $to) as $row) {
            $rows[] = [$row->asset?->name ?? __('—'), (float) $row->total_cost];
        }

        $rows[] = [];
        $rows[] = [__('أكثر العملاء دخلًا')];
        $rows[] = [__('العميل'), __('الإيراد')];

        foreach ($service->incomeByClient($from, $to) as $row) {
            $rows[] = [$row->client?->company_name ?? $row->client?->name ?? __('—'), (float) $row->total_income];
        }

        return $rows;
    }

    protected static function completedOrders(?array $range, int $assetId): Builder
    {
        return WorkOrder::query()
            ->where('status', 'completed')
            ->when($range, fn ($q) => $q->whereBetween('completed_at', $range))
            ->when($assetId, fn ($q) => $q->where('asset_id', $assetId));
    }
}
