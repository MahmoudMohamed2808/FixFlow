# Models - نظام إدارة الصيانة (CMMS)

## طريقة الاستخدام
انسخ كل الملفات دي إلى `app/Models/` في مشروعك (ملف `User.php` هيستبدل النسخة الافتراضية من Breeze).

## قبل التشغيل
```bash
composer require spatie/laravel-permission
php artisan vendor:publish --provider="Spatie\Permission\PermissionServiceProvider"
php artisan migrate
```

## أشياء مبنية جاهزة جوا الموديلات (منطق تلقائي)
- **`WorkOrderPart`**: أي صف بيتضاف فيه، بيخصم الكمية تلقائيًا من `spare_parts.quantity_in_stock` (والعكس عند الحذف).
- **`WorkOrder::getPartsCostAttribute()`**: يديك تكلفة قطع الغيار كلها لأمر شغل معين — استخدمها كده: `$workOrder->parts_cost`.
- **`SparePart::isBelowMinimum()`**: بترجع true لو الكمية وصلت للحد الأدنى (تقدر تستخدمها في تنبيه أو Query scope لاحقًا).
- **`DowntimeRecord`**: بيحسب `duration_minutes` تلقائيًا لما يتسجل `start_time` و `end_time`.

## بيانات ناقصة عمدًا
- الأدوار (Admin, Supervisor, Technician, Viewer) هتتضاف عن طريق Seeder (الخطوة الجاية) باستخدام Spatie، مش هنا في الموديلات.
- لسه محتاجين: Policies (AssetPolicy, WorkOrderPolicy) تتحكم في مين يعمل إيه حسب الدور.

## الخطوة الجاية
Seeders لبيانات تجريبية (أقسام، مواقع، تصنيفات، أدوار وصلاحيات، مستخدم Admin افتراضي).
