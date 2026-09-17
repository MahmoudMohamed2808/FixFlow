<?php

namespace Database\Seeders;

use App\Models\Client;
use App\Models\Invoice;
use App\Models\WorkOrder;
use Illuminate\Database\Seeder;

class InvoiceSeeder extends Seeder
{
    public function run(): void
    {
        if (Client::count() === 0) {
            (new ClientSeeder)->run();
        }

        if (WorkOrder::count() === 0) {
            (new WorkOrderSeeder)->run();
        }

        $byCode = fn (string $code) => WorkOrder::where('code', $code)->first();

        // [رقم الفاتورة، كود أمر الشغل، إيميل العميل، الحالة، الضريبة، البنود، ملاحظات]
        // أرقام الفواتير تُترك للـ hook التلقائي (INV-00001...) عبر firstOrCreate على البنود المميزة.
        $invoices = [
            ['WO-1001', 'a.naggar@elnour-foods.test', 'paid', 350, [['أجور إصلاح الكمبروسور', 1, 2200], ['توريد فلتر زيت ×2', 2, 450], ['توريد فلتر هواء', 1, 320]], '-45 days', '-31 days', '-30 days', 'مدفوعة تحويل بنكي'],
            ['WO-1002', 'a.naggar@elnour-foods.test', 'paid', 280, [['صيانة وقائية شهرية شاملة', 1, 1800], ['توريد سير ناقل 4م', 4, 850]], '-30 days', '-16 days', '-15 days', null],
            ['WO-1003', 'm.abdelaziz@delta-contracting.test', 'sent', 420, [['إصلاح دائرة السولار للمولد', 1, 3000], ['توريد فلتر سولار ×2', 2, 260], ['فيوز حراري ×3', 3, 65]], '-20 days', '+10 days', null, 'بانتظار التحصيل'],
            ['WO-1004', 'karim@egy-pack.test', 'overdue', 310, [['تغيير رولمان بلي + تشحيم', 1, 1900], ['شحم حراري', 1, 340]], '-60 days', '-30 days', null, 'متأخرة — تم التواصل مع العميل'],
            ['WO-1005', 'sameh@elsafa-dairy.test', 'sent', 150, [['فحص الغلاية الشهري', 1, 1200]], '-9 days', '+5 days', null, null],
            ['WO-1015', 'm.abdelaziz@delta-contracting.test', 'paid', 120, [['تشغيل تجريبي + فلتر سولار', 1, 950]], '-26 days', '-12 days', '-11 days', null],
            ['WO-1016', 'tarek@elobour-eng.test', 'draft', 200, [['معايرة حساس حرارة', 1, 1400]], '-3 days', '+11 days', null, 'مسودة — بانتظار اعتماد المشرف'],
            ['WO-1018', 'sherif@elkhaleeg-hvac.test', 'sent', 180, [['تغيير كونتاكتور لوحة خط 1', 1, 1600]], '-5 days', '+9 days', null, null],
            [null, 'hany@elnile-pharma.test', 'draft', 500, [['عرض سعر: صيانة التكييف المركزي ربع سنوية', 1, 4500]], '-2 days', '+12 days', null, 'عرض سعر — لم يعتمد بعد'],
            [null, 'mona@elsharq-textile.test', 'cancelled', 0, [['معاينة السير الرئيسي', 1, 500]], '-25 days', '-11 days', null, 'أُلغيت بطلب العميل'],
        ];

        foreach ($invoices as [$woCode, $clientEmail, $status, $tax, $items, $issueAgo, $dueIn, $paidAgo, $notes]) {
            $wo = $woCode ? $byCode($woCode) : null;
            $client = Client::where('email', $clientEmail)->firstOrFail();

            // الثبات عبر التكرار: كل أمر شغل مفوتر مرة واحدة في بيانات التجربة،
            // والفاتورتان بدون أمر شغل مميزتان بالعميل (work_order_id = null).
            // ملاحظة: لا نطابق بـ issue_date لأن SQLite يخزن أعمدة date بصيغة
            // datetime ('Y-m-d 00:00:00') فتفشل مطابقة السلسلة 'Y-m-d' عند التكرار.
            $invoice = Invoice::firstOrCreate(
                [
                    'work_order_id' => $wo?->id,
                    'client_id' => $client->id,
                ],
                [
                    'issue_date' => now()->modify($issueAgo)->toDateString(),
                    'due_date' => now()->modify($dueIn)->toDateString(),
                    'status' => $status,
                    'tax' => $tax,
                    'paid_at' => $paidAgo ? now()->modify($paidAgo) : null,
                    'notes' => $notes,
                ]
            );

            foreach ($items as [$desc, $qty, $price]) {
                if (! $invoice->items()->where('description', $desc)->exists()) {
                    $invoice->items()->create([
                        'description' => $desc,
                        'quantity' => $qty,
                        'unit_price' => $price,
                    ]);
                }
            }

            // توحيد الحالة بعد البنود (recalculateTotals يحدّث المجاميع فقط).
            // التواريخ تُضبط عند الإنشاء فقط حتى لا تنزاح مع كل تكرار.
            $invoice->update([
                'status' => $status,
                'tax' => $tax,
            ]);
            $invoice->recalculateTotals();
        }
    }
}
