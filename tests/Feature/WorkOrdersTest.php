<?php

use App\Models\Asset;
use App\Models\SparePart;
use App\Models\User;
use App\Models\WorkOrder;
use App\Models\WorkOrderLabor;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

beforeEach(function () {
    (new RolesAndPermissionsSeeder)->run();

    $this->admin = User::factory()->create();
    $this->admin->assignRole('Admin');

    $this->technician = User::factory()->create();
    $this->technician->assignRole('Technician');
});

it('redirects guests away from the work order pages', function () {
    $this->get(route('work-orders.index'))->assertRedirect('/login');

    $order = WorkOrder::factory()->create();

    $this->get(route('work-orders.show', $order))->assertRedirect('/login');
});

it('only grants work order permissions through roles', function () {
    $viewer = User::factory()->create();

    expect($viewer->can('view', WorkOrder::first() ?? new WorkOrder))->toBeFalse();
    expect($this->admin->can('create', WorkOrder::class))->toBeTrue();
    expect($this->technician->can('create', WorkOrder::class))->toBeFalse();
});

it('generates a unique code for a new work order', function () {
    $asset = Asset::factory()->create();

    $created = WorkOrder::create([
        'asset_id' => $asset->id,
        'type' => 'corrective',
        'priority' => 'medium',
        'description' => 'اختبار توليد الكود',
    ]);

    expect($created->code)->toMatch('/^WO-\d{4}$/');
});

it('creates a work order through the livewire page', function () {
    $asset = Asset::factory()->create();

    Livewire::actingAs($this->admin)
        ->test('pages::work-orders.index')
        ->set('assetId', $asset->id)
        ->set('type', 'corrective')
        ->set('priority', 'high')
        ->set('description', 'عطل في المحرك')
        ->call('save')
        ->assertHasNoErrors();

    $this->assertDatabaseHas('work_orders', [
        'asset_id' => $asset->id,
        'type' => 'corrective',
        'priority' => 'high',
        'description' => 'عطل في المحرك',
        'reported_by' => $this->admin->id,
    ]);
});

it('requires an asset and description when creating a work order', function () {
    Livewire::actingAs($this->admin)
        ->test('pages::work-orders.index')
        ->call('save')
        ->assertHasErrors(['assetId' => 'required', 'description' => 'required']);
});

it('assigns a technician and moves the order to assigned', function () {
    $order = WorkOrder::factory()->create();

    Livewire::actingAs($this->admin)
        ->test('pages::work-orders.index')
        ->call('openAssign', $order->id)
        ->set('assignedTo', $this->technician->id)
        ->call('saveAssignment')
        ->assertHasNoErrors();

    $this->assertDatabaseHas('work_orders', [
        'id' => $order->id,
        'assigned_to' => $this->technician->id,
        'status' => 'assigned',
    ]);
});

it('updates the work order status and records completion', function () {
    $order = WorkOrder::factory()->create();

    Livewire::actingAs($this->admin)
        ->test('pages::work-orders.show', ['workOrder' => $order])
        ->call('updateStatus', 'in_progress');

    $order->refresh();

    expect($order->status)->toBe('in_progress');
    expect($order->started_at)->not->toBeNull();

    Livewire::actingAs($this->admin)
        ->test('pages::work-orders.show', ['workOrder' => $order])
        ->call('updateStatus', 'completed');

    expect($order->refresh()->status)->toBe('completed');
    expect($order->completed_at)->not->toBeNull();
});

it('allows the assigned technician to update the status', function () {
    $order = WorkOrder::factory()->create(['assigned_to' => $this->technician->id]);

    Livewire::actingAs($this->technician)
        ->test('pages::work-orders.show', ['workOrder' => $order])
        ->call('updateStatus', 'in_progress')
        ->assertHasNoErrors();

    expect($order->refresh()->status)->toBe('in_progress');
});

it('adds a comment to the work order', function () {
    $order = WorkOrder::factory()->create();

    $this->assertDatabaseCount('work_order_comments', 0);

    Livewire::actingAs($this->admin)
        ->test('pages::work-orders.show', ['workOrder' => $order])
        ->set('comment', 'تم إصلاح العطل مؤقتًا')
        ->call('addComment')
        ->assertHasNoErrors();

    $this->assertDatabaseHas('work_order_comments', [
        'work_order_id' => $order->id,
        'user_id' => $this->admin->id,
        'comment' => 'تم إصلاح العطل مؤقتًا',
    ]);
});

it('decrements stock and records cost when a part is added', function () {
    $order = WorkOrder::factory()->create();
    $part = SparePart::factory()->create(['quantity_in_stock' => 10, 'unit_price' => 25]);

    Livewire::actingAs($this->admin)
        ->test('pages::work-orders.show', ['workOrder' => $order])
        ->set('addingPartId', $part->id)
        ->set('quantityUsed', 2)
        ->call('addPart')
        ->assertHasNoErrors();

    $this->assertDatabaseHas('work_order_parts', [
        'work_order_id' => $order->id,
        'spare_part_id' => $part->id,
        'quantity_used' => 2,
        'unit_cost' => 25,
    ]);

    expect($part->refresh()->quantity_in_stock)->toBe(8);
    expect((float) $order->refresh()->partsCost)->toBe(50.0);
});

