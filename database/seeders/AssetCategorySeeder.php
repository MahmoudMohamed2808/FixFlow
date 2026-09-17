<?php

namespace Database\Seeders;

use App\Models\AssetCategory;
use App\Models\Department;
use Illuminate\Database\Seeder;

class AssetCategorySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        if (Department::count() === 0) {
            (new DepartmentSeeder)->run();
        }

        $maintenance = Department::where('name', 'الصيانة')->first();
        $production = Department::where('name', 'الإنتاج')->first();

        $machine = AssetCategory::firstOrCreate(
            ['name' => 'آلات ومعدات'],
            ['department_id' => $maintenance?->id ?? $production?->id]
        );
        $electrical = AssetCategory::firstOrCreate(
            ['name' => 'معدات كهربائية'],
            ['department_id' => $maintenance?->id ?? $production?->id]
        );
        $building = AssetCategory::firstOrCreate(
            ['name' => 'مباني ومرافق'],
            ['department_id' => $production?->id]
        );

        foreach ([
            ['name' => 'مكابس', 'parent' => $machine],
            ['name' => 'سيور ناقلة', 'parent' => $machine],
            ['name' => 'طلمبات وكمبروسرات', 'parent' => $machine],
            ['name' => 'محركات', 'parent' => $electrical],
            ['name' => 'لوحات تحكم', 'parent' => $electrical],
            ['name' => 'مولدات ومحولات', 'parent' => $electrical],
            ['name' => 'أنظمة تكييف', 'parent' => $building],
            ['name' => 'غلايات وأفران', 'parent' => $building],
        ] as $child) {
            AssetCategory::firstOrCreate(
                ['name' => $child['name']],
                ['parent_id' => $child['parent']->id, 'department_id' => $child['parent']->department_id]
            );
        }
    }
}
