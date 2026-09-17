<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

class AdminUserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        if (Role::count() === 0) {
            (new RolesAndPermissionsSeeder)->run();
        }

        $admin = User::firstOrCreate(
            ['email' => config('app.admin_email', 'admin@fixflow.test')],
            [
                'name' => config('app.admin_name', 'مدير النظام'),
                'password' => Hash::make(config('app.admin_password', 'password')),
                'email_verified_at' => now(),
                'is_active' => true,
            ]
        );

        $admin->assignRole('Admin');

        $this->command?->info("تم إنشاء مستخدم الأدمن: {$admin->email}");
    }
}
