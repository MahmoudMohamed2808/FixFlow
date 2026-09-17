<?php

use App\Models\Supplier;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;

beforeEach(function () {
    (new RolesAndPermissionsSeeder)->run();

    $this->admin = User::factory()->create();
    $this->admin->assignRole('Admin');
});

it('renders the suppliers index page for an authenticated user', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->get(route('suppliers.index'))->assertOk();
});

it('renders the suppliers list', function () {
    $supplier = Supplier::factory()->create(['name' => 'شركة الخليج']);

    $this->actingAs($this->admin)
        ->get(route('suppliers.index'))
        ->assertOk()
        ->assertSee('شركة الخليج');
});

it('lets an admin create a supplier', function () {
    Livewire::actingAs($this->admin)
        ->test('pages::suppliers.index')
        ->call('openCreate')
        ->set('name', 'شركة النور')
        ->set('phone', '0501234567')
        ->set('email', 'supplier@test.com')
        ->set('address', 'الرياض')
        ->call('save')
        ->assertHasNoErrors();

    $this->assertDatabaseHas('suppliers', [
        'name' => 'شركة النور',
        'phone' => '0501234567',
        'email' => 'supplier@test.com',
    ]);
});

it('forbids a plain user from creating a supplier', function () {
    $user = User::factory()->create();

    Livewire::actingAs($user)
        ->test('pages::suppliers.index')
        ->call('openCreate')
        ->assertForbidden();
});

it('validates the name field when creating a supplier', function () {
    Livewire::actingAs($this->admin)
        ->test('pages::suppliers.index')
        ->call('openCreate')
        ->call('save')
        ->assertHasErrors(['name' => 'required']);
});

it('lets an admin edit a supplier', function () {
    $supplier = Supplier::factory()->create(['name' => 'قديم']);

    Livewire::actingAs($this->admin)
        ->test('pages::suppliers.index')
        ->call('openEdit', $supplier->id)
        ->set('name', 'جديد')
        ->call('save')
        ->assertHasNoErrors();

    $this->assertDatabaseHas('suppliers', ['id' => $supplier->id, 'name' => 'جديد']);
});

it('lets an admin delete a supplier', function () {
    $supplier = Supplier::factory()->create();

    Livewire::actingAs($this->admin)
        ->test('pages::suppliers.index')
        ->call('askDelete', $supplier->id)
        ->call('delete');

    $this->assertDatabaseMissing('suppliers', ['id' => $supplier->id]);
});
