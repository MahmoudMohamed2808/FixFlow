<?php

namespace Database\Seeders;

use App\Models\Department;
use App\Models\Location;
use Illuminate\Database\Seeder;

class LocationSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        if (Department::count() === 0) {
            (new DepartmentSeeder)->run();
        }

        $production = Department::where('name', 'الإنتاج')->first();
        $stores = Department::where('name', 'المستودعات')->first();

        $mainPlant = Location::firstOrCreate([
            'name' => 'مصنع 6 أكتوبر — المبنى الرئيسي',
            'department_id' => $production?->id,
        ]);

        $floorA = Location::firstOrCreate([
            'name' => 'عنبر الإنتاج A',
            'department_id' => $production?->id,
            'parent_id' => $mainPlant->id,
        ]);

        $floorB = Location::firstOrCreate([
            'name' => 'عنبر الإنتاج B',
            'department_id' => $production?->id,
            'parent_id' => $mainPlant->id,
        ]);

        foreach ([
            ['name' => 'خط الإنتاج 1', 'parent' => $floorA],
            ['name' => 'خط الإنتاج 2', 'parent' => $floorA],
            ['name' => 'غرفة المحركات', 'parent' => $floorA],
            ['name' => 'غرفة الكمبروسرات', 'parent' => $floorB],
            ['name' => 'غرفة الكهرباء الرئيسية', 'parent' => $floorB],
        ] as $child) {
            Location::firstOrCreate([
                'name' => $child['name'],
                'department_id' => $production?->id,
                'parent_id' => $child['parent']->id,
            ]);
        }

        $warehouse = Location::firstOrCreate([
            'name' => 'مخزن قطع الغيار',
            'department_id' => $stores?->id ?? $production?->id,
        ]);

        Location::firstOrCreate([
            'name' => 'مخزن الزيوت والشحوم',
            'department_id' => $stores?->id ?? $production?->id,
            'parent_id' => $warehouse->id,
        ]);

        Location::firstOrCreate([
            'name' => 'الساحة الخارجية — المولدات',
            'department_id' => $production?->id,
            'parent_id' => $mainPlant->id,
        ]);
    }
}
