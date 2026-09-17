<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $invoice->invoice_number }}</title>
    <style>
        * { box-sizing: border-box; }
        body { font-family: Tahoma, Arial, sans-serif; color: #18181b; margin: 0; padding: 32px; font-size: 14px; }
        .toolbar { margin-bottom: 24px; display: flex; gap: 8px; }
        .toolbar button { font-family: inherit; font-size: 14px; padding: 8px 20px; border-radius: 6px; border: 1px solid #d4d4d8; background: #18181b; color: #fff; cursor: pointer; }
        .sheet { max-width: 800px; margin: 0 auto; border: 1px solid #e4e4e7; border-radius: 8px; overflow: hidden; }
        .header { background: #18181b; color: #fff; padding: 24px 28px; display: flex; justify-content: space-between; align-items: center; }
        .header .app { font-size: 20px; font-weight: bold; }
        .header .num { font-size: 13px; opacity: 0.85; font-family: monospace; }
        .body { padding: 28px; }
        .meta { display: flex; justify-content: space-between; gap: 16px; margin-bottom: 20px; font-size: 13px; color: #52525b; }
        table.items { width: 100%; border-collapse: collapse; }
        table.items th { background: #f4f4f5; text-align: right; padding: 10px; border-bottom: 1px solid #e4e4e7; }
        table.items td { padding: 10px; border-bottom: 1px solid #f4f4f5; }
        table.items td.n, table.items th.n { text-align: center; }
        table.items td.m, table.items th.m { text-align: left; }
        .totals { width: 280px; margin-right: auto; margin-top: 16px; }
        .totals .row { display: flex; justify-content: space-between; padding: 4px 0; }
        .totals .grand { font-size: 16px; font-weight: bold; border-top: 2px solid #18181b; margin-top: 6px; padding-top: 8px; }
        .notes { margin-top: 20px; font-size: 13px; color: #52525b; }
        @media print {
            body { padding: 0; }
            .toolbar, .no-print { display: none !important; }
            .sheet { border: none; border-radius: 0; }
        }
    </style>
</head>
<body>
<div class="toolbar no-print">
    <button onclick="window.print()">{{ __('طباعة / حفظ PDF') }}</button>
    <button onclick="window.close()" style="background:#fff;color:#18181b;">{{ __('إغلاق') }}</button>
</div>

<div class="sheet">
    <div class="header">
        <div>
            <div class="app">{{ config('app.name') }}</div>
            <div style="font-size: 13px; opacity: 0.85;">{{ __('فاتورة') }}</div>
        </div>
        <div class="num">{{ $invoice->invoice_number }}</div>
    </div>

    <div class="body">
        <div class="meta">
            <div>
                <div><strong>{{ __('العميل') }}:</strong> {{ $invoice->client?->company_name ?? $invoice->client?->name }}</div>
                @if ($invoice->client?->phone)
                    <div>{{ __('الهاتف') }}: <span dir="ltr">{{ $invoice->client->phone }}</span></div>
                @endif
                @if ($invoice->workOrder)
                    <div>{{ __('أمر الشغل') }}: <span style="font-family: monospace;">{{ $invoice->workOrder->code }}</span></div>
                @endif
            </div>
            <div style="text-align: left;">
                <div>{{ __('تاريخ الإصدار') }}: {{ $invoice->issue_date?->translatedFormat('d M Y') }}</div>
                @if ($invoice->due_date)
                    <div>{{ __('تاريخ الاستحقاق') }}: {{ $invoice->due_date->translatedFormat('d M Y') }}</div>
                @endif
            </div>
        </div>

        <table class="items">
            <thead>
            <tr>
                <th>{{ __('الوصف') }}</th>
                <th class="n">{{ __('الكمية') }}</th>
                <th class="m">{{ __('سعر الوحدة') }}</th>
                <th class="m">{{ __('الإجمالي') }}</th>
            </tr>
            </thead>
            <tbody>
            @forelse ($invoice->items as $item)
                <tr>
                    <td>{{ $item->description }}</td>
                    <td class="n">{{ $item->quantity }}</td>
                    <td class="m">{{ number_format((float) $item->unit_price, 2) }}</td>
                    <td class="m">{{ number_format((float) $item->total, 2) }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="4" style="text-align: center; color: #71717a;">{{ __('لا توجد بنود.') }}</td>
                </tr>
            @endforelse
            </tbody>
        </table>

        <div class="totals">
            <div class="row"><span>{{ __('المجموع الفرعي') }}</span><span>{{ number_format((float) $invoice->subtotal, 2) }}</span></div>
            <div class="row"><span>{{ __('الضريبة') }}</span><span>{{ number_format((float) $invoice->tax, 2) }}</span></div>
            <div class="row grand"><span>{{ __('الإجمالي') }}</span><span>{{ number_format((float) $invoice->total_amount, 2) }}</span></div>
        </div>

        @if ($invoice->notes)
            <div class="notes"><strong>{{ __('ملاحظات') }}:</strong> {{ $invoice->notes }}</div>
        @endif
    </div>
</div>
</body>
</html>
