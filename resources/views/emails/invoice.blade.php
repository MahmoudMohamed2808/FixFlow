<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <title>{{ $invoice->invoice_number }}</title>
</head>
<body style="font-family: Tahoma, Arial, sans-serif; background: #f4f4f5; margin: 0; padding: 24px;">
<div style="max-width: 640px; margin: 0 auto; background: #ffffff; border-radius: 8px; overflow: hidden;">
    <div style="background: #18181b; color: #ffffff; padding: 20px 24px;">
        <div style="font-size: 20px; font-weight: bold;">{{ config('app.name') }}</div>
        <div style="font-size: 13px; opacity: 0.8;">{{ __('فاتورة') }} {{ $invoice->invoice_number }}</div>
    </div>
    <div style="padding: 24px;">
        <p style="margin: 0 0 8px;">{{ __('السيد/ة :name،', ['name' => $invoice->client?->name]) }}</p>
        <p style="margin: 0 0 16px; color: #52525b;">{{ __('نرفق لكم تفاصيل الفاتورة الصادرة بتاريخ :date.', ['date' => $invoice->issue_date?->translatedFormat('d M Y')]) }}</p>

        <table style="width: 100%; border-collapse: collapse; font-size: 14px;">
            <thead>
            <tr style="background: #f4f4f5;">
                <th style="text-align: right; padding: 10px; border-bottom: 1px solid #e4e4e7;">{{ __('الوصف') }}</th>
                <th style="text-align: center; padding: 10px; border-bottom: 1px solid #e4e4e7;">{{ __('الكمية') }}</th>
                <th style="text-align: left; padding: 10px; border-bottom: 1px solid #e4e4e7;">{{ __('الإجمالي') }}</th>
            </tr>
            </thead>
            <tbody>
            @forelse ($invoice->items as $item)
                <tr>
                    <td style="padding: 10px; border-bottom: 1px solid #f4f4f5;">{{ $item->description }}</td>
                    <td style="text-align: center; padding: 10px; border-bottom: 1px solid #f4f4f5;">{{ $item->quantity }}</td>
                    <td style="text-align: left; padding: 10px; border-bottom: 1px solid #f4f4f5;">{{ number_format((float) $item->total, 2) }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="3" style="padding: 10px; text-align: center; color: #71717a;">{{ __('لا توجد بنود.') }}</td>
                </tr>
            @endforelse
            </tbody>
        </table>

        <table style="width: 100%; margin-top: 16px; font-size: 14px;">
            <tr>
                <td style="padding: 4px 0; color: #52525b;">{{ __('المجموع الفرعي') }}</td>
                <td style="text-align: left; padding: 4px 0;">{{ number_format((float) $invoice->subtotal, 2) }}</td>
            </tr>
            <tr>
                <td style="padding: 4px 0; color: #52525b;">{{ __('الضريبة') }}</td>
                <td style="text-align: left; padding: 4px 0;">{{ number_format((float) $invoice->tax, 2) }}</td>
            </tr>
            <tr>
                <td style="padding: 8px 0; font-size: 16px; font-weight: bold;">{{ __('الإجمالي المستحق') }}</td>
                <td style="text-align: left; padding: 8px 0; font-size: 16px; font-weight: bold;">{{ number_format((float) $invoice->total_amount, 2) }}</td>
            </tr>
        </table>

        @if ($invoice->due_date)
            <p style="margin: 16px 0 0; color: #52525b; font-size: 13px;">{{ __('تاريخ الاستحقاق') }}: {{ $invoice->due_date->translatedFormat('d M Y') }}</p>
        @endif

        <p style="margin: 16px 0 0; font-size: 13px; color: #71717a;">{{ __('شكرًا لتعاملكم معنا.') }}</p>
    </div>
</div>
</body>
</html>
