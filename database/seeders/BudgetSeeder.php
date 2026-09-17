<?php

namespace Database\Seeders;

use App\Models\Budget;
use App\Models\Department;
use Illuminate\Database\Seeder;

class BudgetSeeder extends Seeder
{
    public function run(): void
    {
        if (Department::count() === 0) {
            (new DepartmentSeeder)->run();
        }

        $year = date('Y');
        $deptId = fn (string $name) => Department::where('name', $name)->value('id');

        $budgets = [
            ['title' => "موازنة الصيانة العامة {$year}", 'dept' => null, 'start' => "{$year}-01-01", 'end' => "{$year}-12-31", 'amount' => 600000, 'notes' => 'تشمل قطع الغيار والأجور الخارجية لكل الأقسام'],
            ['title' => "موازنة قسم الصيانة — النصف الأول {$year}", 'dept' => 'الصيانة', 'start' => "{$year}-01-01", 'end' => "{$year}-06-30", 'amount' => 180000, 'notes' => 'قطع غيار سريعة الاستهلاك (فلاتر، سيور، زيوت)'],
            ['title' => "موازنة قسم الصيانة — النصف الثاني {$year}", 'dept' => 'الصيانة', 'start' => "{$year}-07-01", 'end' => "{$year}-12-31", 'amount' => 150000, 'notes' => null],
            ['title' => "موازنة قسم الإنتاج {$year}", 'dept' => 'الإنتاج', 'start' => "{$year}-01-01", 'end' => "{$year}-12-31", 'amount' => 90000, 'notes' => 'صيانة وقائية لخطوط الإنتاج'],
            ['title' => "موازنة المستودعات {$year}", 'dept' => 'المستودعات', 'start' => "{$year}-01-01", 'end' => "{$year}-12-31", 'amount' => 40000, 'notes' => 'إعادة ملء المخزون الحرج'],
            // موازنة صغيرة مستنفدة عمدًا لتظهر حالة تجاوز الموازنة في المتصفح.
            ['title' => "موازنة طوارئ المولدات {$year}", 'dept' => 'الصيانة', 'start' => "{$year}-01-01", 'end' => "{$year}-12-31", 'amount' => 1000, 'notes' => 'بند رمزي للتنبيه — يُتوقع تجاوزه'],
        ];

        foreach ($budgets as $budget) {
            Budget::firstOrCreate(
                ['title' => $budget['title']],
                [
                    'department_id' => $budget['dept'] ? $deptId($budget['dept']) : null,
                    'period_start' => $budget['start'],
                    'period_end' => $budget['end'],
                    'allocated_amount' => $budget['amount'],
                    'notes' => $budget['notes'],
                ]
            );
        }
    }
}
