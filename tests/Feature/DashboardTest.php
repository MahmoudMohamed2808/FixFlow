<?php

use App\Models\Asset;
use App\Models\Client;
use App\Models\Invoice;
use App\Models\PreventiveSchedule;
use App\Models\User;
use App\Models\WorkOrder;
use Database\Seeders\RolesAndPermissionsSeeder;

test('guests are redirected to the login page', function () {
    $response = $this->get(route('dashboard'));
    $response->assertRedirect(route('login'));
});

test('authenticated users can visit the dashboard', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $response = $this->get(route('dashboard'));
    $response->assertOk();
});

test('dashboard lists overdue preventive schedules', function () {
    $asset = Asset::factory()->create();
    $schedule = PreventiveSchedule::factory()->for($asset)->create([
        'title' => 'تزييت الشاحنة',
        'next_due_date' => now()->subDays(2),
    ]);

    $user = User::factory()->create();
    $this->actingAs($user);

    $this->get(route('dashboard'))
        ->assertOk()
        ->assertSee('تزييت الشاحنة');
});

test('dashboard ranks the most faulty assets', function () {
    $asset = Asset::factory()->create(['name' => 'مولد ثانٍ']);
    WorkOrder::factory()->for($asset)->state(['type' => 'corrective'])->count(3)->create();

    $user = User::factory()->create();
    $this->actingAs($user);

    $this->get(route('dashboard'))->assertOk()->assertSee('مولد ثانٍ');
});

test('dashboard shows recent work orders', function () {
    $asset = Asset::factory()->create();
    $order = WorkOrder::factory()->for($asset)->create();

    $user = User::factory()->create();
    $this->actingAs($user);

    $this->get(route('dashboard'))->assertOk()->assertSee($order->code);
});

test('dashboard shows the financial overview to users with invoices.view', function () {
    (new RolesAndPermissionsSeeder)->run();

    $year = date('Y');
    $asset = Asset::factory()->create();
    WorkOrder::factory()->for($asset)->create([
        'scheduled_date' => "{$year}-06-10 10:00",
        'cost' => 4000,
    ]);

    $client = Client::factory()->create();
    Invoice::factory()->for($client, 'client')->paid()->create([
        'total_amount' => 10000,
        'subtotal' => 10000,
        'paid_at' => "{$year}-07-01 10:00",
    ]);

    $admin = User::factory()->create()->assignRole('Admin');

    $this->actingAs($admin)->get(route('dashboard'))
        ->assertOk()
        ->assertSee('الملخص المالي')
        ->assertSee('4,000.00')
        ->assertSee('10,000.00')
        ->assertSee('6,000.00');
});

test('dashboard hides the financial overview from unauthorized users', function () {
    (new RolesAndPermissionsSeeder)->run();

    $viewer = User::factory()->create()->assignRole('Viewer');

    $this->actingAs($viewer)->get(route('dashboard'))
        ->assertOk()
        ->assertDontSee('الملخص المالي');
});
