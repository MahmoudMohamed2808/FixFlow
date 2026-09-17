<?php

namespace App\Services;

use App\Models\Invoice;
use App\Models\WorkOrder;
use Illuminate\Support\Carbon;

class FinancialReportService
{
    /**
     * ملخص مالي بين تاريخين: كام صرفت (تكلفة صيانة داخلية) وكام اكتسبت (فواتير العملاء المدفوعة).
     *
     * @param  int|null  $departmentId  حدّده لو عايز التقرير لقسم معين بس، اتركه فاضي للنظام كله
     */
    public function summary(Carbon $from, Carbon $to, ?int $departmentId = null): array
    {
        $expenses = WorkOrder::query()
            ->whereBetween('scheduled_date', [$from, $to])
            ->when($departmentId, function ($query) use ($departmentId) {
                $query->whereHas('asset.location', fn ($q) => $q->where('department_id', $departmentId));
            })
            ->sum('cost');

        $income = Invoice::query()
            ->paid()
            ->whereBetween('paid_at', [$from, $to])
            ->when($departmentId, function ($query) use ($departmentId) {
                $query->whereHas('workOrder.asset.location', fn ($q) => $q->where('department_id', $departmentId));
            })
            ->sum('total_amount');

        $expenses = (float) $expenses;
        $income = (float) $income;

        return [
            'from' => $from->toDateString(),
            'to' => $to->toDateString(),
            'total_expenses' => $expenses,
            'total_income' => $income,
            'net_profit' => $income - $expenses,
            'profit_margin_percent' => $income > 0
                ? round((($income - $expenses) / $income) * 100, 1)
                : 0,
        ];
    }

    /**
     * تفصيل المصروفات حسب الأصل (أكتر الأصول تكلفة).
     */
    public function expensesByAsset(Carbon $from, Carbon $to, int $limit = 10)
    {
        return WorkOrder::query()
            ->whereBetween('scheduled_date', [$from, $to])
            ->selectRaw('asset_id, SUM(cost) as total_cost')
            ->groupBy('asset_id')
            ->orderByDesc('total_cost')
            ->with('asset:id,name,serial_number')
            ->limit($limit)
            ->get();
    }

    /**
     * تفصيل الإيرادات حسب العميل (أكتر العملاء دخلًا).
     */
    public function incomeByClient(Carbon $from, Carbon $to, int $limit = 10)
    {
        return Invoice::query()
            ->paid()
            ->whereBetween('paid_at', [$from, $to])
            ->selectRaw('client_id, SUM(total_amount) as total_income')
            ->groupBy('client_id')
            ->orderByDesc('total_income')
            ->with('client:id,name,company_name')
            ->limit($limit)
            ->get();
    }
}
