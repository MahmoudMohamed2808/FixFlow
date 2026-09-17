<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RolesAndPermissionsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $permissions = [
            'assets.view',
            'assets.create',
            'assets.edit',
            'assets.delete',
            'work_orders.view',
            'work_orders.create',
            'work_orders.edit',
            'work_orders.assign',
            'work_orders.update_status',
            'work_orders.delete',
            'work_orders.comment',
            'schedules.view',
            'schedules.create',
            'schedules.edit',
            'schedules.delete',
            'inventory.view',
            'inventory.create',
            'inventory.edit',
            'inventory.delete',
            'suppliers.view',
            'suppliers.create',
            'suppliers.edit',
            'suppliers.delete',
            'clients.view',
            'clients.create',
            'clients.edit',
            'clients.delete',
            'invoices.view',
            'invoices.create',
            'invoices.edit',
            'invoices.delete',
            'budgets.view',
            'budgets.create',
            'budgets.edit',
            'budgets.delete',
            'reports.view',
            'users.manage',
            'settings.manage',
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission]);
        }

        $admin = Role::firstOrCreate(['name' => 'Admin']);
        $admin->syncPermissions(Permission::all());

        $supervisor = Role::firstOrCreate(['name' => 'Supervisor']);
        $supervisor->syncPermissions([
            'assets.view',
            'assets.create',
            'assets.edit',
            'work_orders.view',
            'work_orders.create',
            'work_orders.edit',
            'work_orders.assign',
            'work_orders.update_status',
            'work_orders.comment',
            'schedules.view',
            'schedules.create',
            'schedules.edit',
            'inventory.view',
            'inventory.create',
            'inventory.edit',
            'suppliers.view',
            'suppliers.create',
            'suppliers.edit',
            'reports.view',
            'clients.view',
            'clients.create',
            'clients.edit',
            'invoices.view',
            'invoices.create',
            'invoices.edit',
            'budgets.view',
            'budgets.create',
            'budgets.edit',
        ]);

        $technician = Role::firstOrCreate(['name' => 'Technician']);
        $technician->syncPermissions([
            'assets.view',
            'work_orders.view',
            'work_orders.update_status',
            'work_orders.comment',
            'inventory.view',
            'reports.view',
        ]);

        $viewer = Role::firstOrCreate(['name' => 'Viewer']);
        $viewer->syncPermissions([
            'assets.view',
            'work_orders.view',
            'inventory.view',
            'reports.view',
        ]);
    }
}