it('refuses to add a part when stock is insufficient', function () {
    $order = WorkOrder::factory()->create();
    $part = SparePart::factory()->create(['quantity_in_stock' => 1]);

    Livewire::actingAs($this->admin)
        ->test('pages::work-orders.show', ['workOrder' => $order])
        ->set('addingPartId', $part->id)
        ->set('quantityUsed', 5)
        ->call('addPart');

    $this->assertDatabaseCount('work_order_parts', 0);
    expect($part->refresh()->quantity_in_stock)->toBe(1);
});

it('stores an uploaded attachment for the work order', function () {
    Storage::fake('public');

    $order = WorkOrder::factory()->create();

    Livewire::actingAs($this->admin)
        ->test('pages::work-orders.show', ['workOrder' => $order])
        ->set('attachment', UploadedFile::fake()->image('photo.jpg'))
        ->call('uploadAttachment')
        ->assertHasNoErrors();

    $this->assertDatabaseCount('work_order_attachments', 1);

    Storage::disk('public')->assertExists($order->refresh()->attachments->first()->file_path);
});

it('soft deletes a work order', function () {
    $order = WorkOrder::factory()->create();

    Livewire::actingAs($this->admin)
        ->test('pages::work-orders.index')
        ->call('askDelete', $order->id)
        ->call('delete');

    $this->assertSoftDeleted($order);
});

it('records labor hours and rolls the cost into the work order', function () {
    $order = WorkOrder::factory()->create(['cost' => 0]);

    Livewire::actingAs($this->admin)
        ->test('pages::work-orders.show', ['workOrder' => $order])
        ->set('addingLaborUserId', $this->technician->id)
        ->set('laborHours', 3)
        ->set('laborRate', 100)
        ->call('addLabor')
        ->assertHasNoErrors()
        ->assertSee('300.00');

    expect((float) $order->fresh()->cost)->toBe(300.0);
});

it('autofills the hourly rate from the technician profile', function () {
    $this->technician->update(['hourly_rate' => 150]);
    $order = WorkOrder::factory()->create();

    Livewire::actingAs($this->admin)
        ->test('pages::work-orders.show', ['workOrder' => $order])
        ->set('addingLaborUserId', $this->technician->id)
        ->assertSet('laborRate', 150.0);
});

it('lets the assigned technician record labor but not a viewer', function () {
    $order = WorkOrder::factory()->create(['assigned_to' => $this->technician->id, 'cost' => 0]);
    $viewer = User::factory()->create();
    $viewer->assignRole('Viewer');

    Livewire::actingAs($this->technician)
        ->test('pages::work-orders.show', ['workOrder' => $order])
        ->set('addingLaborUserId', $this->technician->id)
        ->set('laborHours', 2)
        ->set('laborRate', 80)
        ->call('addLabor')
        ->assertHasNoErrors();

    expect((float) $order->fresh()->cost)->toBe(160.0);

    Livewire::actingAs($viewer)
        ->test('pages::work-orders.show', ['workOrder' => $order])
        ->set('addingLaborUserId', $this->technician->id)
        ->set('laborHours', 2)
        ->set('laborRate', 80)
        ->call('addLabor')
        ->assertForbidden();
});

it('deletes a labor entry and recalculates the cost down', function () {
    $order = WorkOrder::factory()->create(['cost' => 0]);
    $entry = WorkOrderLabor::factory()->for($order, 'workOrder')->create([
        'hours_spent' => 4,
        'hourly_rate' => 50,
    ]);

    expect((float) $order->fresh()->cost)->toBe(200.0);

    Livewire::actingAs($this->admin)
        ->test('pages::work-orders.show', ['workOrder' => $order])
        ->call('deleteLabor', $entry->id)
        ->assertHasNoErrors();

    expect((float) $order->fresh()->cost)->toBe(0.0);
});

it('includes parts and labor in the cost when completing an order', function () {
    $order = WorkOrder::factory()->create(['cost' => 0]);
    $part = SparePart::factory()->create(['quantity_in_stock' => 50, 'unit_price' => 40]);

    Livewire::actingAs($this->admin)
        ->test('pages::work-orders.show', ['workOrder' => $order])
        ->set('addingPartId', $part->id)
        ->set('quantityUsed', 2)
        ->call('addPart')
        ->set('addingLaborUserId', $this->technician->id)
        ->set('laborHours', 1)
        ->set('laborRate', 120)
        ->call('addLabor')
        ->call('updateStatus', 'completed')
        ->assertHasNoErrors();

    // 2 × 40 قطع + 1 × 120 أجور = 200
    expect((float) $order->fresh()->cost)->toBe(200.0);
});
