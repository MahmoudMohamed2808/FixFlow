<?php

use App\Models\Asset;
use App\Models\Budget;
use App\Models\Client;
use App\Models\Invoice;
use App\Models\PreventiveSchedule;
use App\Models\SparePart;
use App\Models\Supplier;
use App\Models\User;
use App\Models\WorkOrder;
use Database\Seeders\RolesAndPermissionsSeeder;

beforeEach(function () {
    (new RolesAndPermissionsSeeder)->run();
});

it('grants the Admin role every permission', function () {
    $admin = User::factory()->create()->assignRole('Admin');

    $permissions = collect([
        'assets.view', 'assets.create', 'assets.edit', 'assets.delete',
        'work_orders.view', 'work_orders.create', 'work_orders.edit', 'work_orders.assign',
        'work_orders.update_status', 'work_orders.delete', 'work_orders.comment',
        'schedules.view', 'schedules.create', 'schedules.edit', 'schedules.delete',
        'inventory.view', 'inventory.create', 'inventory.edit', 'inventory.delete',
        'suppliers.view', 'suppliers.create', 'suppliers.edit', 'suppliers.delete',
        'clients.view', 'clients.create', 'clients.edit', 'clients.delete',
        'invoices.view', 'invoices.create', 'invoices.edit', 'invoices.delete',
        'budgets.view', 'budgets.create', 'budgets.edit', 'budgets.delete',
        'reports.view', 'users.manage', 'settings.manage',
    ]);

    $permissions->each(fn (string $permission) => expect($admin->can($permission))->toBeTrue("Admin should have {$permission}"));
});

it('lets the Supervisor view and edit but not delete core records', function () {
    $supervisor = User::factory()->create()->assignRole('Supervisor');
    $asset = Asset::factory()->create();
    $order = WorkOrder::factory()->for($asset)->create();
    $schedule = PreventiveSchedule::factory()->for($asset)->create();
    $part = SparePart::factory()->create();
    $supplier = Supplier::factory()->create();
    $client = Client::factory()->create();
    $invoice = Invoice::factory()->create();
    $budget = Budget::factory()->create();

    expect($supervisor->can('viewAny', Asset::class))->toBeTrue();
    expect($supervisor->can('create', Asset::class))->toBeTrue();
    expect($supervisor->can('delete', $asset))->toBeFalse();

    expect($supervisor->can('create', WorkOrder::class))->toBeTrue();
    expect($supervisor->can('assign', $order))->toBeTrue();
    expect($supervisor->can('delete', $order))->toBeFalse();

    expect($supervisor->can('create', PreventiveSchedule::class))->toBeTrue();
    expect($supervisor->can('delete', $schedule))->toBeFalse();

    expect($supervisor->can('create', SparePart::class))->toBeTrue();
    expect($supervisor->can('adjustStock', $part))->toBeTrue();
    expect($supervisor->can('delete', $part))->toBeFalse();

    expect($supervisor->can('create', Supplier::class))->toBeTrue();
    expect($supervisor->can('delete', $supplier))->toBeFalse();

    expect($supervisor->can('viewAny', Client::class))->toBeTrue();
    expect($supervisor->can('create', Client::class))->toBeTrue();
    expect($supervisor->can('delete', $client))->toBeFalse();

    expect($supervisor->can('viewAny', Invoice::class))->toBeTrue();
    expect($supervisor->can('create', Invoice::class))->toBeTrue();
    expect($supervisor->can('delete', $invoice))->toBeFalse();

    expect($supervisor->can('viewAny', Budget::class))->toBeTrue();
    expect($supervisor->can('create', Budget::class))->toBeTrue();
    expect($supervisor->can('delete', $budget))->toBeFalse();

    expect($supervisor->can('reports.view'))->toBeTrue();
    expect($supervisor->can('users.manage'))->toBeFalse();
    expect($supervisor->can('settings.manage'))->toBeFalse();
});

it('restricts the Technician to work order execution and read-only view', function () {
    $technician = User::factory()->create()->assignRole('Technician');
    $asset = Asset::factory()->create();
    $order = WorkOrder::factory()->for($asset)->create();

    expect($technician->can('viewAny', Asset::class))->toBeTrue();
    expect($technician->can('create', Asset::class))->toBeFalse();

    expect($technician->can('viewAny', WorkOrder::class))->toBeTrue();
    expect($technician->can('updateStatus', $order))->toBeTrue();
    expect($technician->can('comment', $order))->toBeTrue();
    expect($technician->can('create', WorkOrder::class))->toBeFalse();
    expect($technician->can('assign', $order))->toBeFalse();
    expect($technician->can('update', $order))->toBeFalse();

    expect($technician->can('viewAny', SparePart::class))->toBeTrue();
    expect($technician->can('create', SparePart::class))->toBeFalse();

    expect($technician->can('reports.view'))->toBeTrue();

    expect($technician->can('viewAny', PreventiveSchedule::class))->toBeFalse();
    expect($technician->can('viewAny', Supplier::class))->toBeFalse();
    expect($technician->can('viewAny', Client::class))->toBeFalse();
    expect($technician->can('viewAny', Invoice::class))->toBeFalse();
    expect($technician->can('viewAny', Budget::class))->toBeFalse();
});

it('keeps the Viewer role strictly read-only', function () {
    $viewer = User::factory()->create()->assignRole('Viewer');
    $order = WorkOrder::factory()->for(Asset::factory()->create())->create();

    expect($viewer->can('viewAny', Asset::class))->toBeTrue();
    expect($viewer->can('viewAny', WorkOrder::class))->toBeTrue();
    expect($viewer->can('viewAny', SparePart::class))->toBeTrue();
    expect($viewer->can('reports.view'))->toBeTrue();

    expect($viewer->can('create', Asset::class))->toBeFalse();
    expect($viewer->can('create', WorkOrder::class))->toBeFalse();
    expect($viewer->can('create', SparePart::class))->toBeFalse();
    expect($viewer->can('updateStatus', $order))->toBeFalse();
    expect($viewer->can('viewAny', Client::class))->toBeFalse();
    expect($viewer->can('viewAny', Invoice::class))->toBeFalse();
    expect($viewer->can('viewAny', Budget::class))->toBeFalse();
    expect($viewer->can('users.manage'))->toBeFalse();
});

it('lets an assignee update the status of only their own assigned work order', function () {
    $technician = User::factory()->create()->assignRole('Technician');
    $viewerWithoutPermission = User::factory()->create()->assignRole('Viewer');
    $asset = Asset::factory()->create();

    $mine = WorkOrder::factory()->for($asset)->create(['assigned_to' => $viewerWithoutPermission->id]);
    $others = WorkOrder::factory()->for($asset)->create();

    expect($technician->can('updateStatus', $mine))->toBeTrue();
    expect($technician->can('updateStatus', $others))->toBeTrue();

    expect($viewerWithoutPermission->can('updateStatus', $mine))->toBeTrue();
    expect($viewerWithoutPermission->can('updateStatus', $others))->toBeFalse();
});
