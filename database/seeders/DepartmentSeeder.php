<?php

namespace Database\Seeders;

use App\Models\Department;
use Illuminate\Database\Seeder;

class DepartmentSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $departments = [
            ['name' => 'الصيانة'],
            ['name' => 'الإنتاج'],
            ['name' => 'المستودعات'],
            ['name' => 'الجودة'],
            ['name' => 'الموارد البشرية'],
            ['name' => 'الإدارة العامة'],
        ];

        foreach ($departments as $department) {
            Department::firstOrCreate(['name' => $department['name']]);
        }
    }
}
