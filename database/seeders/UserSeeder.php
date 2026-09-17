<?php

namespace Database\Seeders;

use App\Models\Department;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    /**
     * فنيون ومشرفون بأسماء مصرية واقعية لتسهيل التجربة من المتصفح.
     * كلمة السر الموحدة للجميع: password
     */
    public function run(): void
    {
        if (Department::count() === 0) {
            (new DepartmentSeeder)->run();
        }

        $maintenance = Department::where('name', 'الصيانة')->first();
        $production = Department::where('name', 'الإنتاج')->first();
        $quality = Department::where('name', 'الجودة')->first();
        $stores = Department::where('name', 'المستودعات')->first();

        $people = [
            ['name' => 'م. أحمد الشريف', 'email' => 'ahmed.elsherif@fixflow.test', 'role' => 'Supervisor', 'dept' => $maintenance, 'rate' => 300, 'phone' => '01001234567'],
            ['name' => 'م. كريم فؤاد', 'email' => 'karim.fouad@fixflow.test', 'role' => 'Supervisor', 'dept' => $production, 'rate' => 280, 'phone' => '01111234567'],
            ['name' => 'محمود السيد', 'email' => 'mahmoud.elsayed@fixflow.test', 'role' => 'Technician', 'dept' => $maintenance, 'rate' => 200, 'phone' => '01221234567'],
            ['name' => 'مصطفى حسن', 'email' => 'mostafa.hassan@fixflow.test', 'role' => 'Technician', 'dept' => $maintenance, 'rate' => 180, 'phone' => '01551234567'],
            ['name' => 'إبراهيم رمضان', 'email' => 'ibrahim.ramadan@fixflow.test', 'role' => 'Technician', 'dept' => $maintenance, 'rate' => 170, 'phone' => '01029876543'],
            ['name' => 'وليد حمدي', 'email' => 'walid.hamdy@fixflow.test', 'role' => 'Technician', 'dept' => $production, 'rate' => 160, 'phone' => '01129876543'],
            ['name' => 'طارق عزت', 'email' => 'tarek.ezzat@fixflow.test', 'role' => 'Technician', 'dept' => $production, 'rate' => 150, 'phone' => '01229876543'],
            ['name' => 'هاني فراج', 'email' => 'hany.farrag@fixflow.test', 'role' => 'Technician', 'dept' => $maintenance, 'rate' => 190, 'phone' => '01529876543'],
            ['name' => 'عمر عبد الرحمن', 'email' => 'omar.abdelrahman@fixflow.test', 'role' => 'Technician', 'dept' => $quality, 'rate' => 175, 'phone' => '01038765421'],
            ['name' => 'خالد منصور', 'email' => 'khaled.mansour@fixflow.test', 'role' => 'Technician', 'dept' => $stores, 'rate' => 140, 'phone' => '01138765421'],
            ['name' => 'أ. منى فاروق', 'email' => 'mona.farouk@fixflow.test', 'role' => 'Viewer', 'dept' => $quality, 'rate' => 120, 'phone' => '01238765421'],
            ['name' => 'أ. دينا سمير', 'email' => 'dina.samir@fixflow.test', 'role' => 'Viewer', 'dept' => null, 'rate' => 120, 'phone' => '01538765421'],
        ];

        foreach ($people as $person) {
            $user = User::firstOrCreate(
                ['email' => $person['email']],
                [
                    'name' => $person['name'],
                    'password' => Hash::make('password'),
                    'email_verified_at' => now(),
                    'department_id' => $person['dept']?->id,
                    'phone' => $person['phone'],
                    'is_active' => true,
                    'hourly_rate' => $person['rate'],
                ]
            );

            if (! $user->hasRole($person['role'])) {
                $user->assignRole($person['role']);
            }
        }
    }
}
