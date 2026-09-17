# Migrations - نظام إدارة الصيانة (CMMS)

## طريقة الاستخدام
1. انسخ كل الملفات دي إلى `database/migrations/` في مشروع Laravel بتاعك.
2. تأكد إنك عملت تثبيت لـ:
   - `laravel/breeze` (أو أي نظام مصادقة بيعمل جدول `users` بالحقول الافتراضية: id, name, email, password...)
   - `spatie/laravel-permission` (شغّل الأمر `php artisan vendor:publish --provider="Spatie\Permission\PermissionServiceProvider"` عشان تنزل migration الخاصة بالأدوار والصلاحيات هي كمان)
3. رتيب migrations لازم يكون: users (من Breeze) → departments → locations → add_department_id_to_users → asset_categories → suppliers → assets → preventive_schedules → work_orders → spare_parts → work_order_parts → downtime_records → attachments/comments/logs. الأسماء هنا متبرمجة بالترتيب ده بالفعل عن طريق التاريخ في اسم الملف.
4. شغّل:
   ```bash
   php artisan migrate
   ```

## ملاحظات مهمة
- **الأدوار (Roles):** معمول اعتمادًا على `spatie/laravel-permission` بدل جدول `roles` مخصص، عشان مرونة إدارة الصلاحيات. لو عايز جدول roles بسيط بدالها قولي وأعدل الملفات.
- **work_order_parts:** جدول pivot لكنه فيه بيانات إضافية (الكمية والسعر)، لازم تعمله Model مستقل مش `belongsToMany` عادي.
- **Soft Deletes:** موجودة على `assets` و `work_orders` بس، عشان الاحتفاظ بالتاريخ.
- بعد الـ migrations، الخطوة الجاية هي: عمل الـ Models والعلاقات، وبعدين الـ Seeders لبيانات تجريبية.
