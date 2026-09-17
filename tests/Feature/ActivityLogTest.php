<?php

use App\Models\ActivityLog;
use App\Models\Asset;
use App\Models\Client;
use App\Models\Invoice;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;

it('records an activity log entry for an authenticated user', function () {
    $user = User::factory()->create();
    $asset = Asset::factory()->create();

    $log = ActivityLog::record('created', $asset, $user->id);

    $this->assertDatabaseHas('activity_logs', [
        'id' => $log->id,
        'user_id' => $user->id,
        'action' => 'created',
        'model_type' => 'App\Models\Asset',
        'model_id' => $asset->id,
    ]);

    expect($log->subject->is($asset))->toBeTrue();
    expect($log->user->is($user))->toBeTrue();
});

it('records activity from the current authenticated user when none given', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $asset = Asset::factory()->create();
    ActivityLog::record('updated', $asset);

    $this->assertDatabaseHas('activity_logs', [
        'user_id' => $user->id,
        'action' => 'updated',
    ]);
});

it('renders recorded activities on the activity logs page', function () {
    (new RolesAndPermissionsSeeder)->run();

    $admin = User::factory()->create();
    $admin->assignRole('Admin');
    $this->actingAs($admin);

    $asset = Asset::factory()->create(['name' => 'مولد احتياطي']);
    ActivityLog::record('created', $asset, $admin->id);

    $this->get(route('activity-logs.index'))
        ->assertOk()
        ->assertSee('مولد احتياطي')
        ->assertSee($admin->name);
});

it('renders financial activities with translated types and identifiers', function () {
    (new RolesAndPermissionsSeeder)->run();

    $admin = User::factory()->create();
    $admin->assignRole('Admin');
    $this->actingAs($admin);

    $client = Client::factory()->create(['name' => 'عميل السجل']);
    $invoice = Invoice::factory()->for($client, 'client')->create();

    ActivityLog::record('created', $client, $admin->id);
    ActivityLog::record('added_item', $invoice, $admin->id);

    $this->get(route('activity-logs.index'))
        ->assertOk()
        ->assertSee('العملاء')
        ->assertSee('عميل السجل')
        ->assertSee('الفواتير')
        ->assertSee($invoice->invoice_number)
        ->assertSee('إضافة بند فاتورة');
});
