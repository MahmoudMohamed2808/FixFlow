<?php

use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;

it('renders each CMMS module page for an authenticated user', function (string $route) {
    $user = User::factory()->create();
    $this->actingAs($user);

    $this->get(route($route))->assertOk();
})->with([
    'dashboard' => 'dashboard',
    'assets' => 'assets.index',
    'work orders' => 'work-orders.index',
    'schedules' => 'schedules.index',
    'inventory' => 'inventory.index',
    'suppliers' => 'suppliers.index',
    'reports' => 'reports.index',
    'clients' => 'clients.index',
    'invoices' => 'invoices.index',
    'budgets' => 'budgets.index',
]);

it('forbids visiting the users page without the manage permission', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $this->get(route('users.index'))->assertForbidden();
});

it('allows an admin to visit the users page', function () {
    (new RolesAndPermissionsSeeder)->run();

    $admin = User::factory()->create();
    $admin->assignRole('Admin');
    $this->actingAs($admin);

    $this->get(route('users.index'))->assertOk();
});

it('forbids visiting the activity logs page without the manage permission', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $this->get(route('activity-logs.index'))->assertForbidden();
});

it('allows an admin to visit the activity logs page', function () {
    (new RolesAndPermissionsSeeder)->run();

    $admin = User::factory()->create();
    $admin->assignRole('Admin');
    $this->actingAs($admin);

    $this->get(route('activity-logs.index'))->assertOk();
});
