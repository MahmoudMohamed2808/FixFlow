<?php

use App\Models\Asset;
use App\Models\AssetCategory;
use App\Models\Location;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

beforeEach(function () {
    (new RolesAndPermissionsSeeder)->run();

    $this->admin = User::factory()->create();
    $this->admin->assignRole('Admin');
});

it('redirects guests away from the assets pages', function () {
    $this->get(route('assets.index'))->assertRedirect('/login');

    $asset = Asset::factory()->create();

    $this->get(route('assets.show', $asset))->assertRedirect('/login');
});

it('only grants asset permissions through roles', function () {
    $viewer = User::factory()->create();

    expect($viewer->can('viewAny', Asset::class))->toBeFalse();
    expect($viewer->can('create', Asset::class))->toBeFalse();
    expect($this->admin->can('viewAny', Asset::class))->toBeTrue();
    expect($this->admin->can('create', Asset::class))->toBeTrue();
});

it('shows an asset detail page with its maintenance history', function () {
    $device = Asset::factory()->create();

    $this->actingAs($this->admin)
        ->get(route('assets.show', $device))
        ->assertOk()
        ->assertSee($device->name)
        ->assertSee($device->serial_number)
        ->assertSee('تاريخ الصيانة');
});

it('creates an asset and records the creator', function () {
    $category = AssetCategory::factory()->create();
    $location = Location::factory()->create();

    Livewire::actingAs($this->admin)
        ->test('pages::assets.index')
        ->set('name', 'مكبس هيدروليكي')
        ->set('serialNumber', 'SN-1001')
        ->set('categoryId', $category->id)
        ->set('locationId', $location->id)
        ->set('manufacturer', 'Bosch')
        ->set('model', 'H1500')
        ->set('status', 'operational')
        ->call('save')
        ->assertHasNoErrors();

    $this->assertDatabaseHas('assets', [
        'name' => 'مكبس هيدروليكي',
        'serial_number' => 'SN-1001',
        'category_id' => $category->id,
        'location_id' => $location->id,
        'created_by' => $this->admin->id,
    ]);
});

it('stores the uploaded asset image', function () {
    Storage::fake('public');

    Livewire::actingAs($this->admin)
        ->test('pages::assets.index')
        ->set('name', 'مضخة مياه')
        ->set('serialNumber', 'SN-2001')
        ->set('image', UploadedFile::fake()->image('pump.jpg'))
        ->call('save')
        ->assertHasNoErrors();

    $asset = Asset::where('serial_number', 'SN-2001')->firstOrFail();

    Storage::disk('public')->assertExists($asset->image);
});

it('requires a name and serial number when creating an asset', function () {
    Livewire::actingAs($this->admin)
        ->test('pages::assets.index')
        ->call('save')
        ->assertHasErrors(['name' => 'required', 'serialNumber' => 'required']);
});

it('rejects a duplicate serial number', function () {
    Asset::factory()->create(['serial_number' => 'SN-3001']);

    Livewire::actingAs($this->admin)
        ->test('pages::assets.index')
        ->set('name', 'ضاغط هواء')
        ->set('serialNumber', 'SN-3001')
        ->call('save')
        ->assertHasErrors(['serialNumber' => 'unique']);
});

it('updates an existing asset', function () {
    $device = Asset::factory()->create(['name' => 'الاسم القديم']);

    Livewire::actingAs($this->admin)
        ->test('pages::assets.index')
        ->call('openEdit', $device->id)
        ->set('name', 'الاسم الجديد')
        ->call('save')
        ->assertHasNoErrors();

    expect($device->refresh()->name)->toBe('الاسم الجديد');
});

it('soft deletes an asset', function () {
    $device = Asset::factory()->create();

    Livewire::actingAs($this->admin)
        ->test('pages::assets.index')
        ->call('askDelete', $device->id)
        ->call('delete');

    $this->assertSoftDeleted($device);
});

it('filters the assets list by status and search text', function () {
    $operational = Asset::factory()->create(['name' => 'جرار زراعي', 'status' => 'operational']);
    $broken = Asset::factory()->create(['name' => 'مولدة كهرباء', 'serial_number' => 'GEN-42', 'status' => 'broken']);

    $component = Livewire::actingAs($this->admin)
        ->test('pages::assets.index')
        ->set('filterStatus', 'broken');

    $component->assertSee($broken->name);

    $component->set('filterStatus', '')
        ->set('search', 'GEN-42')
        ->assertSee($broken->name)
        ->assertDontSee($operational->name);
});
