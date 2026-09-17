<?php

namespace Database\Seeders;

use App\Models\Asset;
use App\Models\AssetCategory;
use App\Models\Budget;
use App\Models\Client;
use App\Models\Department;
use App\Models\Location;
use App\Models\User;
use App\Models\WorkOrder;
use Illuminate\Database\Seeder;

/**
 * بيانات تجريبية للوحدة المالية (عملاء، أوامر، فواتير، أجور، ميزانيات).
 *
 * تُشغَّل يدويًا فقط للمراجعة المحلية — وليست جزءًا من DatabaseSeeder
 * حتى لا تصل بيانات تجريبية إلى بيئة الإنتاج أبدًا:
 *
 *   php artisan db:seed --class=DemoFinancialSeeder
 *
 * المتطلبات: تشغيل `php artisan db:seed` أولًا (أقسام، مواقع، تصنيفات، مستخدم).
 * آمنة عند التكرار: تتوقف فورًا لو وُجد أي عميل مسبقًا.
 */
class DemoFinancialSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        if (Client::query()->exists()) {
            $this->command?->info('يوجد عملاء مسبقًا — تم تخطي البيانات التجريبية.');

            return;
        }

        $year = date('Y');
        $technician = User::firstOrFail();
        $department = Department::firstOrCreate(['name' => 'الصيانة']);

        $location = Location::firstOrCreate(
            ['name' => 'مصنع العبور', 'department_id' => $department->id],
            ['parent_id' => null]
        );

        $asset = Asset::firstOrCreate(
            ['serial_number' => 'DEMO-COMP-01'],
            [
                'name' => 'ضاغط تجريبي',
                'category_id' => AssetCategory::first()?->id,
                'location_id' => $location->id,
                'status' => 'operational',
                'created_by' => $technician->id,
            ]
        );

        $nour = Client::firstOrCreate(
            ['email' => 'info@elnour.example.com'],
            [
                'name' => 'محمد النور',
                'company_name' => 'شركة النور للصناعات',
                'phone' => '01001234567',
                'address' => 'المنطقة الصناعية، العبور',
            ]
        );

        $delta = Client::firstOrCreate(
            ['email' => 'contact@delta.example.com'],
            [
                'name' => 'أحمد الدلتا',
                'company_name' => 'مؤسسة الدلتا',
                'phone' => '01111234567',
            ]
        );

        Client::firstOrCreate(
            ['email' => 'workshop@example.com'],
            ['name' => 'ورشة الإخوة', 'phone' => '01221234567']
        );

        // أمر مدفوع للعميل: تكلفته من ساعات العمل (5 × 200 = 1000).
        $orderOne = WorkOrder::create([
            'asset_id' => $asset->id,
            'client_id' => $nour->id,
            'type' => 'corrective',
            'priority' => 'high',
            'status' => 'completed',
            'description' => 'إصلاح ضاغط الهواء الرئيسي',
            'reported_by' => $technician->id,
            'assigned_to' => $technician->id,
            'scheduled_date' => "{$year}-06-10 10:00",
            'started_at' => "{$year}-06-10 10:00",
            'completed_at' => "{$year}-06-12 14:00",
            'cost' => 0,
        ]);

        $orderOne->laborEntries()->create([
            'user_id' => $technician->id,
            'hours_spent' => 5,
            'hourly_rate' => 200,
        ]);

        // أمر مدفوع ثانٍ بتكلفة مباشرة (قطع غيار) 2500.
        $orderTwo = WorkOrder::create([
            'asset_id' => $asset->id,
            'client_id' => $nour->id,
            'type' => 'preventive',
            'priority' => 'medium',
            'status' => 'completed',
            'description' => 'صيانة دورية شاملة',
            'reported_by' => $technician->id,
            'scheduled_date' => "{$year}-07-05 09:00",
            'started_at' => "{$year}-07-05 09:00",
            'completed_at' => "{$year}-07-05 16:00",
            'cost' => 2500,
        ]);

        // أمر داخلي (بدون عميل): تكلفته من الأجور (2 × 150 = 300).
        $internal = WorkOrder::create([
            'asset_id' => $asset->id,
            'client_id' => null,
            'type' => 'corrective',
            'priority' => 'low',
            'status' => 'completed',
            'description' => 'تشحيم عام',
            'reported_by' => $technician->id,
            'scheduled_date' => "{$year}-08-01 09:00",
            'started_at' => "{$year}-08-01 09:00",
            'completed_at' => "{$year}-08-01 12:00",
            'cost' => 0,
        ]);

        $internal->laborEntries()->create([
            'user_id' => $technician->id,
            'hours_spent' => 2,
            'hourly_rate' => 150,
        ]);

        // فاتورة مدفوعة (2000 + 1500 = 3500) مرتبطة بأمر الشغل الأول.
        $paid = $nour->invoices()->create([
            'work_order_id' => $orderOne->id,
            'issue_date' => "{$year}-06-15",
            'due_date' => "{$year}-06-29",
            'status' => 'paid',
            'tax' => 0,
        ]);

        $paid->items()->create(['description' => 'مصنعية إصلاح الضاغط', 'quantity' => 1, 'unit_price' => 2000]);
        $paid->items()->create(['description' => 'قطع غيار مستبدلة', 'quantity' => 3, 'unit_price' => 500]);
        $paid->markAsPaid();
        $paid->update(['paid_at' => "{$year}-06-20 10:00"]);

        // فاتورة مرسلة مستحقة 1200 مرتبطة بأمر الشغل الثاني.
        $sent = $nour->invoices()->create([
            'work_order_id' => $orderTwo->id,
            'issue_date' => "{$year}-07-10",
            'due_date' => "{$year}-07-24",
            'status' => 'sent',
            'tax' => 0,
        ]);

        $sent->items()->create(['description' => 'صيانة دورية شاملة', 'quantity' => 1, 'unit_price' => 1200]);

        // فاتورة مسودة لعميل آخر.
        $draft = $delta->invoices()->create([
            'issue_date' => "{$year}-08-05",
            'status' => 'draft',
            'tax' => 0,
        ]);

        $draft->items()->create(['description' => 'معاينة مبدئية', 'quantity' => 1, 'unit_price' => 800]);

        // ميزانية عامة سليمة + ميزانية قسم متجاوَزة لعرض الحالتين.
        Budget::firstOrCreate(
            ['title' => 'ميزانية الصيانة '.$year.' (تجريبية)'],
            [
                'department_id' => null,
                'period_start' => "{$year}-01-01",
                'period_end' => "{$year}-12-31",
                'allocated_amount' => 50000,
                'notes' => 'بيانات تجريبية',
            ]
        );

        Budget::firstOrCreate(
            ['title' => 'ميزانية قسم الصيانة (تجريبية)'],
            [
                'department_id' => $department->id,
                'period_start' => "{$year}-01-01",
                'period_end' => "{$year}-12-31",
                'allocated_amount' => 1000,
                'notes' => 'مخصص منخفض عمدًا لعرض حالة التجاوز',
            ]
        );

        $this->command?->info('تم إنشاء البيانات التجريبية للوحدة المالية.');
    }
}
