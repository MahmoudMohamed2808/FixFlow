<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     *
     * الترتيب مقصود: الأساس (الأدوار والأقسام) أولًا، ثم المخزون والأصول،
     * ثم أوامر الشغل بما تحمله (قطع/أجور/تعليقات/توقف)، وأخيرًا الفواتير
     * والموازنات وسجل الأنشطة ثم إشعارات العرض (تقرأ بيانات إخوتها).
     * كل Seeder آمن للتكرار (firstOrCreate).
     *
     * ملاحظة: لا نستخدم WithoutModelEvents عمدًا — سيدات الأوامر والفواتير
     * تعتمد على model hooks (كود WO التلقائي، رقم الفاتورة، خصم المخزون،
     * إعادة حساب التكلفة والمجاميع).
     */
    public function run(): void
    {
        $this->call([
            RolesAndPermissionsSeeder::class,
            DepartmentSeeder::class,
            LocationSeeder::class,
            AssetCategorySeeder::class,
            AdminUserSeeder::class,
            UserSeeder::class,
            SupplierSeeder::class,
            SparePartSeeder::class,
            ClientSeeder::class,
            AssetSeeder::class,
            PreventiveScheduleSeeder::class,
            WorkOrderSeeder::class,
            InvoiceSeeder::class,
            BudgetSeeder::class,
            ActivityLogSeeder::class,
            NotificationSeeder::class,
        ]);
    }
}
