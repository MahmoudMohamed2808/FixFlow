<?php

use App\Console\Commands\GenerateWorkOrdersFromSchedules;
use App\Models\Asset;
use App\Models\PreventiveSchedule;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;

beforeEach(function () {
    (new RolesAndPermissionsSeeder)->run();

    $this->admin = User::factory()->create();
    $this->admin->assignRole('Admin');

    $this->asset = Asset::factory()->create();
});

it('forbids guests from visiting the schedules page', function () {
    $this->get(route('schedules.index'))->assertRedirect(route('login'));
});

it('renders the schedules index page for an authenticated user', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->get(route('schedules.index'))->assertOk();
});

it('renders the schedules list with schedules', function () {
    $schedule = PreventiveSchedule::factory()->for($this->asset)->create([
        'title' => 'تزييت شهري',
        'frequency_type' => 'monthly',
        'frequency_value' => 1,
        'next_due_date' => now()->addMonth(),
    ]);

    $this->actingAs($this->admin)
        ->get(route('schedules.index'))
        ->assertOk()
        ->assertSee('تزييت شهري');
});

it('lets an admin create a preventive schedule', function () {
    Livewire::actingAs($this->admin)
        ->test('pages::schedules.index')
        ->call('openCreate')
        ->set('assetId', $this->asset->id)
        ->set('title', 'فحص دوري')
        ->set('frequencyType', 'monthly')
        ->set('frequencyValue', 1)
        ->set('nextDueDate', now()->addMonth()->format('Y-m-d'))
        ->call('save')
        ->assertHasNoErrors();

    $this->assertDatabaseHas('preventive_schedules', [
        'asset_id' => $this->asset->id,
        'title' => 'فحص دوري',
    ]);
});

it('forbids a plain user from creating a schedule', function () {
    $user = User::factory()->create();

    Livewire::actingAs($user)
        ->test('pages::schedules.index')
        ->call('openCreate')
        ->assertForbidden();
});

it('lets an admin edit a preventive schedule', function () {
    $schedule = PreventiveSchedule::factory()->for($this->asset)->create();

    Livewire::actingAs($this->admin)
        ->test('pages::schedules.index')
        ->call('openEdit', $schedule->id)
        ->set('title', 'عنوان محدث')
        ->call('save')
        ->assertHasNoErrors();

    $this->assertDatabaseHas('preventive_schedules', [
        'id' => $schedule->id,
        'title' => 'عنوان محدث',
    ]);
});

it('lets an admin toggle a schedule active state', function () {
    $schedule = PreventiveSchedule::factory()->for($this->asset)->create(['is_active' => true]);

    Livewire::actingAs($this->admin)
        ->test('pages::schedules.index')
        ->call('toggleActive', $schedule->id);

    $this->assertDatabaseHas('preventive_schedules', [
        'id' => $schedule->id,
        'is_active' => false,
    ]);
});

it('lets an admin delete a preventive schedule', function () {
    $schedule = PreventiveSchedule::factory()->for($this->asset)->create();

    Livewire::actingAs($this->admin)
        ->test('pages::schedules.index')
        ->call('askDelete', $schedule->id)
        ->call('delete');

    $this->assertDatabaseMissing('preventive_schedules', ['id' => $schedule->id]);
});

it('validates required fields when creating a schedule', function () {
    Livewire::actingAs($this->admin)
        ->test('pages::schedules.index')
        ->call('openCreate')
        ->call('save')
        ->assertHasErrors(['assetId' => 'required', 'title' => 'required', 'nextDueDate' => 'required']);
});

it('does not include inactive schedules when filtering active only', function () {
    $active = PreventiveSchedule::factory()->for($this->asset)->create(['title' => 'جدول نشط', 'is_active' => true]);
    PreventiveSchedule::factory()->for($this->asset)->create(['title' => 'جدول معطل', 'is_active' => false]);

    Livewire::actingAs($this->admin)
        ->test('pages::schedules.index')
        ->set('showActiveOnly', true)
        ->assertSee($active->title)
        ->assertDontSee('جدول معطل');
});

it('generates a work order and advances the due date for a due schedule', function () {
    $this->travelTo(now()->startOfDay());

    $schedule = PreventiveSchedule::factory()->for($this->asset)->create([
        'frequency_type' => 'monthly',
        'frequency_value' => 1,
        'next_due_date' => now()->subDay(),
        'last_generated_date' => null,
    ]);

    $this->artisan(GenerateWorkOrdersFromSchedules::class)
        ->assertExitCode(0);

    $this->assertDatabaseHas('work_orders', [
        'schedule_id' => $schedule->id,
        'asset_id' => $this->asset->id,
        'type' => 'preventive',
        'status' => 'open',
    ]);

    $schedule->refresh();

    $this->assertNotNull($schedule->last_generated_date);
    expect($schedule->next_due_date->toDateString())
        ->toBe(now()->addMonth()->toDateString());
});

it('does not generate a work order for a schedule that is not yet due', function () {
    $this->travelTo(now()->startOfDay());

    $schedule = PreventiveSchedule::factory()->for($this->asset)->create([
        'next_due_date' => now()->addWeek(),
    ]);

    $this->artisan(GenerateWorkOrdersFromSchedules::class)
        ->assertExitCode(0);

    $this->assertDatabaseMissing('work_orders', ['schedule_id' => $schedule->id]);
});

it('does not generate a work order for an inactive schedule', function () {
    $this->travelTo(now()->startOfDay());

    $schedule = PreventiveSchedule::factory()->for($this->asset)->create([
        'next_due_date' => now()->subDay(),
        'is_active' => false,
    ]);

    $this->artisan(GenerateWorkOrdersFromSchedules::class)
        ->assertExitCode(0);

    $this->assertDatabaseMissing('work_orders', ['schedule_id' => $schedule->id]);
});
