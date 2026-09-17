# نظام إدارة الصيانة (CMMS) — الحزمة الكاملة

هذا الملف بيجمع كل حاجة اتبنت في المشروع لحد دلوقتي في مكان واحد.

## المحتويات
```
database/migrations/   19 ملف — كل جداول قاعدة البيانات
app/Models/             18 موديل — كل العلاقات والمنطق التلقائي
app/Services/           FinancialReportService.php — تقرير المصروف والمكتسب
```

## التقنيات
Laravel + Livewire + Tailwind CSS + MySQL + Spatie Permission

---

## 1. التركيب (خطوة بخطوة)

```bash
# مشروع Laravel جديد
composer create-project laravel/laravel cmms
cd cmms

# المصادقة
composer require laravel/breeze --dev
php artisan breeze:install livewire

# الأدوار والصلاحيات
composer require spatie/laravel-permission
php artisan vendor:publish --provider="Spatie\Permission\PermissionServiceProvider"
```

بعد كده:
1. انسخ كل ملفات `database/migrations/` هنا إلى `database/migrations/` في مشروعك.
2. انسخ كل ملفات `app/Models/` هنا إلى `app/Models/` (هتستبدل `User.php` الافتراضي من Breeze).
3. انسخ `app/Services/FinancialReportService.php` إلى `app/Services/` في مشروعك (اعمل المجلد لو مش موجود).
4. اضبط `.env` بإعدادات قاعدة البيانات.
5. شغّل:
   ```bash
   php artisan migrate
   ```

---

## 2. خريطة الجداول (19 جدول)

| # | الجدول | الوظيفة |
|---|---|---|
| 1 | departments | الأقسام |
| 2 | locations | المواقع (هرمية) |
| 3 | users (+حقول) | المستخدمين + department_id, phone, is_active, hourly_rate |
| 4 | asset_categories | تصنيفات المعدات — **خاصة بكل قسم على حدة** |
| 5 | suppliers | موردين قطع الغيار |
| 6 | clients | **عملاء الشركة (جديد)** |
| 7 | assets | الأصول/المعدات |
| 8 | preventive_schedules | جداول الصيانة الدورية |
| 9 | work_orders | أوامر الشغل — مربوطة اختياريًا بعميل |
| 10 | spare_parts | قطع الغيار والمخزون |
| 11 | work_order_parts | قطع الغيار المستخدمة في كل أمر شغل |
| 12 | work_order_labor | ساعات عمل الفنيين وتكلفتها |
| 13 | downtime_records | مدة تعطل كل أصل |
| 14 | work_order_attachments | مرفقات أمر الشغل |
| 15 | work_order_comments | تعليقات على أمر الشغل |
| 16 | activity_logs | سجل العمليات |
| 17 | budgets | الميزانيات المخصصة للصيانة |
| 18 | invoices | **فواتير العملاء (جديد)** |
| 19 | invoice_items | **بنود كل فاتورة (جديد)** |

---

## 3. الجزء الجديد: العملاء والفواتير والتقرير المالي

### الفكرة
- **Client**: بيانات العميل (اسم، شركة، تواصل).
- **WorkOrder.client_id**: أمر الشغل ممكن يكون داخلي (client_id = فاضي) أو شغل مدفوع لعميل خارجي.
- **Invoice**: فاتورة للعميل، مربوطة اختياريًا بأمر شغل معين، فيها بنود (`InvoiceItem`) بيتحسب منها الإجمالي تلقائي.
- الفاتورة بتاخد رقم تلقائي (`INV-00001`) لو محدّدتش رقم بنفسك.

### الفرق بين "المصروف" و"المكتسب"
- **المصروف (Expense)** = `work_orders.cost` (تكلفة قطع الغيار + أجور الفنيين — بتتحسب تلقائي زي ما شرحنا قبل كده).
- **المكتسب (Income)** = مجموع `invoices.total_amount` للفواتير اللي حالتها `paid` بس.
- **صافي الربح** = المكتسب − المصروف.

### استخدام عملي (تقرير المصروف والمكتسب)
```php
use App\Services\FinancialReportService;
use Illuminate\Support\Carbon;

$report = new FinancialReportService();

$summary = $report->summary(
    from: Carbon::parse('2026-01-01'),
    to: Carbon::parse('2026-12-31'),
);

// $summary['total_expenses']       -> إجمالي المصروف
// $summary['total_income']         -> إجمالي المكتسب
// $summary['net_profit']           -> صافي الربح
// $summary['profit_margin_percent']-> نسبة هامش الربح %

// تفاصيل إضافية
$report->expensesByAsset($from, $to);   // أكتر الأصول تكلفة
$report->incomeByClient($from, $to);    // أكتر العملاء دخلًا
```

### مثال إنشاء فاتورة
```php
$invoice = Invoice::create([
    'client_id' => $client->id,
    'work_order_id' => $workOrder->id,
    'issue_date' => now(),
    'due_date' => now()->addDays(14),
    'status' => 'sent',
    'tax' => 0,
]);

$invoice->items()->create([
    'description' => 'صيانة وحدة تكييف رقم AC-12',
    'quantity' => 1,
    'unit_price' => 1500,
]);
// subtotal و total_amount بيتحسبوا تلقائي بمجرد ما تضيف بند
```

---

## 4. ملخص كل القرارات المعمارية المتراكمة

1. **الأدوار**: Spatie Permission بدل جدول roles مخصص (Admin, Supervisor, Technician, Viewer).
2. **التصنيفات**: كل قسم له تصنيفاته الخاصة بيه بس (مش قائمة مشتركة).
3. **التكلفة الداخلية**: بتتحسب تلقائي من قطع الغيار + ساعات العمل، وتتخزن في `work_orders.cost`.
4. **الميزانيات**: مقارنة تلقائية بين المصروف الفعلي والمبلغ المخصص، لكل قسم أو للشركة كلها.
5. **العملاء والفواتير**: أمر الشغل ممكن يكون داخلي أو مدفوع لعميل، والفاتورة منفصلة عن التكلفة الداخلية (يعني تقدر تحط سعر بيع مختلف عن التكلفة الحقيقية = هامش ربح).

---

## 5. نقطة تحتاج انتباه عند التركيب
لو دي أول مرة بتعمل فيها migrate على قاعدة بيانات فاضية، مفيش مشكلة. لو عندك بيانات قديمة من نسخة سابقة للمشروع، لازم تعمل `php artisan migrate:fresh` (هتمسح البيانات القديمة) لأن بعض الأعمدة الجديدة إجبارية.

## 6. الخطوة الجاية المقترحة
1. **Seeders**: أقسام، مواقع، تصنيفات، أدوار Spatie، مستخدم Admin تجريبي، عميل تجريبي.
2. **واجهات Livewire**: شاشة العملاء، شاشة الفواتير، وداشبورد يعرض ملخص `FinancialReportService` بشكل مرئي (كروت أو progress bars).

قولي نبدأ في أي واحدة منهم.
