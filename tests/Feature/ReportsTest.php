<?php

use App\Models\Asset;
use App\Models\Client;
use App\Models\Department;
use App\Models\DowntimeRecord;
use App\Models\Invoice;
use App\Models\Location;
use App\Models\SparePart;
use App\Models\User;
use App\Models\WorkOrder;
use Database\Seeders\RolesAndPermissionsSeeder;

beforeEach(function () {
    (new RolesAndPermissionsSeeder)->run();

    $this->admin = User::factory()->create();
    $this->admin->assignRole('Admin');
});

it('renders the reports index page for an authenticated user', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->get(route('reports.index'))->assertOk();
});

it('shows the summary counts', function () {
    $asset = Asset::factory()->create();
    WorkOrder::factory()->for($asset)->create(['status' => 'open', 'cost' => 0]);
    WorkOrder::factory()->for($asset)->create(['status' => 'completed', 'cost' => 150]);

    $this->actingAs($this->admin)
        ->get(route('reports.index'))
        ->assertOk()
        ->assertSee('150');
});

it('computes cost by asset for completed work orders', function () {
    $asset = Asset::factory()->create(['name' => 'آلة الإنتاج']);
    WorkOrder::factory()->for($asset)->create(['status' => 'completed', 'cost' => 100]);
    WorkOrder::factory()->for($asset)->create(['status' => 'completed', 'cost' => 50]);
    WorkOrder::factory()->for($asset)->create(['status' => 'open', 'cost' => 999]);

    Livewire::actingAs($this->admin)
        ->test('pages::reports.index')
        ->set('type', 'asset')
        ->assertSee('آلة الإنتاج')
        ->assertSee('150')
        ->assertDontSee('999');
});

it('computes cost by department through the asset location', function () {
    $dept = Department::factory()->create(['name' => 'قسم الصيانة']);
    $location = Location::factory()->create(['department_id' => $dept->id]);
    $asset = Asset::factory()->create(['location_id' => $location->id, 'name' => 'أصل القسم']);
    WorkOrder::factory()->for($asset)->create(['status' => 'completed', 'cost' => 250]);

    Livewire::actingAs($this->admin)
        ->test('pages::reports.index')
        ->set('type', 'department')
        ->assertSee('قسم الصيانة')
        ->assertSee('250');
});

it('computes downtime totals per asset', function () {
    $asset = Asset::factory()->create(['name' => 'مولد']);
    DowntimeRecord::factory()->for($asset)->create(['start_time' => now()->subDays(2), 'end_time' => now()->subDays(2)->addHours(3), 'duration_minutes' => 180]);
    DowntimeRecord::factory()->for($asset)->create(['start_time' => now()->subDay(), 'end_time' => now()->subDay()->addHour(), 'duration_minutes' => 60]);

    Livewire::actingAs($this->admin)
        ->test('pages::reports.index')
        ->set('type', 'downtime')
        ->assertSee('مولد')
        ->assertSee('240');
});

it('lists low stock parts on the inventory report', function () {
    $part = SparePart::factory()->create(['name' => 'فلتر', 'quantity_in_stock' => 2, 'minimum_quantity' => 5]);

    Livewire::actingAs($this->admin)
        ->test('pages::reports.index')
        ->set('type', 'inventory')
        ->assertSee('فلتر')
        ->assertSee('2');
});

it('exports the cost-by-asset report as CSV', function () {
    $asset = Asset::factory()->create(['name' => 'معدة']);
    WorkOrder::factory()->for($asset)->create(['status' => 'completed', 'cost' => 100]);

    $response = $this->actingAs($this->admin)
        ->get(route('reports.export', ['type' => 'asset']));

    $response->assertOk();
    expect($response->streamedContent())->toContain('معدة');
});

it('forbids exporting reports without the reports.view permission', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('reports.export', ['type' => 'asset']))
        ->assertForbidden();
});

it('excludes cancelled work orders from cost reports', function () {
    $asset = Asset::factory()->create(['name' => 'أصل']);
    $cancelled = WorkOrder::factory()->for($asset)->create(['status' => 'cancelled', 'cost' => 500]);
    $open = WorkOrder::factory()->for($asset)->create(['status' => 'open', 'cost' => 0]);

    Livewire::actingAs($this->admin)
        ->test('pages::reports.index')
        ->set('type', 'asset')
        ->assertDontSee($open->code)
        ->assertDontSee($cancelled->code)
        ->assertSee('لا توجد بيانات');
});

it('shows the financial summary with top assets and clients', function () {
    $year = date('Y');
    $asset = Asset::factory()->create(['name' => 'معدة مكلفة']);
    WorkOrder::factory()->for($asset)->create([
        'scheduled_date' => "{$year}-06-10 10:00",
        'cost' => 4000,
    ]);

    $client = Client::factory()->create(['company_name' => 'شركة العميل']);
    Invoice::factory()->for($client, 'client')->paid()->create([
        'total_amount' => 10000,
        'subtotal' => 10000,
        'paid_at' => "{$year}-07-01 10:00",
    ]);

    Livewire::actingAs($this->admin)
        ->test('pages::reports.index')
        ->set('type', 'financial')
        ->set('from', "{$year}-01-01")
        ->set('to', "{$year}-12-31")
        ->assertSee('إجمالي المصروف')
        ->assertSee('إجمالي المكتسب')
        ->assertSee('صافي الربح')
        ->assertSee('4,000.00')
        ->assertSee('10,000.00')
        ->assertSee('6,000.00')
        ->assertSee('معدة مكلفة')
        ->assertSee('شركة العميل');
});

it('exports the financial report as CSV', function () {
    $year = date('Y');
    $asset = Asset::factory()->create();
    WorkOrder::factory()->for($asset)->create([
        'scheduled_date' => "{$year}-06-10 10:00",
        'cost' => 2500,
    ]);

    $response = $this->actingAs($this->admin)
        ->get(route('reports.export', ['type' => 'financial']));

    $response->assertOk();
    expect($response->streamedContent())
        ->toContain('إجمالي المصروف')
        ->toContain('إجمالي المكتسب')
        ->toContain('صافي الربح');
});

it('exports the summary report matching the on-screen cards', function () {
    $asset = Asset::factory()->create();
    WorkOrder::factory()->for($asset)->create(['status' => 'open', 'cost' => 0]);
    WorkOrder::factory()->for($asset)->create(['status' => 'completed', 'cost' => 150]);

    $response = $this->actingAs($this->admin)
        ->get(route('reports.export', ['type' => 'summary']));

    $response->assertOk();
    expect($response->streamedContent())
        ->toContain('إجمالي أوامر الشغل')
        ->toContain('أوامر مكتملة');
});
