<?php

use App\Models\SparePart;
use App\Models\Supplier;
use App\Models\User;
use App\Notifications\LowStockAlert;
use Database\Seeders\RolesAndPermissionsSeeder;

beforeEach(function () {
    (new RolesAndPermissionsSeeder)->run();

    $this->admin = User::factory()->create();
    $this->admin->assignRole('Admin');

    $this->supplier = Supplier::factory()->create();
});

it('renders the inventory index page for an authenticated user', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->get(route('inventory.index'))->assertOk();
});

it('renders the inventory list with spare parts', function () {
    $part = SparePart::factory()->create(['name' => 'فلتر زيت', 'code' => 'SP-1000']);

    $this->actingAs($this->admin)
        ->get(route('inventory.index'))
        ->assertOk()
        ->assertSee('فلتر زيت')
        ->assertSee('SP-1000');
});

it('lets an admin create a spare part', function () {
    Livewire::actingAs($this->admin)
        ->test('pages::inventory.index')
        ->call('openCreate')
        ->set('name', 'حزام سير')
        ->set('code', 'SP-2000')
        ->set('unit', 'piece')
        ->set('quantityInStock', 20)
        ->set('minimumQuantity', 5)
        ->set('unitPrice', '15.50')
        ->set('supplierId', $this->supplier->id)
        ->call('save')
        ->assertHasNoErrors();

    $this->assertDatabaseHas('spare_parts', [
        'name' => 'حزام سير',
        'code' => 'SP-2000',
        'quantity_in_stock' => 20,
        'minimum_quantity' => 5,
        'unit_price' => '15.50',
        'supplier_id' => $this->supplier->id,
    ]);
});

it('forbids a plain user from creating a spare part', function () {
    $user = User::factory()->create();

    Livewire::actingAs($user)
        ->test('pages::inventory.index')
        ->call('openCreate')
        ->assertForbidden();
});

it('validates unique code when creating a spare part', function () {
    SparePart::factory()->create(['code' => 'SP-3000']);

    Livewire::actingAs($this->admin)
        ->test('pages::inventory.index')
        ->call('openCreate')
        ->set('name', 'قطعة مكررة')
        ->set('code', 'SP-3000')
        ->call('save')
        ->assertHasErrors(['code' => 'unique']);
});

it('lets an admin edit a spare part', function () {
    $part = SparePart::factory()->create(['name' => 'قديم']);

    Livewire::actingAs($this->admin)
        ->test('pages::inventory.index')
        ->call('openEdit', $part->id)
        ->set('name', 'جديد')
        ->call('save')
        ->assertHasNoErrors();

    $this->assertDatabaseHas('spare_parts', ['id' => $part->id, 'name' => 'جديد']);
});

it('lets an admin delete a spare part', function () {
    $part = SparePart::factory()->create();

    Livewire::actingAs($this->admin)
        ->test('pages::inventory.index')
        ->call('askDelete', $part->id)
        ->call('delete');

    $this->assertDatabaseMissing('spare_parts', ['id' => $part->id]);
});

it('lets an admin add stock to a spare part', function () {
    $part = SparePart::factory()->create(['quantity_in_stock' => 10]);

    Livewire::actingAs($this->admin)
        ->test('pages::inventory.index')
        ->call('openStockForm', $part->id)
        ->set('stockAdjustment', 5)
        ->call('adjustStock')
        ->assertHasNoErrors();

    $this->assertDatabaseHas('spare_parts', ['id' => $part->id, 'quantity_in_stock' => 15]);
});

it('lets an admin remove stock from a spare part', function () {
    $part = SparePart::factory()->create(['quantity_in_stock' => 20]);

    Livewire::actingAs($this->admin)
        ->test('pages::inventory.index')
        ->call('openStockForm', $part->id)
        ->set('stockAdjustmentType', 'remove')
        ->set('stockAdjustment', 6)
        ->call('adjustStock')
        ->assertHasNoErrors();

    $this->assertDatabaseHas('spare_parts', ['id' => $part->id, 'quantity_in_stock' => 14]);
});

it('shows all spare parts including low-stock ones on the full list', function () {
    $low = SparePart::factory()->create(['name' => 'قطعة منخفضة', 'quantity_in_stock' => 2, 'minimum_quantity' => 5]);
    $high = SparePart::factory()->create(['name' => 'قطعة كافية', 'quantity_in_stock' => 50, 'minimum_quantity' => 5]);

    $this->actingAs($this->admin)
        ->get(route('inventory.index'))
        ->assertOk()
        ->assertSee($low->name)
        ->assertSee($high->name);
});

it('only lists low-stock parts when filtering', function () {
    $low = SparePart::factory()->create(['name' => 'قطعة منخفضة', 'quantity_in_stock' => 2, 'minimum_quantity' => 5]);
    SparePart::factory()->create(['name' => 'قطعة كافية', 'quantity_in_stock' => 50, 'minimum_quantity' => 5]);

    Livewire::actingAs($this->admin)
        ->test('pages::inventory.index')
        ->set('showLowStockOnly', true)
        ->assertSee($low->name)
        ->assertDontSee('قطعة كافية');
});

it('has a working low stock notification command', function () {
    Notification::fake();

    $admin = User::factory()->create();
    $admin->assignRole('Admin');

    SparePart::factory()->create(['quantity_in_stock' => 2, 'minimum_quantity' => 5, 'name' => 'منخفضة']);

    $this->artisan('inventory:notify-low-stock')->assertExitCode(0);

    Notification::assertSentTo($admin, LowStockAlert::class);
});
