<?php

use App\Models\Client;
use App\Models\Invoice;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Livewire\Livewire;

beforeEach(function () {
    (new RolesAndPermissionsSeeder)->run();
});

it('lets an admin create a client', function () {
    $admin = User::factory()->create()->assignRole('Admin');

    Livewire::actingAs($admin)
        ->test('pages::clients.index')
        ->call('openCreate')
        ->set('name', 'عميل جديد')
        ->set('companyName', 'شركة النور')
        ->set('phone', '01001234567')
        ->call('save')
        ->assertHasNoErrors();

    expect(Client::where('name', 'عميل جديد')->exists())->toBeTrue();
});

it('requires a client name', function () {
    $admin = User::factory()->create()->assignRole('Admin');

    Livewire::actingAs($admin)
        ->test('pages::clients.index')
        ->call('openCreate')
        ->set('name', '')
        ->call('save')
        ->assertHasErrors(['name']);
});

it('lets an admin update a client', function () {
    $admin = User::factory()->create()->assignRole('Admin');
    $client = Client::factory()->create(['name' => 'قديم']);

    Livewire::actingAs($admin)
        ->test('pages::clients.index')
        ->call('openEdit', $client->id)
        ->set('name', 'جديد')
        ->call('save')
        ->assertHasNoErrors();

    expect($client->fresh()->name)->toBe('جديد');
});

it('lets an admin delete a client without invoices', function () {
    $admin = User::factory()->create()->assignRole('Admin');
    $client = Client::factory()->create();

    Livewire::actingAs($admin)
        ->test('pages::clients.index')
        ->call('askDelete', $client->id)
        ->call('delete')
        ->assertHasNoErrors();

    expect(Client::find($client->id))->toBeNull();
});

it('refuses to delete a client that has invoices', function () {
    $admin = User::factory()->create()->assignRole('Admin');
    $client = Client::factory()->create();
    Invoice::factory()->for($client, 'client')->create();

    Livewire::actingAs($admin)
        ->test('pages::clients.index')
        ->call('askDelete', $client->id)
        ->call('delete')
        ->assertHasNoErrors();

    expect(Client::find($client->id))->not->toBeNull();
});

it('forbids client mutations without permission', function () {
    $viewer = User::factory()->create()->assignRole('Viewer');

    Livewire::actingAs($viewer)
        ->test('pages::clients.index')
        ->call('openCreate')
        ->assertForbidden();
});

it('searches clients by name and company', function () {
    $admin = User::factory()->create()->assignRole('Admin');
    Client::factory()->create(['name' => 'عميل مميز', 'company_name' => 'شركة خاصة']);

    Livewire::actingAs($admin)
        ->test('pages::clients.index')
        ->set('search', 'مميز')
        ->assertSee('عميل مميز');
});
